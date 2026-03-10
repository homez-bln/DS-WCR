<?php
/**
 * ctrl/food.php
 * FIX v6: Direkter DB-Zugriff, kein cURL-Roundtrip mehr.
 *         Gruppen-Toggle (OFFEN/GESCHLOSSEN) bleibt erhalten.
 * SECURITY v9: Erfordert edit_products Permission + CSRF-Token
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_products');

$_canPrice = wcr_can('edit_prices');

$DB_TABLE   = 'food';
$PAGE_TITLE = 'Essen';

// FIX: Direkter DB-Zugriff
$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

// Gruppen-Status
try {
    $gruppenStatus = $pdo->query("SELECT typ, aktiv FROM wp_food_gruppen")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $gruppenStatus = [];
}

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
  <style>
    .group-master-switch, .group-header .switch { display: inline-block !important; }
    .group-master-switch { position: relative; z-index: 2; }
    .gruppe-badge {
      flex: 1; text-align: center; font-size: 14px; font-weight: 800;
      letter-spacing: 2px; padding: 5px 16px; border-radius: 20px;
      text-transform: uppercase; transition: background .2s, color .2s;
    }
    .gruppe-on  .gruppe-badge { background: rgba(52,199,89,.15); color: #1a7a30; }
    .gruppe-off .gruppe-badge { background: rgba(255,59,48,.12);  color: #dc2626; }
    .group-header.gruppe-off { opacity: .55; border-left-color: #ff3b30; }
    .group-header.gruppe-off .group-label { text-decoration: line-through; color: #ff3b30 !important; }
    .group-body.gruppe-off .item-card { opacity: .35; filter: grayscale(60%); pointer-events: none; }
  </style>
</head>
<body class="bo" data-csrf="<?= wcr_csrf_attr() ?>">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🍔 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <div class="view-switcher">
    <button onclick="setView('list')"    id="btn-list"    class="active">Liste</button>
    <button onclick="setView('gallery')" id="btn-gallery">Galerie</button>
  </div>
</div>

<?php if (empty($tickets)): ?>
  <p style="padding:20px;color:#86868b;">Keine Einträge gefunden.</p>
<?php else: ?>

<div id="items-container" class="view-list">

  <?php foreach ($grouped as $typ => $items):
      $gKey     = 'group_food_' . preg_replace('/[^a-zA-Z0-9]/', '_', $typ);
      $typLower = strtolower($typ);
      $gruppeAn = (int)($gruppenStatus[$typLower] ?? 1);
      $onOff    = $gruppeAn ? 'gruppe-on' : 'gruppe-off';
  ?>

  <!-- Group header (shared between list + gallery view) -->
  <div class="group-header <?= $onOff ?>"
       data-group="<?= htmlspecialchars($gKey) ?>"
       data-groupkey="<?= htmlspecialchars($gKey) ?>"
       onclick="toggleGroup(this)">
    <label class="switch group-master-switch" onclick="event.stopPropagation()">
      <input type="checkbox" class="group-toggle"
             data-typ="<?= htmlspecialchars($typLower) ?>"
             data-groupkey="<?= htmlspecialchars($gKey) ?>"
             <?= $gruppeAn ? 'checked' : '' ?>
             onchange="updGruppe(this)">
      <span class="slider round"></span>
    </label>
    <span class="group-label"><?= htmlspecialchars($typ) ?></span>
    <span class="gruppe-badge"><?= $gruppeAn ? 'OFFEN' : 'GESCHLOSSEN' ?></span>
    <span class="group-count">(<?= count($items) ?>)</span>
    <span class="group-chevron">▼</span>
  </div>

  <div class="group-body <?= $gruppeAn ? '' : 'gruppe-off' ?>"
       data-group-body="<?= htmlspecialchars($gKey) ?>">

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
            'food',
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
const TABLE = 'food';
</script>
<script src="/be/js/ctrl-shared.js"></script>
<script>
// FIX: updGruppe() muss NACH ctrl-shared.js kommen, da es getCsrfToken() verwendet
function updGruppe(checkbox) {
    const typ      = checkbox.getAttribute('data-typ');
    const groupKey = checkbox.getAttribute('data-groupkey');
    const aktiv    = checkbox.checked ? 1 : 0;

    // Alle Header mit diesem key synchron halten
    document.querySelectorAll('[data-groupkey="' + groupKey + '"]').forEach(el => {
        el.classList.toggle('gruppe-off', !checkbox.checked);
        el.classList.toggle('gruppe-on',   checkbox.checked);
        const cb = el.querySelector('.group-toggle');
        if (cb && cb !== checkbox) cb.checked = checkbox.checked;
        const badge = el.querySelector('.gruppe-badge');
        if (badge) badge.textContent = checkbox.checked ? 'OFFEN' : 'GESCHLOSSEN';
    });
    const body = document.querySelector('[data-group-body="' + groupKey + '"]');
    if (body) body.classList.toggle('gruppe-off', !checkbox.checked);

    // ── CSRF-Token mitschicken ──
    const params = new URLSearchParams();
    params.append('table', 'wp_food_gruppen');
    params.append('nummer', typ);
    params.append('mode', 'gruppe');
    params.append('value', aktiv);
    params.append('csrf_token', getCsrfToken());

    fetch('/be/update_ticket.php', {
        method : 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body   : params.toString()
    })
    .then(r => r.json())
    .then(d => {
        // ── Token-Rotation: Neues Token nach Response speichern ──
        if (d.csrf_token) {
            document.body.dataset.csrf = d.csrf_token;
        }
        if (!d.ok) console.error('Gruppe-Fehler', d);
    })
    .catch(console.error);
}
</script>

<?php if (wcr_is_admin()) include __DIR__ . '/../inc/img-upload-modal.php'; ?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
