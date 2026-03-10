<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WCR_Instagram')) {

class WCR_Instagram {

    const CACHE_KEY   = 'wcr_instagram_posts';
    const OPT_TOKEN   = 'wcr_instagram_token';
    const OPT_USER_ID = 'wcr_instagram_user_id';

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
        if (!$token) return;
        $res = wp_remote_get("https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token={$token}");
        if (is_wp_error($res)) return;
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($data['access_token']))
            update_option(self::OPT_TOKEN, $data['access_token']);
    }

    private static function api_get($url) {
        $res = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($res)) return [];
        return json_decode(wp_remote_retrieve_body($res), true) ?? [];
    }

    public static function fetch_tagged() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        if (!$token || !$uid) return [];
        if (!get_option('wcr_instagram_use_tagged', 1)) return [];
        $data  = self::api_get("https://graph.facebook.com/v19.0/{$uid}/tags?fields=id,media_type,media_url,thumbnail_url,permalink,timestamp,username,like_count&limit=20&access_token={$token}");
        $posts = $data['data'] ?? [];
        foreach ($posts as &$p) $p['source'] = 'tagged';
        return $posts;
    }

    public static function fetch_hashtag() {
        $token = get_option(self::OPT_TOKEN);
        $uid   = get_option(self::OPT_USER_ID);
        if (!$token || !$uid) return [];
        if (!get_option('wcr_instagram_use_hashtag', 1)) return [];
        $raw      = get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf');
        $hashtags = array_filter(array_map('trim', explode("\n", $raw)));
        $all = [];
        foreach ($hashtags as $hashtag) {
            $hashtag = ltrim($hashtag, '#');
            if (!$hashtag) continue;
            $search = self::api_get("https://graph.facebook.com/v19.0/ig-hashtag-search?user_id={$uid}&q=" . urlencode($hashtag) . "&access_token={$token}");
            if (empty($search['data'][0]['id'])) continue;
            $hid  = $search['data'][0]['id'];
            $data = self::api_get("https://graph.facebook.com/v19.0/{$hid}/recent_media?user_id={$uid}&fields=id,media_type,media_url,thumbnail_url,permalink,timestamp,like_count&limit=20&access_token={$token}");
            $posts = $data['data'] ?? [];
            foreach ($posts as &$p) { $p['source'] = 'hashtag'; $p['hashtag'] = $hashtag; }
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
        if ($min_likes > 0)
            $unique = array_values(array_filter($unique, fn($p) => (int)($p['like_count'] ?? 0) >= $min_likes));

        usort($unique, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        $max    = (int) get_option('wcr_instagram_max_posts', 8);
        $result = array_slice($unique, 0, max($max, 20));
        set_transient(self::CACHE_KEY, $result, 15 * MINUTE_IN_SECONDS);
        return $result;
    }

    public static function get_posts() {
        return get_transient(self::CACHE_KEY) ?: self::refresh_cache();
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
        $keys = (array) array_rand($pool_videos, min($count, count($pool_videos)));
        return array_map(fn($k) => $pool_videos[$k], $keys);
    }

    public static function get_weekly_best() {
        if (!get_option('wcr_instagram_weekly_best', 1)) return null;
        $cutoff = strtotime('-7 days');
        $posts  = self::get_posts();
        $week   = array_values(array_filter($posts, fn($p) => strtotime($p['timestamp']) >= $cutoff));
        if (!$week) return null;
        usort($week, fn($a, $b) => (int)($b['like_count'] ?? 0) - (int)($a['like_count'] ?? 0));
        return $week[0] ?? null;
    }
}

add_action('plugins_loaded', ['WCR_Instagram', 'init']);

} // end class_exists
