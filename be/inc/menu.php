<?php
/**
 * inc/menu.php v10 — Gruppiertes Dropdown-Menü
 * Gruppen: Betrieb | Produkte | Content | Digital Signage | Admin
 */
$_currentScript = basename($_SERVER['PHP_SELF']);
$_currentQuery  = $_SERVER['QUERY_STRING'] ?? '';

if (!function_exists('_wcr_menu_active')) {
    function _wcr_menu_active(string $href, string $cur, string $curQ): bool {
        $hrefFile = basename(strtok($href, '?'));
        if ($cur !== $hrefFile) return false;
        $hrefQ = parse_url($href, PHP_URL_QUERY) ?? '';
        if ($hrefQ === '') return true;
        parse_str($hrefQ, $hP);
        parse_str($curQ, $cP);
        return ($hP['t'] ?? '') === ($cP['t'] ?? '');
    }
}
if (!function_exists('_wcr_group_active')) {
    function _wcr_group_active(array $items, string $cur, string $curQ): bool {
        foreach ($items as $item) {
            if (_wcr_menu_active($item[1], $cur, $curQ)) return true;
        }
        return false;
    }
}

$_menuGroups = [
    [
        'label' => '⏱ Betrieb',
        'icon'  => '⏱',
        'items' => [
            ['Open',      'ctrl/times.php',  'view_times'],
            ['Kino',      'ctrl/kino.php',   'edit_content'],
            ['Media',     'ctrl/media.php',  'view_media'],
        ],
    ],
    [
        'label' => '🛍 Produkte',
        'icon'  => '🛍',
        'items' => [
            ['Getränke',  'ctrl/drinks.php',          'edit_products'],
            ['Essen',     'ctrl/food.php',             'edit_products'],
            ['Eis',       'ctrl/list.php?t=ice',       'edit_products'],
            ['Cable',     'ctrl/list.php?t=cable',     'edit_products'],
            ['Camping',   'ctrl/list.php?t=camping',   'edit_products'],
            ['Extra',     'ctrl/list.php?t=extra',     'edit_products'],
        ],
    ],
    [
        'label' => '🗺 Content',
        'icon'  => '🗺',
        'items' => [
            ['Obstacles',   'ctrl/obstacles.php',  'edit_content'],
        ],
    ],
    [
        'label' => '📺 Digital Signage',
        'icon'  => '📺',
        'items' => [
            ['DS-Seiten',     'ctrl/ds-seiten.php',   'view_ds'],
            ['DS Controller', 'ctrl/ds-settings.php', 'view_ds'],
            ['piSignage',     'ctrl/pisignage.php',   'view_ds'],
        ],
    ],
    [
        'label' => '⚙️ Admin',
        'icon'  => '⚙️',
        'perm'  => 'manage_users',  // ganze Gruppe nur bei dieser Permission
        'items' => [
            ['Benutzer',  'ctrl/users.php',       'manage_users'],
            ['Rechte',    'ctrl/permissions.php', null],  // cernal-only, extra check unten
        ],
    ],
];

require_once __DIR__ . '/design-tokens.php';
?>
<link rel="stylesheet" href="/be/inc/style.css">

<div class="nav-bar">

  <a href="/be/index.php" class="nav-home <?= $_currentScript === 'index.php' ? 'active' : '' ?>">
    🏠 <span>Start</span>
  </a>

  <?php foreach ($_menuGroups as $group):
    // Gruppen-Permission prüfen (optional)
    if (!empty($group['perm']) && !wcr_can($group['perm'])) continue;

    // Prüfen ob mind. 1 Item der Gruppe sichtbar ist
    $visibleItems = array_filter($group['items'], function($item) {
        if (empty($item[2])) return wcr_is_cernal(); // null = cernal-only
        return wcr_can($item[2]);
    });
    if (empty($visibleItems)) continue;

    $groupActive = _wcr_group_active(array_values($visibleItems), $_currentScript, $_currentQuery);
  ?>
  <div class="nav-group <?= $groupActive ? 'active' : '' ?>">
    <button class="nav-group-btn" type="button" aria-haspopup="true">
      <?= $group['label'] ?>
      <svg class="nav-chevron" viewBox="0 0 10 6" width="10" height="6"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
    </button>
    <div class="nav-dropdown">
      <?php foreach ($visibleItems as $item): ?>
        <a href="/be/<?= $item[1] ?>"
           class="nav-drop-item <?= _wcr_menu_active($item[1], $_currentScript, $_currentQuery) ? 'active' : '' ?>">
          <?= htmlspecialchars($item[0]) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="nav-spacer"></div>
  <span class="nav-user"><?= wcr_role_badge() ?></span>
  <a href="/be/logout.php" class="nav-logout">Logout</a>

