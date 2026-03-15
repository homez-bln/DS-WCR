<?php
/**
 * ctrl/drinks.php
 * FIX v6: Direkter DB-Zugriff statt cURL → get_tickets.php → DB.
 * SECURITY v9: Erfordert edit_products Permission + CSRF-Token im Body
 * DRAWER v10: + Neuer-Artikel Button/Drawer (create_products Permission)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_products');

$_canPrice  = wcr_can('edit_prices');
$_canCreate = wcr_can('create_products');

$DB_TABLE   = 'drinks';
$PAGE_TITLE = 'Getränke';

// FIX: Direkter DB-Zugriff
$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

// Typ-Liste für Drawer-Dropdown
$typen = array_unique(array_map(fn($r) => trim($r['typ'] ?? ''), $tickets));
sort($typen);

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
  <div style="display:flex;gap:10px;align-items:center;">
    <?php if ($_canCreate): ?>
    <button class="btn-new-item" onclick="openDrawer()">＋ Artikel</button>
    <?php endif; ?>
    <div class="view-switcher">
      <button onclick="setView('list')"    id="btn-list"    class="active">Liste</button>
      <button onclick="setView('gallery')" id="btn-gallery">Galerie</button>
    </div>
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

<?php if ($_canCreate): ?>
<!-- ── Overlay ── -->
<div id="drawer-overlay" onclick="closeDrawer()"></div>

<!-- ── Drawer ── -->
<div id="new-item-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-heading">
  <div class="drawer-header">
    <div class="drawer-title">
      <span class="drawer-icon">🍺</span>
      <h2 id="drawer-heading">Neues Getränk</h2>
    </div>
    <button class="drawer-close" onclick="closeDrawer()" aria-label="Schließen">✕</button>
  </div>
  <form id="new-item-form" onsubmit="submitNewItem(event)" novalidate>
    <div class="drawer-field">
      <label for="di-produkt">Produktname <span class="field-required">*</span></label>
      <input type="text" id="di-produkt" name="produkt" autocomplete="off" required
             placeholder="z.B. Coca-Cola 0,33l">
    </div>
    <div class="drawer-field">
      <label for="di-menge">Menge / Größe</label>
      <input type="text" id="di-menge" name="menge" autocomplete="off"
             placeholder="z.B. 0,33l">
      <span class="field-hint">Optional – wird in der Liste angezeigt</span>
    </div>
    <div class="drawer-field">
      <label for="di-preis">Preis (€)</label>
      <input type="number" id="di-preis" name="preis" step="0.01" min="0" value="0.00">
    </div>
    <div class="drawer-field">
      <label for="di-typ">Gruppe / Typ</label>
      <input type="text" id="di-typ" name="typ" autocomplete="off"
             list="typ-list-drinks" placeholder="z.B. Bier, Softdrinks …">
      <datalist id="typ-list-drinks">
        <?php foreach ($typen as $tv): ?>
        <option value="<?= htmlspecialchars($tv) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="drawer-field drawer-field-toggle">
      <span class="toggle-label">Sofort aktiv (Stock an)</span>
      <label class="switch">
        <input type="checkbox" id="di-stock" name="stock" checked>
        <span class="slider round"></span>
      </label>
    </div>
    <div id="drawer-msg" class="drawer-msg" style="display:none"></div>
    <div class="drawer-actions">
      <button type="button" class="btn-secondary" onclick="closeDrawer()">Abbrechen</button>
      <button type="submit" class="btn-upload" id="drawer-submit">Artikel anlegen</button>
    </div>
  </form>
</div>

<script>
function openDrawer() {
    document.getElementById('new-item-drawer').classList.add('open');
    document.getElementById('drawer-overlay').classList.add('open');
    document.getElementById('di-produkt').focus();
    document.getElementById('drawer-msg').style.display = 'none';
    document.getElementById('new-item-form').reset();
    document.getElementById('di-stock').checked = true;
}
function closeDrawer() {
    document.getElementById('new-item-drawer').classList.remove('open');
    document.getElementById('drawer-overlay').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

async function submitNewItem(e) {
    e.preventDefault();
    const btn = document.getElementById('drawer-submit');
    const msg = document.getElementById('drawer-msg');
    const produkt = document.getElementById('di-produkt').value.trim();
    if (!produkt) {
        msg.textContent = 'Produktname ist Pflicht.';
        msg.className = 'drawer-msg err';
        msg.style.display = 'block';
        return;
    }
    btn.disabled = true;
    btn.textContent = '…';
    const payload = {
        produkt,
        menge:   document.getElementById('di-menge').value.trim(),
        preis:   parseFloat(document.getElementById('di-preis').value) || 0,
        typ:     document.getElementById('di-typ').value.trim() || 'Sonstige',
        stock:   document.getElementById('di-stock').checked ? 1 : 0,
        csrf:    document.body.dataset.csrf || ''
    };
    try {
        const res  = await fetch('/be/api/create.php?t=drinks', {
            method : 'POST',
            headers: {'Content-Type': 'application/json'},
            body   : JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
            msg.textContent = '✅ Artikel angelegt (ID ' + data.id + '). Seite wird neu geladen…';
            msg.className = 'drawer-msg ok';
            msg.style.display = 'block';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.textContent = '❌ ' + (data.error || 'Unbekannter Fehler');
            msg.className = 'drawer-msg err';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Artikel anlegen';
        }
    } catch (err) {
        msg.textContent = '❌ Netzwerkfehler: ' + err.message;
        msg.className = 'drawer-msg err';
        msg.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Artikel anlegen';
    }
}
</script>
<?php endif; ?>

<script>
const TABLE = 'drinks';
</script>
<script src="/be/js/ctrl-shared.js"></script>
<?php if (wcr_is_admin()) include __DIR__ . '/../inc/img-upload-modal.php'; ?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
