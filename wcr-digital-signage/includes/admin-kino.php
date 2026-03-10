<?php
if (!defined('ABSPATH')) exit;

/* ====================================================
   WCR Kino Admin Backend
   Film-Verwaltung für Open-Air-Kino-Programm
   ==================================================== */

function wcr_kino_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'wcr_kino';

    // ── POST: Film hinzufügen / aktualisieren / löschen ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wcr_kino_action', 'wcr_kino_nonce')) {
        $action = sanitize_text_field($_POST['action'] ?? '');

        if ($action === 'add' || $action === 'update') {
            $id         = $action === 'update' ? intval($_POST['id'] ?? 0) : 0;
            $title      = sanitize_text_field($_POST['title'] ?? '');
            $cover_url  = esc_url_raw($_POST['cover_url'] ?? '');
            $date       = sanitize_text_field($_POST['date'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);

            $data = compact('title', 'cover_url', 'date', 'sort_order');

            if ($action === 'add') {
                $wpdb->insert($table, $data);
                echo '<div class="notice notice-success"><p>✅ Film hinzugefügt!</p></div>';
            } else {
                $wpdb->update($table, $data, ['id' => $id]);
                echo '<div class="notice notice-success"><p>✅ Film aktualisiert!</p></div>';
            }
        }

        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $wpdb->delete($table, ['id' => $id]);
            echo '<div class="notice notice-success"><p>✅ Film gelöscht!</p></div>';
        }
    }

    // ── Alle Filme laden ──
    $films = $wpdb->get_results("SELECT * FROM $table ORDER BY date ASC, sort_order ASC");

    // ── Edit-Modus ──
    $edit = null;
    if (isset($_GET['edit'])) {
        $edit_id = intval($_GET['edit']);
        $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
    }

    ?>
    <div class="wrap">
        <h1>🎬 WCR Kino-Programm</h1>
        <p>Open-Air-Kino: Filme mit Cover, Titel und Datum verwalten.</p>

        <!-- ── Formular ── -->
        <div style="background:#fff;padding:20px;border:1px solid #ccc;margin:20px 0;max-width:600px;">
            <h2><?php echo $edit ? '✏️ Film bearbeiten' : '➕ Neuer Film'; ?></h2>
            <form method="post">
                <?php wp_nonce_field('wcr_kino_action', 'wcr_kino_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'add'; ?>">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $edit->id; ?>">
                <?php endif; ?>

                <p>
                    <label><strong>Filmtitel:</strong></label><br>
                    <input type="text" name="title" value="<?php echo esc_attr($edit->title ?? ''); ?>" 
                           style="width:100%;padding:8px;" required>
                </p>

                <p>
                    <label><strong>Cover-URL:</strong></label><br>
                    <input type="url" name="cover_url" value="<?php echo esc_attr($edit->cover_url ?? ''); ?>" 
                           placeholder="https://example.com/poster.jpg" style="width:100%;padding:8px;" required>
                    <small>Direkte URL zum Film-Poster (PNG/JPG).</small>
                </p>

                <p>
                    <label><strong>Spieltag:</strong></label><br>
                    <input type="date" name="date" value="<?php echo esc_attr($edit->date ?? ''); ?>" 
                           style="padding:8px;" required>
                    <small>Film wird ab dem Folgetag automatisch ausgeblendet.</small>
                </p>

                <p>
                    <label><strong>Sortierung:</strong></label><br>
                    <input type="number" name="sort_order" value="<?php echo esc_attr($edit->sort_order ?? 0); ?>" 
                           style="width:100px;padding:8px;">
                    <small>Niedrigere Zahlen = weiter vorne im Slider.</small>
                </p>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo $edit ? '✅ Speichern' : '➕ Hinzufügen'; ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="?page=wcr-kino" class="button">❌ Abbrechen</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- ── Film-Liste ── -->
        <h2>🎬 Alle Filme</h2>
        <?php if (empty($films)): ?>
            <p>Noch keine Filme hinzugefügt.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Cover</th>
                        <th>Titel</th>
                        <th>Spieltag</th>
                        <th>Sort</th>
                        <th style="width:150px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($films as $film): ?>
                        <?php
                        $is_past = strtotime($film->date) < strtotime('today');
                        $row_style = $is_past ? 'opacity:0.4;' : '';
                        ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><?php echo $film->id; ?></td>
                            <td>
                                <?php if ($film->cover_url): ?>
                                    <img src="<?php echo esc_url($film->cover_url); ?>" 
                                         style="width:50px;height:auto;border:1px solid #ddd;">
                                <?php else: ?>
                                    <span style="color:#999;">❌ Kein Cover</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($film->title); ?></strong></td>
                            <td>
                                <?php echo esc_html($film->date); ?>
                                <?php if ($is_past): ?>
                                    <span style="color:#d63638;font-weight:bold;"> (gelaufen)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $film->sort_order; ?></td>
                            <td>
                                <a href="?page=wcr-kino&edit=<?php echo $film->id; ?>" class="button button-small">✏️</a>
                                <form method="post" style="display:inline;" 
                                      onsubmit="return confirm('Film wirklich löschen?');">
                                    <?php wp_nonce_field('wcr_kino_action', 'wcr_kino_nonce'); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $film->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ── Menü registrieren ──
add_action('admin_menu', function() {
    add_menu_page(
        'WCR Kino',
        'Kino-Programm',
        'manage_options',
        'wcr-kino',
        'wcr_kino_admin_page',
        'dashicons-tickets-alt',
        25
    );
});

/* ====================================================
   AJAX Endpoints für Backend Control Panel
   ==================================================== */

// ── Liste alle Filme (inkl. vergangene) ──
add_action('wp_ajax_wcr_kino_list', 'wcr_kino_ajax_list');
add_action('wp_ajax_nopriv_wcr_kino_list', 'wcr_kino_ajax_list');
function wcr_kino_ajax_list() {
    if (($_POST['wcr_secret'] ?? '') !== 'WCR_DS_2026') {
        wp_send_json_error('Nicht autorisiert', 403);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wcr_kino';
    $films = $wpdb->get_results("SELECT * FROM $table ORDER BY date DESC, sort_order ASC", ARRAY_A);

    wp_send_json_success($films);
}

// ── Film hinzufügen ──
add_action('wp_ajax_wcr_kino_add', 'wcr_kino_ajax_add');
add_action('wp_ajax_nopriv_wcr_kino_add', 'wcr_kino_ajax_add');
function wcr_kino_ajax_add() {
    if (($_POST['wcr_secret'] ?? '') !== 'WCR_DS_2026') {
        wp_send_json_error('Nicht autorisiert', 403);
    }

    $title      = sanitize_text_field($_POST['title'] ?? '');
    $cover_url  = esc_url_raw($_POST['cover_url'] ?? '');
    $date       = sanitize_text_field($_POST['date'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);

    if (!$title || !$cover_url || !$date) {
        wp_send_json_error('Fehlende Pflichtfelder', 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wcr_kino';
    $wpdb->insert($table, compact('title', 'cover_url', 'date', 'sort_order'));

    wp_send_json_success(['id' => $wpdb->insert_id]);
}

// ── Film löschen ──
add_action('wp_ajax_wcr_kino_delete', 'wcr_kino_ajax_delete');
add_action('wp_ajax_nopriv_wcr_kino_delete', 'wcr_kino_ajax_delete');
function wcr_kino_ajax_delete() {
    if (($_POST['wcr_secret'] ?? '') !== 'WCR_DS_2026') {
        wp_send_json_error('Nicht autorisiert', 403);
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        wp_send_json_error('Ungültige ID', 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wcr_kino';
    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
}
