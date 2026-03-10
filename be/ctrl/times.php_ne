<?php
/**
 * ctrl/times.php — Öffnungszeiten-Verwaltung
 * FIX v6: error_reporting(E_ALL) + display_errors entfernt (war Debug-Code in Produktion).
 *         times_data.php nutzt jetzt cURL statt @file_get_contents (kein Freeze mehr).
 */

$PAGE_TITLE = 'Öffnungszeiten';
$LAT        = 52.52;
$LNG        = 13.41;
$previewUrl = 'https://wcr-webpage.de/oeffnungszeiten-story/?elementor-preview=1';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_times');

$db = $pdo;

require_once __DIR__ . '/../functions/upload_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = (strpos($uploadMessage, '✓') !== false) ? 'success' : 'error';
    $loc    = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $loc . '?upload=' . $status);
    exit;
}

if (empty($uploadMessage)) {
    $param = $_GET['upload'] ?? '';
    if ($param === 'success') $uploadMessage = '✓ Foto hochgeladen und auf 1080×1920px angepasst!';
    elseif ($param === 'error') $uploadMessage = '✗ Fehler beim Upload';
    else $uploadMessage = '';
}

require_once __DIR__ . '/../functions/times_data.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="bo" data-csrf="<?= wcr_csrf_attr() ?>">

<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🕐 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
</div>

