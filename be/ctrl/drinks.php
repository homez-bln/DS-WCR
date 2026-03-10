<?php
/**
 * ctrl/drinks.php
 * FIX v6: Direkter DB-Zugriff statt cURL → get_tickets.php → DB.
 *         (War vorher 2 DB-Verbindungen + 1 HTTP-Request pro Seitenaufruf)
 * SECURITY v9: Erfordert edit_products Permission + CSRF-Token im Body
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_products');

$_canPrice = wcr_can('edit_prices');

$DB_TABLE   = 'drinks';
$PAGE_TITLE = 'Getränke';

// FIX: Direkter DB-Zugriff
$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

$grouped = [];
foreach ($tickets as $t) {
    $typ = trim((string)($t['typ'] ?? '')) ?: 'Sonstige';
    $grouped[$typ][] = $t;
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
</head>
<body class="bo" data-csrf="<?= wcr_csrf_attr() ?>">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🍺 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <div class="view-switcher">
    <button onclick="setView('list')"    id="btn-list"    class="active">Liste</button>
    <button onclick="setView('gallery')" id="btn-gallery">Galerie</button>
  </div>
</div>

<?php if (empty($tickets)): ?>
  <p style="padding:20px;color:#86868b;">Keine Einträge gefunden.</p>
<?php else: ?>

<div id="items-container" class="view-list">

  <div class="item-header">
    <div class="item-cell cell-active">Aktiv</div>
    <div class="item-cell cell-nr">Nr.</div>
    <div class="item-cell cell-product">Produkt</div>
    <div class="item-cell cell-amount">Menge</div>
    <div class="item-cell cell-price">Preis</div>
    <div class="item-cell cell-type">Typ</div>
    <div class="item-cell cell-status"></div>
  </div>

  <?php foreach ($grouped as $typ => $items):
      $gKey = 'group_drinks_' . preg_replace('/[^a-zA-Z0-9]/', '_', $typ);
  ?>
  <div class="group-header" data-group="<?= htmlspecialchars($gKey) ?>" onclick="toggleGroup(this)">
    <span class="group-label"><?= htmlspecialchars($typ) ?></span>
    <span class="group-count">(<?= count($items) ?>)</span>
    <span class="group-chevron">▼</span>
  </div>
  <div class="group-body" data-group-body="<?= htmlspecialchars($gKey) ?>">
    <?php foreach ($items as $t):
        $active    = (bool)($t['stock'] ?? 0);
        $cardClass = $active ? '' : 'card-off';
    ?>
    <div class="item-card <?= $cardClass ?>"
         id="card-<?= (int)$t['nummer'] ?>"
         onclick="handleCardClick(event,'<?= (int)$t['nummer'] ?>')">

      <div class="card-image-container">
        <?php if (!empty($t['bild_url'])): ?>
          <img src="<?= htmlspecialchars($t['bild_url']) ?>" class="product-img" loading="lazy">
        <?php else: ?>
          <span class="card-image-placeholder">📷</span>
        <?php endif; ?>
        <?php if (wcr_is_admin()): ?>
        <button class="card-img-upload-btn" title="Bild hochladen"
          onclick="event.stopPropagation(); openImgModal(
            'drinks',
            <?= (int)$t['nummer'] ?>,
            '<?= htmlspecialchars(addslashes((string)($t['produkt'] ?? ''))) ?>',
            '<?= htmlspecialchars($t['bild_url'] ?? '') ?>'
          )">📷</button>
        <?php endif; ?>
      </div>

      <div class="item-cell cell-active">
        <label class="switch">
          <input type="checkbox" id="cb-<?= (int)$t['nummer'] ?>"
                 <?= $active ? 'checked' : '' ?>
                 onchange="upd(this,'toggle')" data-nr="<?= (int)$t['nummer'] ?>">
          <span class="slider round"></span>
        </label>
      </div>
      <div class="item-cell cell-nr"><?= (int)$t['nummer'] ?></div>
      <div class="item-cell cell-product"><strong><?= htmlspecialchars((string)$t['produkt']) ?></strong></div>
      <div class="item-cell cell-amount"><?= htmlspecialchars((string)($t['menge'] ?? '')) ?></div>
      <?php if ($_canPrice): ?>
      <div class="item-cell cell-price">
        <input type="number" step="0.01"
               value="<?= number_format((float)($t['preis'] ?? 0), 2, '.', '') ?>"
               onchange="upd(this,'price')" data-nr="<?= (int)$t['nummer'] ?>">
      </div>
      <?php else: ?>
      <div class="item-cell cell-price" style="color:#86868b; font-size:13px; padding-left:8px;">
        <?= number_format((float)($t['preis'] ?? 0), 2, ',', '') ?>&nbsp;€
      </div>
      <?php endif; ?>
      <div class="item-cell cell-type"><?= htmlspecialchars((string)($t['typ'] ?? '')) ?></div>
      <div class="item-cell cell-status" id="s-<?= (int)$t['nummer'] ?>"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

</div>
<?php endif; ?>

<script>
const TABLE = 'drinks';
</script>
<script src="/be/js/ctrl-shared.js"></script>
<?php if (wcr_is_admin()) include __DIR__ . '/../inc/img-upload-modal.php'; ?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
