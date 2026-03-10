<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * DATEI: be/ctrl/kino.php
 * ───────────────────────────────────────────────────────────────
 * Seite    : Open-Air-Kino-Programm
 * Zweck    : Filme verwalten (hinzufügen, bearbeiten, löschen)
 *            Cover-Bild, Titel, Spieltag, Sortierung
 *
 * SECURITY v9: Erfordert edit_content Permission + CSRF-Token
 *
 * Abhängigkeiten:
 *   be/inc/auth.php          → require_login(), wcr_require(), wcr_verify_csrf()
 *   be/inc/db.php            → $db (PDO)
 *   be/inc/style.css         → gemeinsames Apple-Design-System
 *
 * DB-Tabelle: wp_wcr_kino (id, title, cover_url, date, sort_order)
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_content');

$db = $pdo;

$TABLE = 'wp_wcr_kino';
$PAGE_TITLE = 'Kino';

// ── POST: Film hinzufügen / aktualisieren / löschen ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF-Schutz ──
    wcr_verify_csrf();
    
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $id         = ($action === 'update') ? (int)$_POST['id'] : 0;
        $title      = trim($_POST['title'] ?? '');
        $cover_url  = trim($_POST['cover_url'] ?? '');
        $date       = trim($_POST['date'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO $TABLE (title, cover_url, date, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $cover_url, $date, $sort_order]);
            $msg = '✅ Film hinzugefügt!';
        } else {
            $stmt = $db->prepare("UPDATE $TABLE SET title=?, cover_url=?, date=?, sort_order=? WHERE id=?");
            $stmt->execute([$title, $cover_url, $date, $sort_order, $id]);
            $msg = '✅ Film aktualisiert!';
        }
        // PRG-Redirect
        header("Location: kino.php?msg=" . urlencode($msg));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM $TABLE WHERE id=?");
        $stmt->execute([$id]);
        header("Location: kino.php?msg=" . urlencode('✅ Film gelöscht!'));
        exit;
    }
}

