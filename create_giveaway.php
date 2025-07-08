<?php
include 'db.php';

// Generate short management code (8 characters: letters + numbers)
function generateManagementCode() {
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
}

// Get form data
$title = $_POST['title'];
$slug = $_POST['slug'];
$countdown_datetime = $_POST['countdown_datetime'];
$user_timezone = $_POST['user_timezone'] ?? 'UTC';
$es_link = trim($_POST['es_link']);
$winner_count = (int)$_POST['winner_count'];
$winner_selection_mode = $_POST['winner_selection_mode']; // auto or manual

// Validate inputs
if ($winner_count < 1 || $winner_count > 10) {
    die("Error: Invalid number of winners. Must be between 1-10.");
}

if (!in_array($winner_selection_mode, ['auto', 'manual'])) {
    die("Error: Invalid winner selection mode.");
}

// Convert to UTC
$date = new DateTime($countdown_datetime, new DateTimeZone($user_timezone));
$date->setTimezone(new DateTimeZone('UTC'));
$countdown_datetime = $date->format('Y-m-d H:i:s');

// Generate unique management code
do {
    $management_code = generateManagementCode();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE management_code = ?");
    $stmt->execute([$management_code]);
} while ($stmt->fetchColumn() > 0);

// Check if slug is unique
$stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE slug = ?");
$stmt->execute([$slug]);
if ($stmt->fetchColumn() > 0) {
    die("Error: Giveaway code already exists. Choose another.");
}

// Insert giveaway with all fields
$stmt = $pdo->prepare("INSERT INTO giveaways (title, slug, countdown_datetime, es_link, management_code, winner_count, winner_selection_mode) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$title, $slug, $countdown_datetime, $es_link, $management_code, $winner_count, $winner_selection_mode]);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Giveaway Created!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #ffe6f0;
            color: #333;
            text-align: center;
            padding: 30px;
            cursor: url('http://www.rw-designer.com/cursor-extern.php?id=176131'), auto;
        }
        .success-box {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px auto;
            max-width: 500px;
            box-shadow: 0 0 10px #ff99cc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        h1 { color: #ff3399; }
        .code { 
            background: #ff3399; 
            color: white; 
            padding: 10px; 
            border-radius: 4px; 
            font-size: 18px;
            margin: 10px 0;
            letter-spacing: 2px;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .giveaway-link {
            background: #ff3399;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
            word-break: break-all;
            overflow-wrap: break-word;
            hyphens: auto;
            max-width: 100%;
            box-sizing: border-box;
        }
        .giveaway-link a {
            color: white;
            text-decoration: none;
            word-break: break-all;
            overflow-wrap: break-word;
            hyphens: auto;
            display: block;
        }
        .giveaway-link a:hover {
            text-decoration: underline;
        }
        .warning {
            background: #ffeb3b;
            color: #333;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
        .info {
            background: #e3f2fd;
            color: #333;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
        a {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px 15px;
            background: #ff3399;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            word-break: keep-all;
            white-space: nowrap;
            max-width: calc(50% - 10px);
            box-sizing: border-box;
        }
        a:hover { background: #e60073; }
        
        .button-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        /* Responsive design */
        @media (max-width: 600px) {
            .success-box {
                max-width: 90vw;
                margin: 10px auto;
                padding: 15px;
            }
            .code {
                font-size: 14px;
                letter-spacing: 1px;
            }
            .giveaway-link {
                font-size: 10px;
            }
            a {
                display: block;
                margin: 5px 0;
                font-size: 11px;
                max-width: 100%;
                width: 100%;
            }
            .button-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="success-box">
        <h1>🎉 Giveaway Created!</h1>
        
        <div class="giveaway-link">
            <strong>Your Giveaway:</strong><br>
            <a href="/<?= htmlspecialchars($slug) ?>" target="_blank">
                esgiveaways.online/<?= htmlspecialchars($slug) ?>
            </a>
        </div>
        
        <div class="info">
            🏆 This giveaway will pick <?= $winner_count ?> winner<?= $winner_count > 1 ? 's' : '' ?>!<br>
            <?php if ($winner_selection_mode === 'auto'): ?>
                ⚡ Winners will be picked automatically when countdown ends
            <?php else: ?>
                🎯 You'll trigger winner selection manually when ready
            <?php endif; ?>
        </div>
        
        <p><strong>Management Code:</strong></p>
        <div class="code"><?= htmlspecialchars($management_code) ?></div>
        
        <div class="warning">
            ⚠️ SAVE THIS CODE! You need it to manage your giveaway (add/remove entries, etc.)
        </div>
        
        <div class="button-container">
            <a href="/manage?code=<?= urlencode($management_code) ?>">Manage Giveaway</a>
            <a href="/create">Create Another</a>
        </div>
    </div>
</body>
</html>