</div>

<style>
/* ── Nav Bar ──────────────────────────────────────────────── */
.nav-bar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 0 16px;
  height: 52px;
  background: var(--bg-nav, #1a1a2e);
  border-bottom: 1px solid var(--border, #2a2a3e);
  position: sticky;
  top: 0;
  z-index: 999;
  box-shadow: 0 2px 8px rgba(0,0,0,.18);
}
.nav-home {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--nav-text, rgba(255,255,255,.75));
  text-decoration: none;
  white-space: nowrap;
  transition: background .15s, color .15s;
  margin-right: 6px;
}
.nav-home:hover, .nav-home.active {
  background: rgba(255,255,255,.1);
  color: #fff;
}
.nav-spacer { flex: 1; }
.nav-user {
  font-size: 12px;
  color: rgba(255,255,255,.5);
  padding: 0 10px;
  white-space: nowrap;
}
.nav-logout {
  font-size: 12px;
  color: rgba(255,255,255,.5);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 7px;
  border: 1px solid rgba(255,255,255,.15);
  transition: background .15s, color .15s;
  white-space: nowrap;
}
.nav-logout:hover { background: rgba(255,255,255,.1); color: #fff; }

/* ── Gruppe ──────────────────────────────────────────────── */
.nav-group {
  position: relative;
}
.nav-group-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: var(--nav-text, rgba(255,255,255,.75));
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s, color .15s;
  height: 36px;
}
.nav-group-btn:hover,
.nav-group.open .nav-group-btn,
.nav-group.active .nav-group-btn {
  background: rgba(255,255,255,.1);
  color: #fff;
}
.nav-group.active .nav-group-btn {
  color: var(--primary, #019ee3);
}
.nav-chevron {
  transition: transform .2s;
  opacity: .6;
}
.nav-group.open .nav-chevron {
  transform: rotate(180deg);
  opacity: 1;
}

/* ── Dropdown ─────────────────────────────────────────────── */
.nav-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 160px;
  background: var(--bg-card, #1e2235);
  border: 1px solid var(--border, #2a2a3e);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,.35);
  padding: 6px;
  z-index: 1000;
  animation: dropIn .15s ease;
}
@keyframes dropIn {
  from { opacity:0; transform:translateY(-6px); }
  to   { opacity:1; transform:translateY(0); }
}
.nav-group.open .nav-dropdown { display: block; }
.nav-drop-item {
  display: block;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 500;
  color: var(--nav-text, rgba(255,255,255,.75));
  text-decoration: none;
  border-radius: 7px;
  transition: background .12s, color .12s;
  white-space: nowrap;
}
.nav-drop-item:hover {
  background: rgba(255,255,255,.08);
  color: #fff;
}
.nav-drop-item.active {
  background: rgba(1,158,227,.18);
  color: var(--primary, #019ee3);
  font-weight: 700;
}

/* ── Aktive Gruppe hat Unterstrich-Indikator ────────────────────── */
.nav-group.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 12px;
  right: 12px;
  height: 2px;
  background: var(--primary, #019ee3);
  border-radius: 1px;
}

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 768px) {
  .nav-bar { padding: 0 10px; gap: 0; }
  .nav-group-btn { padding: 6px 8px; font-size: 12px; }
  .nav-home span { display: none; }
}
</style>

<script>
(function(){
  // Click-to-toggle Dropdowns
  document.querySelectorAll('.nav-group').forEach(function(group){
    const btn = group.querySelector('.nav-group-btn');
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      const isOpen = group.classList.contains('open');
      // alle schließen
      document.querySelectorAll('.nav-group.open').forEach(function(g){g.classList.remove('open');});
      if(!isOpen) group.classList.add('open');
    });
  });
  // Click-outside schließt alle
  document.addEventListener('click', function(){
    document.querySelectorAll('.nav-group.open').forEach(function(g){g.classList.remove('open');});
  });
  // Dropdown selbst stoppt propagation damit click-outside nicht sofort schliesst
  document.querySelectorAll('.nav-dropdown').forEach(function(d){
    d.addEventListener('click', function(e){e.stopPropagation();});
  });
})();
</script>
