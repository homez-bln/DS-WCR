<?php
/**
 * ctrl/list.php — Generische Produkt-Liste
 *
 * FIX v6: Ersetzt cable.php / camping.php / ice.php / extra.php
 *         (4 identische Kopien → 1 Datei mit ?t=TABLE)
 *
 * FIX v6: Kein HTTP-Umweg mehr. Liest direkt aus $pdo statt erst
 *         einen cURL-Request an get_tickets.php zu machen.
 *
 * SECURITY v9: Erfordert edit_products Permission + CSRF-Token
 *
 * Aufruf:  /be/ctrl/list.php?t=cable
 *                                ^--- ice | cable | camping | extra
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_products');

$_canPrice = wcr_can('edit_prices');

// ── Whitelist ────────────────────────────────────
const LIST_TABLES = [
    'ice'     => ['label' => 'Eis',     'icon' => '🍦'],
    'cable'   => ['label' => 'Cable',   'icon' => '🏄'],
    'camping' => ['label' => 'Camping', 'icon' => '⛺'],
    'extra'   => ['label' => 'Extra',   'icon' => '🛒️'],
];

$t = trim($_GET['t'] ?? '');
if (!array_key_exists($t, LIST_TABLES)) {
    http_response_code(400);
    exit('<p>Ungültige Tabelle. Erlaubt: ' . implode(', ', array_keys(LIST_TABLES)) . '</p>');
}

$PAGE_TITLE = LIST_TABLES[$t]['label'];
$DB_TABLE   = $t;

// FIX: Direkter DB-Zugriff statt cURL-Roundtrip
$tickets = $pdo->query("SELECT * FROM `{$DB_TABLE}` ORDER BY typ ASC, nummer ASC")->fetchAll();

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
  <h1><?= LIST_TABLES[$t]['icon'] ?> <?= htmlspecialchars($PAGE_TITLE) ?></h1>
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

<script>
const TABLE = '<?= $DB_TABLE ?>';

// ── CSRF-Token aus <body data-csrf="..."> lesen ──
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
    
    // ── CSRF-Token mitschicken ──
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
        // FIX v13: Token IMMER aktualisieren (auch bei Fehler!)
        // Grund: Server rotiert Token auch bei Fehler, Frontend muss mitziehen.
        if (d.csrf_token) {
            document.body.dataset.csrf = d.csrf_token;
        }
        
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
