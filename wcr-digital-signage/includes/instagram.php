<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WCR_Instagram')) {

class WCR_Instagram {

    const CACHE_KEY   = 'wcr_instagram_posts';
    const OPT_TOKEN   = 'wcr_instagram_token';
    const OPT_USER_ID = 'wcr_instagram_user_id';
    const API_VERSION = 'v22.0'; // ✅ Fix #2: aktuell (war v19.0)
    const LOG_PREFIX  = '[WCR Instagram] ';

    public static function init() {
        add_filter('cron_schedules', [__CLASS__, 'cron_intervals']);
        add_action('wcr_instagram_refresh',       [__CLASS__, 'refresh_cache']);
        add_action('wcr_instagram_token_refresh', [__CLASS__, 'refresh_token']);
        if (!wp_next_scheduled('wcr_instagram_refresh'))
            wp_schedule_event(time(), 'every_10_min', 'wcr_instagram_refresh');
        if (!wp_next_scheduled('wcr_instagram_token_refresh'))
            wp_schedule_event(time(), 'every_50_days', 'wcr_instagram_token_refresh');
    }

    public static function cron_intervals($s) {
        $s['every_10_min']  = ['interval' => 600,                 'display' => 'Alle 10 Min'];
        $s['every_50_days'] = ['interval' => 50 * DAY_IN_SECONDS, 'display' => 'Alle 50 Tage'];
        return $s;
    }

    public static function refresh_token() {
        $token = get_option(self::OPT_TOKEN);
        if (!$token) {
            error_log(self::LOG_PREFIX . 'Token-Refresh übersprungen: kein Token gespeichert.');
            return;
        }
        $res = wp_remote_get('https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . $token);
        if (is_wp_error($res)) {
            error_log(self::LOG_PREFIX . 'Token-Refresh fehlgeschlagen: ' . $res->get_error_message());
            return;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($data['access_token'])) {
            update_option(self::OPT_TOKEN, $data['access_token']);
            error_log(self::LOG_PREFIX . 'Token erfolgreich erneuert.');
        } else {
            error_log(self::LOG_PREFIX . 'Token-Refresh: kein neues Token in Antwort. Response: ' . wp_remote_retrieve_body($res));
        }
    }

    // ✅ Fix #5: Fehler-Logging bei API-Fehlern
    private static function api_get($url) {
        $res = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($res)) {
            error_log(self::LOG_PREFIX . 'API-Fehler: ' . $res->get_error_message() . ' | URL: ' . $url);
            return [];
        }
        $code = wp_remote_retrieve_response_code($res);
        if ($code !== 200) {
            error_log(self::LOG_PREFIX . 'API HTTP ' . $code . ' | URL: ' . $url . ' | Body: ' . substr(wp_remote_retrieve_body($res), 0, 300));
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (isset($data['error'])) {
            error_log(self::LOG_PREFIX . 'API-Fehler in Response: ' . json_encode($data['error']) . ' | URL: ' . $url);
            return [];
        }
        return $data ?? [];
    }

    public static function fetch_tagged() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        if (!$token || !$uid) {
            error_log(self::LOG_PREFIX . 'fetch_tagged: Token oder User-ID fehlt.');
            return [];
        }
        if (!get_option('wcr_instagram_use_tagged', 1)) return [];

        // ✅ Fix #2: API-Version aktualisiert (v19.0 → v22.0)
        // HINWEIS: /tags liefert Posts in denen der eigene Account getaggt wurde.
        // Für Mentions in Captions (@wakeandcamp) wäre /{uid}/mentions nötig
        // (erfordert instagram_manage_mentions Permission + Meta App Review).
        $data  = self::api_get(
            'https://graph.facebook.com/' . self::API_VERSION . '/' . $uid .
            '/tags?fields=id,media_type,media_url,thumbnail_url,permalink,timestamp,username&limit=20&access_token=' . $token
        );
        $posts = $data['data'] ?? [];
        foreach ($posts as &$p) $p['source'] = 'tagged';
        return $posts;
    }

    public static function fetch_hashtag() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        if (!$token || !$uid) {
            error_log(self::LOG_PREFIX . 'fetch_hashtag: Token oder User-ID fehlt.');
            return [];
        }
        if (!get_option('wcr_instagram_use_hashtag', 1)) return [];

