<?php
/**
 * inc/debug.php — Debug-Panel (nur für cernal)
 * v7: Zeigt Session, DB-Queries, Request-Info, PHP-Fehler
 * Wird per include am Ende jeder Seite eingebunden.
 * Ist nur sichtbar wenn wcr_is_cernal() === true.
 */
if (!function_exists('wcr_is_cernal') || !wcr_is_cernal()) return;

// Alle bisherigen DB-Queries einsammeln (werden von wcr_pdo_debug() getracked)
global $WCR_DEBUG_QUERIES;
$queries   = $WCR_DEBUG_QUERIES ?? [];
$queryCount = count($queries);
$memUsage  = round(memory_get_usage(true) / 1024 / 1024, 2);
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
$loadTime  = isset($GLOBALS['WCR_PAGE_START']) ? round((microtime(true) - $GLOBALS['WCR_PAGE_START']) * 1000, 1) : '?';
$role      = wcr_role();
$userId    = wcr_user_id();
?>
<div id="wcr-debug" class="wcr-debug-panel">
  <div class="dbg-bar" onclick="document.getElementById('wcr-debug').classList.toggle('open')">
    <span>🔧 DEBUG</span>
    <span class="dbg-badges">
      <span class="dbg-badge purple"><?= $loadTime ?>ms</span>
      <span class="dbg-badge blue"><?= $memUsage ?>MB / <?= $memPeak ?>MB peak</span>
      <span class="dbg-badge orange"><?= $queryCount ?> queries</span>
    </span>
    <span class="dbg-chevron">▲</span>
  </div>

  <div class="dbg-body">
    <div class="dbg-grid">

      <!-- SESSION -->
      <div class="dbg-section">
        <div class="dbg-section-title">👤 Session</div>
        <table class="dbg-table">
          <tr><td>User-ID</td><td><code><?= $userId ?></code></td></tr>
          <tr><td>Rolle</td><td><?= wcr_role_badge() ?></td></tr>
          <tr><td>Last seen</td><td><code><?= date('H:i:s', $_SESSION['be_last_seen'] ?? 0) ?></code></td></tr>
          <tr><td>Session-ID</td><td><code style="font-size:10px"><?= substr(session_id(), 0, 20) ?>…</code></td></tr>
        </table>
      </div>

      <!-- REQUEST -->
      <div class="dbg-section">
        <div class="dbg-section-title">🌐 Request</div>
        <table class="dbg-table">
          <tr><td>Method</td><td><code><?= $_SERVER['REQUEST_METHOD'] ?? '?' ?></code></td></tr>
          <tr><td>URI</td><td><code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></code></td></tr>
          <tr><td>IP</td><td><code><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '') ?></code></td></tr>
          <tr><td>PHP</td><td><code><?= PHP_VERSION ?></code></td></tr>
        </table>
      </div>

      <!-- PERMISSIONS -->
      <div class="dbg-section">
        <div class="dbg-section-title">🔐 Berechtigungen</div>
        <table class="dbg-table">
          <?php foreach (WCR_PERMISSIONS as $action => $roles): ?>
          <tr>
            <td><?= htmlspecialchars($action) ?></td>
            <td>
              <?php if (wcr_can($action)): ?>
                <span style="color:#34c759; font-weight:700;">✓ Ja</span>
              <?php else: ?>
                <span style="color:#ff3b30;">✗ Nein</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <!-- DB QUERIES -->
      <div class="dbg-section dbg-section-wide">
        <div class="dbg-section-title">🗄 DB Queries (<?= $queryCount ?>)</div>
        <?php if (empty($queries)): ?>
          <p style="color:#86868b; font-size:12px; margin:8px 0">Keine Queries getrackt. Nutze <code>wcr_pdo()</code> statt <code>$pdo</code> direkt für automatisches Tracking.</p>
        <?php else: ?>
          <div class="dbg-queries">
            <?php foreach ($queries as $i => $q): ?>
              <div class="dbg-query">
                <span class="dbg-q-nr"><?= $i + 1 ?></span>
                <span class="dbg-q-time"><?= $q['ms'] ?>ms</span>
                <code class="dbg-q-sql"><?= htmlspecialchars($q['sql']) ?></code>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- POST DATA (nur wenn vorhanden) -->
      <?php if (!empty($_POST)): ?>
      <div class="dbg-section dbg-section-wide">
        <div class="dbg-section-title">📤 POST Data</div>
        <pre class="dbg-pre"><?= htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.wcr-debug-panel {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999;
  background: #1a1a2e; color: #e0e0e0; font-family: monospace;
  box-shadow: 0 -4px 24px rgba(0,0,0,.4);
  border-top: 2px solid #7c3aed;
  font-size: 12px;
  max-height: 40px;
  overflow: hidden;
  transition: max-height .3s ease;
}
.wcr-debug-panel.open { max-height: 420px; overflow-y: auto; }
.dbg-bar {
  display: flex; align-items: center; gap: 12px; padding: 8px 16px;
  cursor: pointer; user-select: none; background: #16213e;
  border-bottom: 1px solid #7c3aed44;
  position: sticky; top: 0; z-index: 1;
}
.dbg-bar span:first-child { font-weight: 700; color: #a78bfa; letter-spacing: 1px; font-size: 11px; }
.dbg-badges { display: flex; gap: 6px; flex: 1; }
.dbg-badge { padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.dbg-badge.purple { background: #7c3aed22; color: #a78bfa; border: 1px solid #7c3aed44; }
.dbg-badge.blue   { background: #0071e322; color: #60a5fa; border: 1px solid #0071e344; }
.dbg-badge.orange { background: #f59e0b22; color: #fbbf24; border: 1px solid #f59e0b44; }
.dbg-chevron { color: #a78bfa; font-size: 10px; transition: transform .3s; }
.wcr-debug-panel.open .dbg-chevron { transform: rotate(180deg); }
.dbg-body { padding: 16px; }
.dbg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.dbg-section { background: #0f3460; border-radius: 8px; padding: 12px; }
.dbg-section-wide { grid-column: 1 / -1; }
.dbg-section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #a78bfa; margin-bottom: 8px; }
.dbg-table { width: 100%; border-collapse: collapse; }
.dbg-table td { padding: 3px 6px; font-size: 12px; border-bottom: 1px solid #1a1a2e; }
.dbg-table td:first-child { color: #9ca3af; white-space: nowrap; width: 40%; }
.dbg-pre { background: #0a0a1a; padding: 10px; border-radius: 6px; font-size: 11px; overflow-x: auto; max-height: 120px; overflow-y: auto; color: #86efac; margin: 0; }
.dbg-queries { display: flex; flex-direction: column; gap: 4px; max-height: 150px; overflow-y: auto; }
.dbg-query { display: flex; align-items: center; gap: 8px; background: #0a0a1a; padding: 4px 8px; border-radius: 4px; }
.dbg-q-nr   { color: #6b7280; min-width: 18px; text-align: right; }
.dbg-q-time { color: #fbbf24; min-width: 45px; }
.dbg-q-sql  { color: #86efac; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
/* Spacing damit der Debug-Bar die Seite nicht überdeckt */
body { padding-bottom: 50px !important; }
.wcr-debug-panel.open ~ * { margin-bottom: 420px; }
</style>