<div class="main-layout">

  <!-- LINKE SPALTE: Upload + Vorschau -->
  <div>
    <div class="upload-panel">
      <h3>📷 Foto hochladen</h3>
      <?php if ($uploadMessage): ?>
        <div class="upload-message <?= strpos($uploadMessage,'✓') !== false ? 'success' : 'error' ?>">
          <?= htmlspecialchars($uploadMessage) ?>
        </div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data" class="upload-form">
        <input type="file" name="opening_hours_photo" accept="image/jpeg,image/png,image/jpg" required>
        <button type="submit" class="btn-upload">Foto hochladen</button>
      </form>
    </div>

    <?php if ($currentPhoto): ?>
    <div class="preview-panel">
      <div class="preview-header">
        <span>📄 Vorschau (9:16)</span>
        <button class="btn-download" onclick="downloadAsJPG()">Als JPG</button>
      </div>
      <div class="preview-iframe-container compact">
        <iframe id="preview-iframe" class="preview-iframe"
                src="<?= htmlspecialchars($previewUrl) ?>"></iframe>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- RECHTE SPALTE: Tabelle -->
  <div id="items-container">
    <div class="times-table">
      <?php
      $renderedKws = [];
      foreach ($keys as $index => $key):
          $dt           = $periodArray[$key];
          $dateStr      = $dt->format('Y-m-d');
          $kw           = $dt->format('W');
          $wochentag    = $deWochentage[$dt->format('w')];
          $datumAnzeige = $wochentag . ', ' . $dt->format('d.m.');
          $isToday      = ($dateStr === $today->format('Y-m-d'));
          $isPast       = ($dt < $today);
          $isKwEnd      = ($index === $lastIndex) || (
              isset($keys[$index + 1]) &&
              $periodArray[$keys[$index + 1]]->format('W') !== $kw
          );
          $rowClass  = $isToday ? 'row-today' : ($isPast ? 'row-past' : '');
          if ($isKwEnd) $rowClass .= ' kw-end-border';
          $rowStyle  = '';
          if ($isPast) {
              $daysAgo  = (int)$today->diff($dt)->days;
              $opacity  = max(0.1, round(1 - ($daysAgo / 5), 2));
              $rowStyle = ' style="opacity:' . $opacity . ';"';
          }
          $datumCellStyle = 'justify-content:space-between; align-items:center; gap:6px;'
                          . ($isPast ? ' opacity:' . ($opacity ?? 1) . ';' : '');
          $kurseStyle     = 'justify-content:center; padding:4px;'
                          . ($isPast ? ' opacity:' . ($opacity ?? 1) . ';' : '');
          $valStart = $savedData[$dateStr]['start_time'] ?? '';
          $valEnd   = $savedData[$dateStr]['end_time']   ?? '';
          $isClosed = (bool)($savedData[$dateStr]['is_closed'] ?? 0);
          $sunset   = $sunsetMap[$dateStr] ?? '--:--';
          $isFallbackEnd = (!$isClosed && empty($valEnd) && $sunset !== '--:--');
          $valEndDisplay = $isFallbackEnd ? $sunset : $valEnd;
          $c1       = (int)($savedData[$dateStr]['course1'] ?? 0);
          $c2       = (int)($savedData[$dateStr]['course2'] ?? 0);
          $dayShort = $dt->format('D');
          $isWeekend = in_array($dayShort, ['Sat','Sun']);
          $defT1    = ($dayShort === 'Sat') ? '09:00 - 11:00' : '08:00 - 10:00';
          $defT2    = ($dayShort === 'Sat') ? '11:00 - 13:00' : '10:00 - 12:00';
          $txt1     = $savedData[$dateStr]['course1_text'] ?? $defT1;
          $txt2     = $savedData[$dateStr]['course2_text'] ?? $defT2;

          if (!in_array($kw, $renderedKws)) {
              echo '<div class="cell-kw" style="grid-row: span ' . $daysByKw[$kw] . ';'>'
                 . '<span>KW ' . $kw . '</span></div>';
              $renderedKws[] = $kw;
          }
      ?>

      <!-- DATUM -->
      <div class="cell <?= $rowClass ?> <?= $isClosed ? 'cell-closed' : '' ?>"
           style="<?= $datumCellStyle ?>">
        <div style="display:flex; align-items:center; gap:6px; min-width:0;">
          <span style="font-weight:600; white-space:nowrap;"><?= $datumAnzeige ?></span>
          <?php if ($isToday): ?><span class="badge-today">Heute</span><?php endif; ?>
          <?php if ($isClosed): ?><span class="badge-closed">Geschlossen</span><?php endif; ?>
        </div>
        <div style="display:flex; gap:4px; align-items:center;">
          <button class="btn-closed <?= $isClosed ? 'active' : '' ?>"
                  onclick="toggleClosed('<?= $dateStr ?>',this)"
                  title="<?= $isClosed ? 'Wieder öffnen' : 'Als geschlossen markieren' ?>">
            <?= $isClosed ? '🔓' : '🔒' ?>
          </button>
          <?php if (!empty($valStart) || !empty($valEnd)): ?>
            <button class="btn-clear-times" onclick="clearTimes('<?= $dateStr ?>')" title="Zeiten löschen">✕</button>
          <?php endif; ?>
        </div>
      </div>

      <!-- START -->
      <div class="cell <?= $rowClass ?>"<?= $rowStyle ?>>
        <input type="time" id="start-<?= $dateStr ?>"
               value="<?= htmlspecialchars($valStart) ?>"
               step="1800" placeholder="09:00"
               <?= $isClosed ? 'disabled' : '' ?>
               onchange="updateTime(this,'<?= $dateStr ?>','start_time')">
      </div>

      <!-- ENDE -->
      <div class="cell <?= $rowClass ?>"<?= $rowStyle ?>>
        <input type="time" id="end-<?= $dateStr ?>"
               value="<?= htmlspecialchars($valEndDisplay) ?>"
               step="1800" placeholder="<?= $sunset ?>"
               class="<?= $isFallbackEnd ? 'is-fallback' : '' ?>"
               <?= $isClosed ? 'disabled' : '' ?>
               onchange="updateTime(this,'<?= $dateStr ?>','end_time')">
      </div>

      <!-- KURSE -->
      <div class="cell <?= $rowClass ?>" style="<?= $kurseStyle ?>">
        <?php if ($isWeekend && !$isClosed): ?>
          <div class="course-wrapper">
            <div class="course-pill <?= $c1 ? 'active' : '' ?>" id="pill-c1-<?= $dateStr ?>">
              <div class="course-toggle" onclick="toggleCourse('<?= $dateStr ?>','course1',this)"></div>
              <input type="text" class="course-input"
                     value="<?= htmlspecialchars($txt1) ?>"
                     data-fallback="<?= htmlspecialchars($defT1) ?>"
                     onchange="updateCourseText(this,'<?= $dateStr ?>','course1_text')">
            </div>
            <div class="course-pill <?= $c2 ? 'active' : '' ?>" id="pill-c2-<?= $dateStr ?>">
              <div class="course-toggle" onclick="toggleCourse('<?= $dateStr ?>','course2',this)"></div>
              <input type="text" class="course-input"
                     value="<?= htmlspecialchars($txt2) ?>"
                     data-fallback="<?= htmlspecialchars($defT2) ?>"
                     onchange="updateCourseText(this,'<?= $dateStr ?>','course2_text')">
            </div>
          </div>
        <?php elseif ($isWeekend && $isClosed): ?>
          <span style="color:#ccc; font-size:12px;">—</span>
        <?php endif; ?>
      </div>

      <?php endforeach; ?>
    </div><!-- /times-table -->
  </div><!-- /rechte Spalte -->

</div><!-- /main-layout -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="/be/js/times.js"></script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
