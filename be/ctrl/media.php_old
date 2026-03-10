<?php

/**
 * ═══════════════════════════════════════════════════════════════
 * DATEI: be/ctrl/media.php
 * ───────────────────────────────────────────────────────────────
 * Seite    : Media-Verwaltung
 * Zweck    : Bilder in konfigurierten Ordnern ansehen,
 *            per Toggle für random_split_photos() aktivieren /
 *            deaktivieren und neue Bilder hochladen.
 *
 * Abhängigkeiten:
 *   be/inc/auth.php          → require_login()
 *   be/inc/db.php            → $db (PDO)
 *   be/api/toggle_media.php  → AJAX-Toggle-Endpunkt
 *   be/inc/style.css         → gemeinsames Apple-Design-System
 *
 * DB-Tabelle (wird beim ersten Aufruf auto-erstellt):
 *   media_files (id, folder, filename, is_active, created_at)
 *
 * Neuen Ordner hinzufügen:
 *   Im Array $MEDIA_FOLDERS einen weiteren Eintrag ergänzen –
 *   alle anderen Dateien bleiben unverändert.
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_media');
$db = $pdo;
// ← DEBUG: Diese 2 Zeilen temporär einfügen

// ═══════════════════════════════════════════════════════════════
// KONFIGURATION  –  Ordner hier pflegen
// ═══════════════════════════════════════════════════════════════
$MEDIA_FOLDERS = [

    'ticket' => [
        'label'        => 'Ticket Bilder',
        'icon'         => '🎫️',
        // Absoluter Dateisystem-Pfad (trailing slash erforderlich!)
        // __DIR__ = /be/ctrl  →  ../../wp-content/uploads/ticket/
        'abs_path'     => realpath(__DIR__ . '/../../wp-content/uploads/ticket') . '/',
        // URL-Basis für den Browser (relativ von /be/ctrl/media.php aus)
        'web_base'     => '../../wp-content/uploads/ticket/',
        // Wird in der Info-Bar angezeigt
        'requirements' => [
            'Format: JPG, PNG oder WebP',
            'Empfohlene Größe: mind. 800 × 600 px',
            'Max. Dateigröße: 5 MB pro Bild',
            'Verwendet von: random_split_photos()',
        ],
    ],

    // ── Weiteren Ordner hinzufügen – einfach duplizieren ────────────────────────
    // 'events' => [
    //     'label'        => 'Event Bilder',
    //     'icon'         => '📸',
    //     'abs_path'     => realpath(__DIR__ . '/../../wp-content/uploads/events') . '/',
    //     'web_base'     => '../../wp-content/uploads/events/',
    //     'requirements' => ['Format: JPG, PNG, WebP', 'Max. 5 MB'],
    // ],

];

// ═══════════════════════════════════════════════════════════════
// AKTIVER ORDNER  (aus URL-Parameter, Fallback auf ersten Eintrag)
// ═══════════════════════════════════════════════════════════════
reset($MEDIA_FOLDERS);
$defaultKey = key($MEDIA_FOLDERS);
$activeKey  = (isset($_GET['folder']) && array_key_exists($_GET['folder'], $MEDIA_FOLDERS))
                ? $_GET['folder']
                : $defaultKey;

$folder     = $MEDIA_FOLDERS[$activeKey];
$folderPath = $folder['abs_path'];
$webBase    = $folder['web_base'];

// ═══════════════════════════════════════════════════════════════
// DB – Tabelle auto-erstellen (läuft nur einmalig durch)
// ═══════════════════════════════════════════════════════════════
$db->exec("
    CREATE TABLE IF NOT EXISTS media_files (
        id         INT          AUTO_INCREMENT PRIMARY KEY,
        folder     VARCHAR(50)  NOT NULL,
        filename   VARCHAR(255) NOT NULL,
        is_active  TINYINT(1)   NOT NULL DEFAULT 1,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_file (folder, filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ═══════════════════════════════════════════════════════════════
// UPLOAD HANDLING  (PRG-Pattern – verhindert Doppel-Submit)
// ═══════════════════════════════════════════════════════════════
$uploadMsg   = '';
$uploadIsErr = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media_upload']['name'][0])) {

    $allowedExts = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];
    $maxBytes     = 5 * 1024 * 1024;  // 5 MB
    $countSuccess = 0;
    $errors       = [];

    // Ordner anlegen falls noch nicht vorhanden
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }

    // PHP liefert bei multiple-Input immer Arrays
    $fNames = (array)$_FILES['media_upload']['name'];
    $fTmps  = (array)$_FILES['media_upload']['tmp_name'];
    $fSizes = (array)$_FILES['media_upload']['size'];

    foreach ($fNames as $i => $origName) {
        if (empty($origName) || empty($fTmps[$i])) continue;

        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $mime     = mime_content_type($fTmps[$i]);
        $fileSize = (int)$fSizes[$i];

        // ── Validierung ──────────────────────────────────────────────────────────────────────
        if (!array_key_exists($ext, $allowedExts)) {
            $errors[] = "$origName: ungültiges Format (JPG/PNG/WebP erlaubt)";
            continue;
        }
        if (!in_array($mime, $allowedExts, true)) {
            $errors[] = "$origName: MIME-Typ passt nicht zum Dateinamen";
            continue;
        }
        if ($fileSize > $maxBytes) {
            $errors[] = "$origName: zu groß (max. 5 MB)";
            continue;
        }

        // ── Sicherer Dateiname (nur alphanumerisch + . _ -) ────────────────────────────────────────────
        $safeName = preg_replace('/[^a-zA-Z0-9._\\-]/', '_', $origName);
        $dest     = $folderPath . $safeName;

        if (move_uploaded_file($fTmps[$i], $dest)) {
            // DB-Eintrag: neue Bilder starten als aktiv (is_active = 1)
            $stmt = $db->prepare("
                INSERT IGNORE INTO media_files (folder, filename, is_active)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$activeKey, $safeName]);
            $countSuccess++;
        } else {
            $errors[] = "$origName: konnte nicht gespeichert werden";
        }
    }

    // Status-Meldung zusammenbauen
    $parts = [];
    if ($countSuccess > 0) $parts[] = "✓ $countSuccess Bild(er) hochgeladen";
    if (!empty($errors))   $parts[] = implode(' | ', $errors);

    // PRG-Redirect (verhindert Doppel-Upload bei F5)
    header("Location: media.php?folder=$activeKey&msg=" . urlencode(implode(' — ', $parts)));
    exit;
}

// Status-Meldung nach Redirect anzeigen
if (!empty($_GET['msg'])) {
    $uploadMsg   = htmlspecialchars(urldecode($_GET['msg']));
    $uploadIsErr = (strpos($uploadMsg, '✓') !== 0);
}

// ═══════════════════════════════════════════════════════════════
// BILDER LADEN  –  Dateisystem + DB-Status zusammenführen
// ═══════════════════════════════════════════════════════════════
$images         = [];
$allowedExtList = ['jpg', 'jpeg', 'png', 'webp'];

if (is_dir($folderPath)) {
    foreach (glob($folderPath . '*') as $filepath) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtList)) continue;

        $filename = basename($filepath);
        $images[] = $filename;

        // Sicherstellen: Jede Datei hat einen DB-Eintrag
        $stmt = $db->prepare("
            INSERT IGNORE INTO media_files (folder, filename, is_active)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([$activeKey, $filename]);
    }
}

// DB-Status aller Bilder auf einmal abrufen (eine Query)
$activeMap = [];
if (!empty($images)) {
    $ph   = implode(',', array_fill(0, count($images), '?'));
    $stmt = $db->prepare("
        SELECT filename, is_active
        FROM   media_files
        WHERE  folder = ? AND filename IN ($ph)
    ");
    $stmt->execute(array_merge([$activeKey], $images));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activeMap[$row['filename']] = (int)$row['is_active'];
    }
}

$activeCount = array_sum($activeMap);
$totalCount  = count($images);
$PAGE_TITLE  = 'Media';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($PAGE_TITLE) ?> – Backend</title>
    <link rel="stylesheet" href="../inc/style.css">
    <style>
    
    </style>
</head>
<body data-csrf="<?= wcr_csrf_attr() ?>">

<!-- ═══════════════════════════════════════════════════════════════
     NAVIGATION  –  Passe Links an dein bestehendes nav-bar Menü an!
     ═══════════════════════════════════════════════════════════════ -->
    <?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="page-wrapper" style="max-width:1180px;margin:0 auto;padding:24px 20px;">

    <!-- Seitentitel -->
    <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:700;color:#1d1d1f;margin:0;">🖼️ Media-Verwaltung</h1>
        <p style="font-size:13px;color:#86868b;margin:3px 0 0;">
            Bilder verwalten · für WordPress-Funktionen aktivieren/deaktivieren
        </p>
    </div>

    <div class="media-layout">

        <!-- ════════════════════════════════
             LINKE SIDEBAR – Ordner-Auswahl
             ════════════════════════════════ -->
        <nav class="media-sidebar">
            <div class="sidebar-head">Ordner</div>

            <?php foreach ($MEDIA_FOLDERS as $key => $cfg):
                $isActive = ($key === $activeKey);
                // Badge: Anzahl aktiver Bilder dieses Ordners
                $bs = $db->prepare("SELECT COUNT(*) FROM media_files WHERE folder=? AND is_active=1");
                $bs->execute([$key]);
                $badgeNum = (int)$bs->fetchColumn();
            ?>
            <a href="media.php?folder=<?= htmlspecialchars($key) ?>"
               class="sidebar-link <?= $isActive ? 'active' : '' ?>">
                <span class="s-icon"><?= $cfg['icon'] ?></span>
                <span><?= htmlspecialchars($cfg['label']) ?></span>
                <span class="s-badge"><?= $badgeNum ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- ════════════════════════════════
             HAUPTBEREICH
             ════════════════════════════════ -->
        <main class="media-main">

            <!-- ── INFO-BAR ── -->
            <section class="info-bar">
                <div class="info-reqs">
                    <h4>Anforderungen · <?= htmlspecialchars($folder['label']) ?></h4>
                    <div class="req-tags">
                        <?php foreach ($folder['requirements'] as $req): ?>
                        <span class="req-tag"><?= htmlspecialchars($req) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="info-stat">
                    <div class="stat-num" id="js-active-count"><?= $activeCount ?></div>
                    <div class="stat-lbl">Aktiv</div>
                    <div class="stat-total">von <?= $totalCount ?></div>
                </div>
            </section>

            <!-- ── UPLOAD-BOX ── -->
            <section class="upload-box">
                <h4>📤 Bilder hinzufügen</h4>
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <div class="drop-zone" id="drop-zone">
                        <input type="file" name="media_upload[]" id="file-input"
                               accept=".jpg,.jpeg,.png,.webp" multiple>
                        <div class="drop-icon">🖼️</div>
                        <div class="drop-text" id="drop-text">Klicken oder Dateien hierher ziehen</div>
                        <div class="drop-hint">JPG · PNG · WebP &nbsp;|&nbsp; max. 5 MB pro Datei</div>
                    </div>
                    <button type="submit" class="btn-upload" id="btn-upload" disabled>
                        Hochladen
                    </button>
                </form>
                <?php if ($uploadMsg): ?>
                <div class="upload-msg <?= $uploadIsErr ? 'err' : 'ok' ?>">
                    <?= $uploadMsg ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- ── GALERIE ── -->
            <section class="gallery-panel">
                <div class="gallery-header">
                    <h4>
                        🗂️ <?= htmlspecialchars($folder['label']) ?>
                        <span style="font-weight:400;color:#aaa;">(<?= $totalCount ?>)</span>
                    </h4>
                    <div class="filter-group">
                        <button class="filter-btn active" data-filter="all">Alle</button>
                        <button class="filter-btn"        data-filter="on">Aktiv</button>
                        <button class="filter-btn"        data-filter="off">Inaktiv</button>
                    </div>
                </div>

                <?php if (empty($images)): ?>
                <div class="gallery-empty">
                    <div class="ei">📂</div>
                    <p>Keine Bilder im Ordner gefunden.<br>Lade Bilder hoch, um zu starten.</p>
                </div>

                <?php else: ?>
                <div class="media-gallery" id="media-gallery">

                    <?php foreach ($images as $filename):
                        $isActive  = $activeMap[$filename] ?? 1;
                        $cardClass = $isActive ? '' : 'status-off';
                        $imgUrl    = htmlspecialchars($webBase . $filename);
                    ?>
                    <div class="media-card <?= $cardClass ?>"
                         data-filename="<?= htmlspecialchars($filename) ?>"
                         data-folder="<?= htmlspecialchars($activeKey) ?>"
                         data-active="<?= $isActive ?>"
                         onclick="toggleMedia(this)">

                        <!-- Ladeindikator (während AJAX) -->
                        <div class="card-spinner">⌛</div>

                        <!-- Dateiname-Overlay (hover + immer sichtbar wenn off) -->
                        <span class="card-name"><?= htmlspecialchars($filename) ?></span>

                        <!-- Bild -->
                        <img src="<?= $imgUrl ?>"
                             alt="<?= htmlspecialchars($filename) ?>"
                             loading="lazy">

                        <!-- ON/OFF Pill -->
                        <div class="card-pill">
                            <span class="pill-on">ON</span>
                            <span class="pill-off">OFF</span>
                        </div>

                    </div>
                    <?php endforeach; ?>

                </div>
                <?php endif; ?>

            </section>

        </main>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════════
   MEDIA.JS  –  Toggle · Galerie-Filter · Upload-UI · Drag & Drop
   PHP 7.4 kompatibel – kein modernes JS (kein ??, kein Arrow-Fn)
   ═══════════════════════════════════════════════════════════════ */