// ── Alle Filme laden ──
$stmt = $db->query("SELECT * FROM $TABLE ORDER BY date ASC, sort_order ASC");
$films = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Edit-Modus ──
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM $TABLE WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$msg = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($PAGE_TITLE) ?> – Backend</title>
    <link rel="stylesheet" href="../inc/style.css">
    <style>
        .kino-layout { display:flex; gap:24px; margin-top:24px; }
        .kino-form { flex:0 0 400px; }
        .kino-gallery { flex:1; }
        .kino-card {
            background:#fff;
            border:1px solid #e5e5e5;
            border-radius:12px;
            padding:20px;
            margin-bottom:16px;
        }
        .kino-card h4 {
            font-size:15px;
            font-weight:600;
            margin:0 0 16px 0;
            color:#1d1d1f;
        }
        .form-group {
            margin-bottom:16px;
        }
        .form-group label {
            display:block;
            font-size:13px;
            font-weight:600;
            color:#1d1d1f;
            margin-bottom:6px;
        }
        .form-group input {
            width:100%;
            padding:10px 12px;
            border:1px solid #d2d2d7;
            border-radius:8px;
            font-size:14px;
            transition:border-color 0.2s;
        }
        .form-group input:focus {
            outline:none;
            border-color:#0071e3;
        }
        .form-group small {
            display:block;
            margin-top:4px;
            font-size:11px;
            color:#86868b;
        }
        .btn-primary {
            background:#0071e3;
            color:#fff;
            border:none;
            padding:10px 20px;
            border-radius:8px;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
            transition:background 0.2s;
        }
        .btn-primary:hover { background:#0077ed; }
        .btn-secondary {
            background:#fff;
            color:#1d1d1f;
            border:1px solid #d2d2d7;
            padding:10px 20px;
            border-radius:8px;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
            margin-left:8px;
            text-decoration:none;
            display:inline-block;
        }
        .btn-secondary:hover { background:#f5f5f7; }
        .film-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));
            gap:20px;
        }
        .film-item {
            background:#fff;
            border:1px solid #e5e5e5;
            border-radius:12px;
            overflow:hidden;
            transition:transform 0.2s, box-shadow 0.2s;
            position:relative;
        }
        .film-item:hover {
            transform:translateY(-4px);
            box-shadow:0 10px 30px rgba(0,0,0,0.15);
        }
        .film-cover {
            width:100%;
            height:320px;
            object-fit:cover;
            border-bottom:1px solid #e5e5e5;
        }
        .film-info {
            padding:16px;
        }
        .film-title {
            font-size:15px;
            font-weight:600;
            margin:0 0 8px 0;
            color:#1d1d1f;
        }
        .film-date {
            font-size:13px;
            color:#86868b;
            margin:0 0 12px 0;
        }
        .film-actions {
            display:flex;
            gap:8px;
        }
        .film-actions button,
        .film-actions a {
            flex:1;
            padding:8px;
            border-radius:6px;
            font-size:12px;
            font-weight:600;
            text-align:center;
            border:1px solid #d2d2d7;
            background:#fff;
            cursor:pointer;
            text-decoration:none;
            color:#1d1d1f;
        }
        .film-actions button:hover { background:#f5f5f7; }
        .film-actions button.danger { color:#d70015; border-color:#d70015; }
        .film-badge {
            position:absolute;
            top:12px;
            right:12px;
            background:rgba(0,0,0,0.8);
            color:#fff;
            padding:6px 12px;
            border-radius:20px;
            font-size:11px;
            font-weight:600;
            text-transform:uppercase;
        }
        .film-badge.today { background:#0071e3; }
        .film-badge.past { background:#d70015; }
        .film-item.past { opacity:0.5; }
        .msg-box {
            background:#d1f4e0;
            color:#0d8043;
            padding:12px 16px;
            border-radius:8px;
            margin-bottom:20px;
            font-size:13px;
            font-weight:600;
        }
        .empty-state {
            text-align:center;
            padding:60px 20px;
            color:#86868b;
        }
        .empty-state .ei {
            font-size:48px;
            margin-bottom:12px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="page-wrapper" style="max-width:1400px;margin:0 auto;padding:24px 20px;">

    <!-- Seitentitel -->
    <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:700;color:#1d1d1f;margin:0;">🎬 Open-Air-Kino-Programm</h1>
        <p style="font-size:13px;color:#86868b;margin:3px 0 0;">
            Filme mit Cover, Titel und Spieltag verwalten · Automatische Datumsfilterung im Frontend
        </p>
    </div>

    <?php if ($msg): ?>
    <div class="msg-box"><?= $msg ?></div>
    <?php endif; ?>

    <div class="kino-layout">

        <!-- ── FORMULAR ── -->
        <aside class="kino-form">
            <div class="kino-card">
                <h4><?= $edit ? '✏️ Film bearbeiten' : '➕ Neuer Film' ?></h4>
                <form method="POST">
                    <?= wcr_csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
                    <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Filmtitel</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Cover-URL</label>
                        <input type="url" name="cover_url" value="<?= htmlspecialchars($edit['cover_url'] ?? '') ?>" 
                               placeholder="https://example.com/poster.jpg" required>
                        <small>Direkte URL zum Film-Poster (JPG/PNG)</small>
                    </div>

                    <div class="form-group">
                        <label>Spieltag</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($edit['date'] ?? '') ?>" required>
                        <small>Film wird ab dem Folgetag ausgeblendet</small>
                    </div>

                    <div class="form-group">
                        <label>Sortierung</label>
                        <input type="number" name="sort_order" value="<?= htmlspecialchars($edit['sort_order'] ?? 0) ?>">
                        <small>Niedrigere Zahlen = weiter vorne im Slider</small>
                    </div>

                    <button type="submit" class="btn-primary">
                        <?= $edit ? '✅ Speichern' : '➕ Hinzufügen' ?>
                    </button>
                    <?php if ($edit): ?>
                    <a href="kino.php" class="btn-secondary">❌ Abbrechen</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Info-Box -->
            <div class="kino-card" style="margin-top:16px;">
                <h4>📍 Info</h4>
                <p style="font-size:12px;color:#86868b;line-height:1.5;margin:0;">
                    <strong>Frontend-Shortcode:</strong><br>
                    <code style="background:#f5f5f7;padding:4px 8px;border-radius:4px;">[wcr_kino_slider]</code><br><br>
                    <strong>REST-API:</strong><br>
                    <code style="background:#f5f5f7;padding:4px 8px;border-radius:4px;">/wp-json/wakecamp/v1/kino</code><br><br>
                    Nur Filme ab <strong>heute</strong> werden angezeigt.
                </p>
            </div>
        </aside>

        <!-- ── GALERIE ── -->
        <main class="kino-gallery">
            <div class="kino-card">
                <h4>🎬 Alle Filme (<?= count($films) ?>)</h4>
                <?php if (empty($films)): ?>
                <div class="empty-state">
                    <div class="ei">🎬</div>
                    <p>Noch keine Filme hinzugefügt.<br>Füge deinen ersten Film hinzu, um zu starten.</p>
                </div>
                <?php else: ?>
                <div class="film-grid">
                    <?php
                    $today = date('Y-m-d');
                    foreach ($films as $film):
                        $isPast  = $film['date'] < $today;
                        $isToday = $film['date'] === $today;
                        $badge   = $isToday ? 'Heute' : ($isPast ? 'Gelaufen' : 'Kommend');
                        $badgeClass = $isToday ? 'today' : ($isPast ? 'past' : '');
                        $itemClass  = $isPast ? 'past' : '';
                    ?>
                    <div class="film-item <?= $itemClass ?>">
                        <span class="film-badge <?= $badgeClass ?>"><?= $badge ?></span>
                        <img src="<?= htmlspecialchars($film['cover_url']) ?>" 
                             alt="<?= htmlspecialchars($film['title']) ?>" 
                             class="film-cover"
                             onerror="this.src='https://via.placeholder.com/300x450?text=Kein+Cover'">
                        <div class="film-info">
                            <div class="film-title"><?= htmlspecialchars($film['title']) ?></div>
                            <div class="film-date">📅 <?= date('d.m.Y', strtotime($film['date'])) ?></div>
                            <div class="film-actions">
                                <a href="kino.php?edit=<?= $film['id'] ?>">✏️ Bearbeiten</a>
                                <form method="POST" style="flex:1;margin:0;" 
                                      onsubmit="return confirm('Film wirklich löschen?');">
                                    <?= wcr_csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $film['id'] ?>">
                                    <button type="submit" class="danger">🗑️ Löschen</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>

    </div>
</div>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
