<?php
/**
 * ctrl/list.php — Generische Produkt-Liste
 *
 * FIX v6: Ersetzt cable.php / camping.php / ice.php / extra.php
 * SECURITY v9: Erfordert edit_products Permission + CSRF-Token
 * DRAWER v10: + Neuer-Artikel Button/Drawer (create_products Permission)
 *
 * Aufruf: /be/ctrl/list.php?t=ice|cable|camping|extra
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_products');

$_canPrice  = wcr_can('edit_prices');
$_canCreate = wcr_can('create_products');

// ── Whitelist ($array statt const → kein Fatal bei Mehrfach-Include) ──
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

// FIX: Direkter DB-Zugriff statt cURL-Roundtrip
$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

// Typ-Liste für Drawer-Datalist
$typen = array_unique(array_map(fn($r) => trim($r['typ'] ?? ''), $tickets));
sort($typen);

// Gruppieren
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
      <div class="item-cell cell-price" style="color:#86868b; font-size:13px; padding-left:8px;">
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
<div id="drawer-overlay" onclick="closeDrawer()"></div>
<div id="new-item-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-heading">
  <div class="drawer-header">
    <div class="drawer-title">
      <span class="drawer-icon"><?= $PAGE_ICON ?></span>
      <h2 id="drawer-heading">Neuer <?= htmlspecialchars($PAGE_TITLE) ?>-Artikel</h2>
    </div>
    <button class="drawer-close" onclick="closeDrawer()" aria-label="Schließen">✕</button>
  </div>
  <form id="new-item-form" onsubmit="submitNewItem(event)" novalidate>
    <div class="drawer-field">
      <label for="di-produkt">Produktname <span class="field-required">*</span></label>
      <input type="text" id="di-produkt" name="produkt" autocomplete="off" required
             placeholder="Produktname eingeben…">
    </div>
    <div class="drawer-field">
      <label for="di-menge">Menge / Größe</label>
      <input type="text" id="di-menge" name="menge" autocomplete="off" placeholder="Optional">
    </div>
    <div class="drawer-field">
      <label for="di-preis">Preis (€)</label>
      <input type="number" id="di-preis" name="preis" step="0.01" min="0" value="0.00">
    </div>
    <div class="drawer-field">
      <label for="di-typ">Gruppe / Typ</label>
      <input type="text" id="di-typ" name="typ" autocomplete="off"
             list="typ-list-<?= $DB_TABLE ?>" placeholder="Gruppe eingeben…">
      <datalist id="typ-list-<?= $DB_TABLE ?>">
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
const DRAWER_TABLE = '<?= $DB_TABLE ?>';
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
        msg.className = 'drawer-msg err'; msg.style.display = 'block';
        return;
    }
    btn.disabled = true; btn.textContent = '…';
    const payload = {
        produkt,
        menge:  document.getElementById('di-menge').value.trim(),
        preis:  parseFloat(document.getElementById('di-preis').value) || 0,
        typ:    document.getElementById('di-typ').value.trim() || 'Sonstige',
        stock:  document.getElementById('di-stock').checked ? 1 : 0,
        csrf:   document.body.dataset.csrf || ''
    };
    try {
        const res  = await fetch('/be/api/create.php?t=' + DRAWER_TABLE, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
            msg.textContent = '✅ Artikel angelegt (ID ' + data.id + '). Seite wird neu geladen…';
            msg.className = 'drawer-msg ok'; msg.style.display = 'block';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.textContent = '❌ ' + (data.error || 'Unbekannter Fehler');
            msg.className = 'drawer-msg err'; msg.style.display = 'block';
            btn.disabled = false; btn.textContent = 'Artikel anlegen';
        }
    } catch (err) {
        msg.textContent = '❌ Netzwerkfehler: ' + err.message;
        msg.className = 'drawer-msg err'; msg.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Artikel anlegen';
    }
}
</script>
<?php endif; ?>

<script>
const TABLE = '<?= $DB_TABLE ?>';

function getCsrfToken() {
    return document.body.getAttribute('data-csrf') || '';
}

function toggleGroup(h) {
    const key  = h.dataset.group;
    const body = document.querySelector('[data-group-body="' + key + '"]');
    if (!body) return;
    const collapsed = body.classList.toggle('collapsed');
    h.classList.toggle('collapsed', collapsed);
    localStorage.setItem(key, collapsed ? '1' : '0');
}

function setView(view) {
    const c = document.getElementById('items-container');
    if (view === 'gallery') {
        c.classList.replace('view-list','view-gallery');
        document.getElementById('btn-list').classList.remove('active');
        document.getElementById('btn-gallery').classList.add('active');
    } else {
        c.classList.replace('view-gallery','view-list');
        document.getElementById('btn-gallery').classList.remove('active');
        document.getElementById('btn-list').classList.add('active');
    }
    localStorage.setItem('viewPref_<?= $DB_TABLE ?>', view);
}

function handleCardClick(e, nr) {
    const c = document.getElementById('items-container');
    if (!c.classList.contains('view-gallery')) return;
    if (e.target.tagName === 'INPUT') return;
    const cb = document.getElementById('cb-' + nr);
    if (cb) { cb.checked = !cb.checked; upd(cb, 'toggle'); }
}

function upd(el, mode) {
    const nr  = el.getAttribute('data-nr');
    const val = mode === 'toggle' ? (el.checked ? '1' : '0') : el.value;
    if (mode === 'toggle') {
        const card = document.getElementById('card-' + nr);
        if (card) card.classList.toggle('card-off', !el.checked);
    }
    const s = document.getElementById('s-' + nr);
    s.textContent = '…'; s.className = 'status-msg visible';
    
    const params = new URLSearchParams();
    params.append('table', TABLE);
    params.append('nummer', nr);
    params.append('mode', mode);
    params.append('value', val);
    params.append('csrf_token', getCsrfToken());
    
    fetch('/be/update_ticket.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (d.csrf_token) document.body.dataset.csrf = d.csrf_token;
        if (d.ok) {
            s.textContent = 'OK'; s.className = 'status-msg visible success';
            setTimeout(() => { s.textContent = ''; s.className = 'status-msg'; }, 1500);
        } else {
            s.textContent = 'Err'; s.className = 'status-msg visible error';
            console.error('Update-Fehler:', d.error);
        }
    })
    .catch(err => { 
        s.textContent = 'Err'; s.className = 'status-msg visible error';
        console.error('Fetch-Fehler:', err);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.group-header').forEach(h => {
        if (localStorage.getItem(h.dataset.group) === '1') {
            h.classList.add('collapsed');
            const b = document.querySelector('[data-group-body="' + h.dataset.group + '"]');
            if (b) b.classList.add('collapsed');
        }
    });
    const pref = localStorage.getItem('viewPref_<?= $DB_TABLE ?>');
    if (pref === 'gallery') setView('gallery');
});
</script>

<?php if (wcr_is_admin()) include __DIR__ . '/../inc/img-upload-modal.php'; ?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