'use strict';

// ──────────────────────────────────────────────────────────────────
// 1. TOGGLE  –  is_active per AJAX setzen
// ──────────────────────────────────────────────────────────────────

/**
 * Sendet den neuen Status an toggle_media.php.
 * Aktualisiert die Karte sofort ohne Reload (Optimistic UI).
 * @param {HTMLElement} card  Angeklickte .media-card
 */
function toggleMedia(card) {
    if (card.classList.contains('loading')) return; // Doppelklick blockieren

    var filename  = card.dataset.filename;
    var folder    = card.dataset.folder;
    var wasActive = (card.dataset.active === '1');
    var newVal    = wasActive ? 0 : 1;

    card.classList.add('loading');

    fetch('../api/toggle_media.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({
            folder    : folder,
            filename  : filename,
            is_active : newVal,
            // ── CSRF-Token anhängen ──
            csrf_token: document.body.dataset.csrf || ''
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        card.classList.remove('loading');
        if (data.ok) {
            // ── CSRF-Token nach Rotation aktualisieren ──
            // API hat neues Token zurückgegeben, Frontend muss es speichern
            // damit nächster Request auf derselben Seite funktioniert
            if (data.csrf_token) {
                document.body.dataset.csrf = data.csrf_token;
            }

            // UI aktualisieren
            card.dataset.active = String(newVal);
            card.classList.toggle('status-off', newVal === 0);
            // Zähler in Info-Bar anpassen
            adjustCount(newVal === 1 ? 1 : -1);
            // Badge in Sidebar aktualisieren
            adjustSidebarBadge(newVal === 1 ? 1 : -1);
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannt'));
        }
    })
    .catch(function(err) {
        card.classList.remove('loading');
        alert('Netzwerkfehler: ' + err.message);
    });
}