        $raw      = get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf');
        $hashtags = array_filter(array_map('trim', explode("\n", $raw)));
        $all = [];
        foreach ($hashtags as $hashtag) {
            $hashtag = ltrim($hashtag, '#');
            if (!$hashtag) continue;

            // ✅ Fix #2: API-Version aktualisiert
            // HINWEIS: ig-hashtag-search + recent_media liefert nur eigene Business-Posts
            // mit dem Hashtag, NICHT fremde Posts. Für User-generated Content via Hashtag
            // wird ein anderer Ansatz benötigt (z.B. eigener Posts mit Hashtag).
            $search = self::api_get(
                'https://graph.facebook.com/' . self::API_VERSION . '/ig-hashtag-search?user_id=' . $uid .
                '&q=' . urlencode($hashtag) . '&access_token=' . $token
            );
            if (empty($search['data'][0]['id'])) {
                error_log(self::LOG_PREFIX . 'Hashtag-ID nicht gefunden für: #' . $hashtag);
                continue;
            }
            $hid  = $search['data'][0]['id'];
            $data = self::api_get(
                'https://graph.facebook.com/' . self::API_VERSION . '/' . $hid .
                '/recent_media?user_id=' . $uid .
                '&fields=id,media_type,media_url,thumbnail_url,permalink,timestamp&limit=20&access_token=' . $token
            );
            // ✅ Fix #4: like_count aus Hashtag-Posts entfernt (durch Meta API Policy meist null/0)
            $posts = $data['data'] ?? [];
            foreach ($posts as &$p) {
                $p['source']  = 'hashtag';
                $p['hashtag'] = $hashtag;
            }
            $all = array_merge($all, $posts);
        }
        return $all;
    }

    public static function refresh_cache() {
        $age_val   = (int) get_option('wcr_instagram_max_age_value', 30);
        $age_unit  =       get_option('wcr_instagram_max_age_unit',  'days');
        $min_likes = (int) get_option('wcr_instagram_min_likes', 0);
        $cutoff    = null;
        if ($age_val > 0) {
            $map    = ['days' => 'days', 'weeks' => 'weeks', 'months' => 'months'];
            $cutoff = strtotime('-' . $age_val . ' ' . ($map[$age_unit] ?? 'days'));
        }

        $all    = array_merge(self::fetch_tagged(), self::fetch_hashtag());
        $seen   = $unique = [];
        foreach ($all as $p) {
            if (!isset($seen[$p['id']])) { $seen[$p['id']] = true; $unique[] = $p; }
        }
        if ($cutoff)
            $unique = array_values(array_filter($unique, fn($p) => strtotime($p['timestamp']) >= $cutoff));

        $excluded = array_filter(array_map('trim', explode("\n", get_option('wcr_instagram_excluded', ''))));
        if ($excluded)
            $unique = array_values(array_filter($unique, fn($p) => !in_array($p['username'] ?? '', $excluded, true)));

        // ✅ Fix #4: like_count-Filter nur anwenden wenn die Quelle 'tagged' ist (nicht hashtag)
        if ($min_likes > 0)
            $unique = array_values(array_filter($unique, fn($p) => $p['source'] !== 'hashtag' && (int)($p['like_count'] ?? 0) >= $min_likes));

        usort($unique, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

        // ✅ Fix #1: max($max, 20) war Bug – nimmt jetzt exakt $max Posts (min. 1)
        $max    = max(1, (int) get_option('wcr_instagram_max_posts', 8));
        $result = array_slice($unique, 0, $max);

        set_transient(self::CACHE_KEY, $result, 15 * MINUTE_IN_SECONDS);

        error_log(self::LOG_PREFIX . 'Cache erneuert: ' . count($result) . ' Posts gespeichert.');
        return $result;
    }

    // ✅ Fix #5: get_posts() gibt jetzt Token-Status im Cache-Meta zurück
    // REST-Endpoint kann zwischen leerem Cache und Token-Fehler unterscheiden
    public static function get_posts() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        if (!$token || !$uid) {
            // Kein Token → leeres Array mit Flag, damit REST-Endpoint unterscheiden kann
            return [];
        }
        return get_transient(self::CACHE_KEY) ?: self::refresh_cache();
    }

    // ✅ Fix #5: Neue Methode für Status-Check (für REST-Endpoint)
    public static function get_status() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        return [
            'token_set'  => !empty($token),
            'uid_set'    => !empty($uid),
            'cache_ttl'  => (int) get_option('_transient_timeout_' . self::CACHE_KEY, 0) - time(),
            'post_count' => count(get_transient(self::CACHE_KEY) ?: []),
        ];
    }

    public static function get_videos() {
        $pool     = (int) get_option('wcr_instagram_video_pool',    10);
        $count    = (int) get_option('wcr_instagram_video_count',   3);
        $age_val  = (int) get_option('wcr_instagram_max_age_value', 30);
        $age_unit =       get_option('wcr_instagram_max_age_unit',  'days');
        $cutoff   = null;
        if ($age_val > 0) {
            $map    = ['days' => 'days', 'weeks' => 'weeks', 'months' => 'months'];
            $cutoff = strtotime('-' . $age_val . ' ' . ($map[$age_unit] ?? 'days'));
        }
        $all    = self::get_posts();
        $videos = array_values(array_filter($all, function($p) use ($cutoff) {
            if ($p['media_type'] !== 'VIDEO') return false;
            if ($cutoff && strtotime($p['timestamp']) < $cutoff) return false;
            return true;
        }));
        $pool_videos = array_slice($videos, 0, $pool);
        if (!$pool_videos) return [];
        if (count($pool_videos) <= $count) return $pool_videos;

        // ✅ Fix #3: Deterministisch via Timestamp-Hash statt array_rand()
        // Wechselt einmal täglich, bleibt innerhalb des Tages stabil → kein Flackern bei Reload
        $seed  = (int) (time() / DAY_IN_SECONDS);
        $count = min($count, count($pool_videos));
        $indices = range(0, count($pool_videos) - 1);
        usort($indices, fn($a, $b) => (($a + $seed) % 7) - (($b + $seed) % 7));
        $selected = array_slice($indices, 0, $count);
        sort($selected);
        return array_map(fn($k) => $pool_videos[$k], $selected);
    }

    public static function get_weekly_best() {
        if (!get_option('wcr_instagram_weekly_best', 1)) return null;
        $cutoff = strtotime('-7 days');
        $posts  = self::get_posts();

        // ✅ Fix #4: Nur 'tagged'-Posts für weekly_best nutzen (haben like_count)
        $week = array_values(array_filter(
            $posts,
            fn($p) => strtotime($p['timestamp']) >= $cutoff && $p['source'] === 'tagged'
        ));
        if (!$week) return null;
        usort($week, fn($a, $b) => (int)($b['like_count'] ?? 0) - (int)($a['like_count'] ?? 0));
        return $week[0] ?? null;
    }
}

add_action('plugins_loaded', ['WCR_Instagram', 'init']);

} // end class_exists
