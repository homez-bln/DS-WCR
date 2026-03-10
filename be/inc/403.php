<?php
/**
 * inc/403.php — Keine Berechtigung
 */
require_once __DIR__ . '/menu.php';
?>
<div style="text-align:center; padding:80px 20px;">
  <div style="font-size:56px; margin-bottom:16px;">🚫</div>
  <h2 style="font-size:22px; font-weight:700; color:#1d1d1f; margin:0 0 10px;">Kein Zugriff</h2>
  <p style="color:#86868b; font-size:15px;">Deine Rolle <strong><?= wcr_role_badge() ?></strong> hat keine Berechtigung für diese Seite.</p>
  <a href="/be/index.php" style="display:inline-block; margin-top:24px; padding:10px 24px; background:#0071e3; color:#fff; border-radius:8px; text-decoration:none; font-weight:600;">← Zurück zum Dashboard</a>
</div>