// ──────────────────────────────────────────────────────────────────
// 2. ZÄHLER AKTUALISIEREN
// ──────────────────────────────────────────────────────────────────

function adjustCount(delta) {
    var el = document.getElementById('js-active-count');
    if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) + delta);
}

function adjustSidebarBadge(delta) {
    var badge = document.querySelector('.sidebar-link.active .s-badge');
    if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent, 10) + delta);
}

// ──────────────────────────────────────────────────────────────────
// 3. GALERIE-FILTER  (Alle / Aktiv / Inaktiv)
// ──────────────────────────────────────────────────────────────────

document.querySelectorAll('.filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        // Aktiv-Klasse umsetzen
        document.querySelectorAll('.filter-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        btn.classList.add('active');

        var filter = btn.dataset.filter;
        document.querySelectorAll('.media-card').forEach(function(card) {
            var isOn = (card.dataset.active === '1');
            if (filter === 'all') {
                card.style.display = '';
            } else if (filter === 'on') {
                card.style.display = isOn ? '' : 'none';
            } else {
                card.style.display = !isOn ? '' : 'none';
            }
        });
    });
});

// ──────────────────────────────────────────────────────────────────
// 4. UPLOAD UI  –  Dateiauswahl-Feedback + Drag & Drop
// ──────────────────────────────────────────────────────────────────

var fileInput = document.getElementById('file-input');
var dropZone  = document.getElementById('drop-zone');
var dropText  = document.getElementById('drop-text');
var btnUpload = document.getElementById('btn-upload');

// Dateiauswahl per Klick
fileInput.addEventListener('change', function() {
    var count = fileInput.files.length;
    if (count > 0) {
        dropText.textContent = count === 1
            ? fileInput.files[0].name
            : count + ' Dateien ausgewählt';
        btnUpload.disabled = false;
    } else {
        dropText.textContent = 'Klicken oder Dateien hierher ziehen';
        btnUpload.disabled   = true;
    }
});

// Drag & Drop
dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
dropZone.addEventListener('dragleave', function() {
    dropZone.classList.remove('drag-over');
});
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer && e.dataTransfer.files.length) {
        try {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        } catch (err) {
            // Fallback: DataTransfer nicht setzbar (Safari)
            dropText.textContent = e.dataTransfer.files.length + ' Datei(en) per Drag';
            btnUpload.disabled   = false;
        }
    }
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
