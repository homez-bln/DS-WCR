<?php
/**
 * ctrl/list.php — Generische Produkt-Liste
 * MODAL v11: Drawer → FAB + Modal, JS-Duplikate entfernt (→ ctrl-shared.js)
 * Aufruf: /be/ctrl/list.php?t=ice|cable|camping|extra
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

wcr_require('edit_products');

$_canPrice  = wcr_can('edit_prices');
$_canCreate = wcr_can('create_products');

$LIST_TABLES = [
    'ice'     => ['label' => 'Eis',     'icon' => '🍦'],
    'cable'   => ['label' => 'Cable',   'icon' => '🏄'],
    'camping' => ['label' => 'Camping', 'icon' => '⛺'],
    'extra'   => ['label' => 'Extra',   'icon' => '🛒️'],
];

$t = trim($_GET['t'] ?? '');
if (!array_key_exists($t, $LIST_TABLES)) {
    http_response_code(400);
    exit('<p>Ungültige Tabelle. Erlaubt: ' . implode(', ', array_keys($LIST_TABLES)) . '</p>');
}

$PAGE_TITLE = $LIST_TABLES[$t]['label'];
$PAGE_ICON  = $LIST_TABLES[$t]['icon'];
$DB_TABLE   = $t;

$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

$typen = array_unique(array_map(fn($r) => trim($r['typ'] ?? ''), $tickets));
sort($typen);

$grouped = [];
foreach ($tickets as $row) {
    $typ = trim((string)($row['typ'] ?? '')) ?: 'Sonstige';
    $grouped[$typ][] = $row;
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
  <h1><?= $PAGE_ICON ?> <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <div class="view-switcher">
    <button onclick="setView('list')"    id="btn-list"    class="active">Liste</button>
    <button onclick="setView('gallery')" id="btn-gallery">Galerie</button>
  </div>
</div>

<?php if (empty($tickets)): ?>
  <p style="padding:20px;color:#86868b;">Keine Einträge in Tabelle <code><?= htmlspecialchars($DB_TABLE) ?></code> gefunden.</p>
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
      $gKey = 'group_' . $DB_TABLE . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $typ);
  ?>
  <div class="group-header" data-group="<?= htmlspecialchars($gKey) ?>" onclick="toggleGroup(this)">
    <span class="group-label"><?= htmlspecialchars($typ) ?></span>
    <span class="group-count">(<?= count($items) ?>)</span>
    <span class="group-chevron">▼</span>
  </div>
  <div class="group-body" data-group-body="<?= htmlspecialchars($gKey) ?>">
    <?php foreach ($items as $row):
        $active    = (bool)($row['stock'] ?? 0);
        $cardClass = $active ? '' : 'card-off';
    ?>
    <div class="item-card <?= $cardClass ?>"
         id="card-<?= (int)$row['nummer'] ?>"
         onclick="handleCardClick(event,'<?= (int)$row['nummer'] ?>')">

      <div class="card-image-container">
        <?php if (!empty($row['bild_url'])): ?>
          <img src="<?= htmlspecialchars($row['bild_url']) ?>" class="product-img" loading="lazy">
        <?php else: ?>
          <span class="card-image-placeholder">📷</span>
        <?php endif; ?>
        <?php if (wcr_is_admin()): ?>
        <button class="card-img-upload-btn" title="Bild hochladen"
          onclick="event.stopPropagation(); openImgModal(
            '<?= $DB_TABLE ?>',
            <?= (int)$row['nummer'] ?>,
            '<?= htmlspecialchars(addslashes((string)($row['produkt'] ?? ''))) ?>',
            '<?= htmlspecialchars($row['bild_url'] ?? '') ?>'
          )">📷</button>
        <?php endif; ?>
      </div>

      <div class="item-cell cell-active">
        <label class="switch">
          <input type="checkbox" id="cb-<?= (int)$row['nummer'] ?>"
                 <?= $active ? 'checked' : '' ?>
                 onchange="upd(this,'toggle')"
                 data-nr="<?= (int)$row['nummer'] ?>">
          <span class="slider round"></span>
        </label>
      </div>
      <div class="item-cell cell-nr"><?= (int)$row['nummer'] ?></div>
      <div class="item-cell cell-product"><strong><?= htmlspecialchars((string)$row['produkt']) ?></strong></div>
      <div class="item-cell cell-amount"><?= htmlspecialchars((string)($row['menge'] ?? '')) ?></div>
      <?php if ($_canPrice): ?>
      <div class="item-cell cell-price">
        <input type="number" step="0.01"
               value="<?= number_format((float)($row['preis'] ?? 0), 2, '.', '') ?>"
               onchange="upd(this,'price')"
               data-nr="<?= (int)$row['nummer'] ?>">
      </div>
      <?php else: ?>
      <div class="item-cell cell-price" style="color:#86868b;font-size:13px;padding-left:8px;">
        <?= number_format((float)($row['preis'] ?? 0), 2, ',', '') ?>&nbsp;€
      </div>
      <?php endif; ?>
      <div class="item-cell cell-type"><?= htmlspecialchars((string)($row['typ'] ?? '')) ?></div>
      <div class="item-cell cell-status" id="s-<?= (int)$row['nummer'] ?>"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

</div><!-- /items-container -->
<?php endif; ?>

<?php if ($_canCreate): ?>
<!-- ── FAB ── -->
<button id="fab-new-item" onclick="niOpen()" title="Neuer <?= htmlspecialchars($PAGE_TITLE) ?>-Artikel">＋</button>

<!-- ── Overlay ── -->
<div id="ni-overlay" onclick="niClose()"></div>

<!-- ── Modal ── -->
<div id="new-item-modal" role="dialog" aria-modal="true" aria-labelledby="ni-heading">
  <div class="ni-header">
    <div class="ni-title">
      <span class="ni-icon"><?= $PAGE_ICON ?></span>
      <span id="ni-heading">Neuer <?= htmlspecialchars($PAGE_TITLE) ?>-Artikel</span>
    </div>
    <button class="ni-close" onclick="niClose()" aria-label="Schließen">✕</button>
  </div>
  <form class="ni-body" id="ni-form" onsubmit="niSubmit(event)" novalidate>
    <div class="ni-field">
      <label for="ni-produkt">Produktname *</label>
      <input type="text" id="ni-produkt" name="produkt" autocomplete="off" required
             placeholder="Produktname eingeben …">
    </div>
    <div class="ni-row2">
      <div class="ni-field">
        <label for="ni-menge">Menge</label>
        <input type="text" id="ni-menge" name="menge" autocomplete="off" placeholder="Optional">
      </div>
      <div class="ni-field">
        <label for="ni-preis">Preis (€)</label>
        <input type="number" id="ni-preis" name="preis" step="0.01" min="0" value="0.00">
      </div>
    </div>
    <div class="ni-field">
      <label for="ni-typ">Gruppe</label>
      <input type="text" id="ni-typ" name="typ" autocomplete="off"
             list="ni-typ-list" placeholder="Gruppe eingeben …">
      <datalist id="ni-typ-list">
        <?php foreach ($typen as $tv): ?>
        <option value="<?= htmlspecialchars($tv) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="ni-toggle-row">
      <span class="ni-toggle-label">Sofort aktiv</span>
      <label class="switch">
        <input type="checkbox" id="ni-stock" name="stock" checked>
        <span class="slider round"></span>
      </label>
    </div>
    <div id="ni-msg" class="ni-msg"></div>
  </form>
  <div class="ni-footer">
    <button type="button" class="btn-secondary" onclick="niClose()">Abbrechen</button>
    <button type="submit" form="ni-form" class="btn-upload" id="ni-submit">Anlegen</button>
  </div>
</div>

<script>
const NI_TABLE = '<?= $DB_TABLE ?>';
function niOpen()  {
    document.getElementById('ni-overlay').classList.add('open');
    document.getElementById('new-item-modal').classList.add('open');
    document.getElementById('ni-form').reset();
    document.getElementById('ni-stock').checked = true;
    const m = document.getElementById('ni-msg');
    m.className = 'ni-msg'; m.textContent = '';
    setTimeout(() => document.getElementById('ni-produkt').focus(), 80);
}
function niClose() {
    document.getElementById('ni-overlay').classList.remove('open');
    document.getElementById('new-item-modal').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') niClose(); });

async function niSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('ni-submit');
    const msg = document.getElementById('ni-msg');
    const produkt = document.getElementById('ni-produkt').value.trim();
    if (!produkt) {
        msg.textContent = 'Produktname ist Pflicht.';
        msg.className = 'ni-msg err';
        return;
    }
    btn.disabled = true; btn.textContent = '…';
    const payload = {
        produkt,
        menge:  document.getElementById('ni-menge').value.trim(),
        preis:  parseFloat(document.getElementById('ni-preis').value) || 0,
        typ:    document.getElementById('ni-typ').value.trim() || 'Sonstige',
        stock:  document.getElementById('ni-stock').checked ? 1 : 0,
        csrf:   document.body.dataset.csrf || ''
    };
    try {
        const res  = await fetch('/be/api/create.php?t=' + NI_TABLE, {
            method : 'POST',
            headers: {'Content-Type': 'application/json'},
            body   : JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
            msg.textContent = '✅ Angelegt (ID ' + data.id + ') – Seite lädt neu …';
            msg.className = 'ni-msg ok';
            setTimeout(() => location.reload(), 1100);
        } else {
            msg.textContent = '❌ ' + (data.error || 'Fehler');
            msg.className = 'ni-msg err';
            btn.disabled = false; btn.textContent = 'Anlegen';
        }
    } catch (err) {
        msg.textContent = '❌ Netzwerkfehler: ' + err.message;
        msg.className = 'ni-msg err';
        btn.disabled = false; btn.textContent = 'Anlegen';
    }
}
</script>
<?php endif; ?>

<script>const TABLE = '<?= $DB_TABLE ?>';</script>
<script src="/be/js/ctrl-shared.js"></script>
<?php if (wcr_is_admin()) include __DIR__ . '/../inc/img-upload-modal.php'; ?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
