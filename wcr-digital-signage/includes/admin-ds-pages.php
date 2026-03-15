<?php
/**
 * includes/admin-ds-pages.php
 * WP-Admin Menü: "DS-Seiten"
 * Zeigt alle WP-Seiten + DS-Regel + Auto-Status + Override.
 * Speichern per POST mit Nonce + capability check.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
    add_menu_page(
        'DS-Seiten',
        'DS-Seiten',
        'manage_options',
        'wcr-ds-pages',
        'wcr_ds_admin_page',
        'dashicons-visibility',
        30
    );
} );

// ── Save Handler ──────────────────────────────────────────
add_action( 'admin_init', function() {
    if (
        ! isset( $_POST['wcr_ds_save'] ) ||
        ! current_user_can( 'manage_options' )
    ) return;

    check_admin_referer( 'wcr_ds_save_rules', 'wcr_ds_nonce' );

    $page_id  = (int) ( $_POST['wcr_ds_page_id'] ?? 0 );
    if ( $page_id <= 0 ) return;

    $rule = [
        'override' => sanitize_text_field( $_POST['wcr_ds_override'] ?? 'auto' ),
        'tables'   => isset( $_POST['wcr_ds_tables'] ) ? array_map( 'sanitize_text_field', (array) $_POST['wcr_ds_tables'] ) : [],
        'ids'      => sanitize_text_field( $_POST['wcr_ds_ids'] ?? '' ),
        'mode'     => sanitize_text_field( $_POST['wcr_ds_mode'] ?? 'any' ),
    ];

    wcr_ds_save_rule( $page_id, $rule );

    wp_redirect( add_query_arg( [ 'page' => 'wcr-ds-pages', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
} );

// ── Admin-Seite rendern ───────────────────────────────────
function wcr_ds_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    // Alle veröffentlichten Seiten
    $pages = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
    $edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;

    ?>
    <div class="wrap">
    <h1>📺 DS-Seiten Aktivierung</h1>

    <?php if ( $saved ): ?>
    <div class="notice notice-success is-dismissible"><p>✅ Regel gespeichert.</p></div>
    <?php endif; ?>

    <?php if ( $edit_id > 0 ) :
        $ep = get_post( $edit_id );
        if ( $ep && $ep->post_type === 'page' ) :
            $er = wcr_ds_get_rule( $edit_id ) ?? [ 'override'=>'auto','tables'=>[],'ids'=>'','mode'=>'any' ];
            $ev = wcr_ds_eval_rule( $er );
    ?>
    <div style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin-bottom:20px;max-width:700px;border-radius:4px;">
      <h2 style="margin-top:0;">Regel bearbeiten: <em><?= esc_html( $ep->post_title ) ?></em></h2>
      <form method="post">
        <?php wp_nonce_field( 'wcr_ds_save_rules', 'wcr_ds_nonce' ); ?>
        <input type="hidden" name="wcr_ds_save"    value="1">
        <input type="hidden" name="wcr_ds_page_id" value="<?= $edit_id ?>">

        <table class="form-table" style="max-width:600px;">
          <tr>
            <th>Override</th>
            <td>
              <select name="wcr_ds_override">
                <?php foreach ( ['auto'=>'Auto (DB-Check)','force_on'=>'Force ON (immer aktiv)','force_off'=>'Force OFF (immer leer)'] as $v => $l ): ?>
                <option value="<?= $v ?>" <?= selected($er['override'],$v,false) ?>><?= esc_html($l) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>Tabellen</th>
            <td>
              <?php foreach ( WCR_DS_TABLES_WHITELIST as $tbl ): ?>
              <label style="margin-right:12px;">
                <input type="checkbox" name="wcr_ds_tables[]" value="<?= $tbl ?>"
                  <?= in_array($tbl, (array)($er['tables']??[]), true) ? 'checked' : '' ?>>
                <?= esc_html($tbl) ?>
              </label>
              <?php endforeach; ?>
              <p class="description">Leer = alle 6 Tabellen werden geprüft.</p>
            </td>
          </tr>
          <tr>
            <th>IDs (nummer)</th>
            <td>
              <input type="text" name="wcr_ds_ids" value="<?= esc_attr($er['ids']??'') ?>"
                     placeholder="z.B. 3010,3089,3162" class="regular-text">
              <p class="description">Komma-getrennte Produkt-IDs (Spalte <code>nummer</code>). Leer = alle Produkte.</p>
            </td>
          </tr>
          <tr>
            <th>Mode</th>
            <td>
              <select name="wcr_ds_mode">
                <option value="any" <?= selected($er['mode']??'any','any',false) ?>>any – mind. 1 ID aktiv</option>
                <option value="all" <?= selected($er['mode']??'any','all',false) ?>>all – alle IDs müssen aktiv sein</option>
              </select>
            </td>
          </tr>
        </table>

        <p style="margin-top:8px;">
          <strong>Auto-Status jetzt:</strong>
          <?= $ev['active'] ? '✅ Aktiv' : '⛔ Inaktiv' ?>
          <code style="margin-left:8px;font-size:12px;"><?= esc_html($ev['reason']) ?></code>
          <?php if ( ! $ev['db_ok'] ): ?>
            <span style="color:#c0392b;"> ⚠️ DB-Verbindung fehlgeschlagen (fail-open)</span>
          <?php endif; ?>
        </p>

        <p>
          <a href="<?= esc_url(add_query_arg(['page'=>'wcr-ds-pages'],admin_url('admin.php'))) ?>" class="button">Abbrechen</a>
          &nbsp;
          <button type="submit" class="button button-primary">Speichern</button>
        </p>
      </form>
    </div>
    <?php endif; endif; ?>

    <table class="widefat striped" style="max-width:1100px;">
      <thead>
        <tr>
          <th>Seite</th>
          <th>Override</th>
          <th>Tabellen</th>
          <th>IDs</th>
          <th>Mode</th>
          <th>Auto-Status</th>
          <th>Effektiv</th>
          <th>Aktion</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $pages as $p ) :
          $rule  = wcr_ds_get_rule( $p->ID ) ?? [ 'override'=>'auto','tables'=>[],'ids'=>'','mode'=>'any' ];
          $eval  = wcr_ds_eval_rule( $rule );
          $ov    = $rule['override'] ?? 'auto';
          $tbls  = implode( ', ', (array)($rule['tables']??[]) ) ?: '—';
          $ids   = $rule['ids'] ?: '—';
          $mode  = $rule['mode'] ?? 'any';
          $auto  = $eval['active'];
          $effektiv = ( $ov === 'force_on' ) ? true : ( ( $ov === 'force_off' ) ? false : $auto );
          $edit_url = add_query_arg( ['page'=>'wcr-ds-pages','edit'=>$p->ID], admin_url('admin.php') );
      ?>
      <tr>
        <td>
          <strong><?= esc_html($p->post_title) ?></strong><br>
          <span style="color:#666;font-size:12px;">/<?= esc_html($p->post_name) ?>/</span>
          &nbsp;<a href="<?= get_permalink($p->ID) ?>" target="_blank" style="font-size:11px;">Ansehen ↗</a>
        </td>
        <td><?= esc_html($ov) ?></td>
        <td style="font-size:12px;"><?= esc_html($tbls) ?></td>
        <td style="font-size:12px;"><?= esc_html($ids) ?></td>
        <td><?= esc_html($mode) ?></td>
        <td>
          <?= $auto ? '✅' : '⛔' ?>
          <span style="font-size:11px;color:#666;"><?= esc_html($eval['reason']) ?></span>
          <?php if ( ! $eval['db_ok'] ): ?>
            <span title="DB-Fehler" style="color:#e74c3c;">⚠️</span>
          <?php endif; ?>
        </td>
        <td><?= $effektiv ? '✅ Aktiv' : '⛔ Inaktiv' ?></td>
        <td><a href="<?= esc_url($edit_url) ?>" class="button button-small">Bearbeiten</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}
