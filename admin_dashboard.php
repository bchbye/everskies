<?php
// 2. CREATE admin_dashboard.php
include 'admin_auth.php'; // We'll create this file too

// Add after session_start()
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// For POST requests, check CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
}

$success_message = '';
$error_message = '';

// Handle delete giveaway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_giveaway_id'])) {
    $delete_id = (int)$_POST['delete_giveaway_id'];

    // Delete winners first
    $stmt = $pdo->prepare("DELETE FROM winners WHERE giveaway_id = ?");
    $stmt->execute([$delete_id]);
    
    // Delete entries
    $stmt = $pdo->prepare("DELETE FROM entries WHERE giveaway_id = ?");
    $stmt->execute([$delete_id]);

    // Delete giveaway
    $stmt = $pdo->prepare("DELETE FROM giveaways WHERE id = ?");
    $stmt->execute([$delete_id]);

    header("Location: admin_dashboard.php");
    exit;
}

// Handle verify/unverify giveaway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_verify'])) {
    $giveaway_id = (int)$_POST['giveaway_id'];
    $new_status = (int)$_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE giveaways SET verified = ? WHERE id = ?");
    $stmt->execute([$new_status, $giveaway_id]);
    
    header("Location: admin_dashboard.php");
    exit;
}

// Handle add banned word
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banned_word'])) {
    $word = trim($_POST['word']);
    $category = $_POST['category'];
    $severity = $_POST['severity'];
    
    if (!empty($word)) {
        // Check if word already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM banned_words WHERE word = ?");
        $stmt->execute([$word]);
        
        if ($stmt->fetchColumn() > 0) {
            $error_message = "Word '$word' is already in the banned list";
        } else {
            $stmt = $pdo->prepare("INSERT INTO banned_words (word, category, severity) VALUES (?, ?, ?)");
            $stmt->execute([$word, $category, $severity]);
            $success_message = "Banned word '$word' added successfully";
        }
    } else {
        $error_message = "Word cannot be empty";
    }
}

// Handle delete banned word
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_banned_word'])) {
    $word_id = (int)$_POST['word_id'];
    
    $stmt = $pdo->prepare("DELETE FROM banned_words WHERE id = ?");
    $stmt->execute([$word_id]);
    
    $success_message = "Banned word deleted successfully";
}

// Handle bulk add banned words
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_words'])) {
    $bulk_text = trim($_POST['bulk_words']);
    $category = $_POST['bulk_category'];
    $severity = $_POST['bulk_severity'];
    
    if (!empty($bulk_text)) {
        $lines = explode("\n", $bulk_text);
        $added_count = 0;
        $skipped_count = 0;
        
        foreach ($lines as $line) {
            $word = trim($line);
            if (!empty($word)) {
                // Check if word already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM banned_words WHERE word = ?");
                $stmt->execute([$word]);
                
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO banned_words (word, category, severity) VALUES (?, ?, ?)");
                    $stmt->execute([$word, $category, $severity]);
                    $added_count++;
                } else {
                    $skipped_count++;
                }
            }
        }
        
        $success_message = "Added $added_count words. Skipped $skipped_count duplicates.";
    }
}

// Fetch all giveaways (active and expired)
$stmt = $pdo->prepare("
    SELECT g.*, 
           COUNT(DISTINCT e.username) as unique_entries,
           COUNT(DISTINCT e.id) as total_entries,
           GROUP_CONCAT(DISTINCT w.username ORDER BY w.position SEPARATOR ', ') as winners
    FROM giveaways g
    LEFT JOIN entries e ON g.id = e.giveaway_id
    LEFT JOIN winners w ON g.id = w.giveaway_id AND w.status = 'active'
    GROUP BY g.id
    ORDER BY g.countdown_datetime DESC
");
$stmt->execute();
$giveaways = $stmt->fetchAll();

// Fetch banned words
$stmt = $pdo->prepare("SELECT * FROM banned_words ORDER BY category, severity DESC, word");
$stmt->execute();
$banned_words = $stmt->fetchAll();

$now = time();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - ES Giveaways</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #ffe6f0;
            color: #333;
            padding: 20px;
            cursor: url('http://www.rw-designer.com/cursor-extern.php?id=176131'), auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        h1 {
            color: #ff3399;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #ff3399;
            text-align: center;
            margin: 30px 0 20px 0;
        }

        .logout-btn {
            background: #666;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #555;
        }

        .stats-bar {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        .stat-item {
            margin: 5px;
            font-size: 12px;
        }

        .stat-number {
            color: #ff3399;
            font-size: 16px;
            display: block;
        }

        .giveaway-card {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            position: relative;
        }

        .giveaway-card.expired {
            border-color: #ccc;
            background: #f5f5f5;
        }

        .giveaway-card.active {
            border-color: #4caf50;
            background: #f0fff0;
        }

        .giveaway-title {
            color: #ff3399;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .giveaway-info {
            font-size: 10px;
            color: #666;
            margin: 5px 0;
        }

        .giveaway-link {
            color: #ff3399;
            text-decoration: none;
            font-size: 10px;
        }

        .giveaway-link:hover {
            text-decoration: underline;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
            font-size: 10px;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            color: white;
        }

        .status-active {
            background: #4caf50;
        }

        .status-ended {
            background: #ff9800;
        }

        .status-expired {
            background: #f44336;
        }

        .winners-info {
            background: #e8f5e8;
            padding: 8px;
            border-radius: 4px;
            margin: 5px 0;
            font-size: 9px;
        }

        .verify-btn {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
            font-size: 10px;
        }

        .verify-btn.verified {
            background: #28a745;
            color: white;
        }

        .verify-btn.unverified {
            background: #ffc107;
            color: #333;
        }

        .verify-btn:hover {
            opacity: 0.8;
        }

        .status-verified {
            background: #28a745;
        }

        .status-unverified {
            background: #ffc107;
            color: #333;
        }
        
        /* Banned Words Section Styles */
        .banned-words-section {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .banned-word-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin-bottom: 20px;
        }
        
        .banned-word-form input, .banned-word-form select, .banned-word-form button {
            font-family: 'Press Start 2P', cursive;
            padding: 8px;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            background: #fff;
            font-size: 10px;
        }
        
        .banned-word-form button {
            background: #ff3399;
            color: white;
            cursor: pointer;
        }
        
        .banned-word-form button:hover {
            background: #e60073;
        }
        
        .banned-words-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        
        .banned-words-table th,
        .banned-words-table td {
            border: 1px solid #ff99cc;
            padding: 8px;
            text-align: left;
        }
        
        .banned-words-table th {
            background: #ff99cc;
            color: white;
            font-weight: bold;
        }
        
        .category-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            color: white;
        }
        
        .category-profanity { background: #e74c3c; }
        .category-spam { background: #f39c12; }
        .category-inappropriate { background: #9b59b6; }
        .category-reserved { background: #3498db; }
        
        .severity-high { background: #e74c3c; }
        .severity-medium { background: #f39c12; }
        .severity-low { background: #27ae60; }
        
        .bulk-add-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .bulk-add-section textarea {
            width: 100%;
            height: 100px;
            font-family: 'Press Start 2P', cursive;
            font-size: 10px;
            padding: 8px;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            resize: vertical;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 11px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 11px;
        }
        
        .nav-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ff99cc;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-bottom: none;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
            font-size: 10px;
            margin-right: 5px;
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tab.active {
            background: #ff99cc;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        @media (max-width: 600px) {
            .stats-bar {
                flex-direction: column;
            }
            
            .giveaway-card {
                margin: 10px 0;
                padding: 10px;
            }
            
            .banned-word-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Admin Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></p>
    <a href="admin_logout.php" class="logout-btn">Logout</a>
</div>

<?php if ($success_message): ?>
    <div class="success-message">✅ <?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="error-message">❌ <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="nav-tabs">
    <div class="nav-tab active" onclick="showTab('giveaways')">Giveaways</div>
    <div class="nav-tab" onclick="showTab('banned-words')">Banned Words</div>
</div>

<!-- Giveaways Tab -->
<div id="giveaways-tab" class="tab-content active">
    <?php
    // Calculate stats
    $activeCount = 0;
    $expiredCount = 0;
    $totalEntries = 0;
    $totalWinners = 0;

    foreach ($giveaways as $g) {
        $endTime = strtotime($g['countdown_datetime']);
        $isActive = $endTime > $now;
        
        if ($isActive) {
            $activeCount++;
        } else {
            $expiredCount++;
        }
        
        $totalEntries += $g['total_entries'];
        if ($g['winners']) {
            $totalWinners += count(explode(', ', $g['winners']));
        }
    }
    ?>

    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-number"><?= count($giveaways) ?></span>
            Total Giveaways
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $activeCount ?></span>
            Active
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $expiredCount ?></span>
            Ended
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $totalEntries ?></span>
            Total Entries
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= $totalWinners ?></span>
            Winners Picked
        </div>
    </div>

    <h2>All Giveaways</h2>

    <?php if (empty($giveaways)): ?>
        <p style="text-align: center; color: #666;">No giveaways found.</p>
    <?php else: ?>
        <?php foreach ($giveaways as $g): ?>
            <?php
            $endTime = strtotime($g['countdown_datetime']);
            $isActive = $endTime > $now;
            $timeDiff = $endTime - $now;
            
            if ($isActive) {
                $status = 'active';
                $statusText = 'Active';
            } else {
                $status = 'ended';
                $statusText = 'Ended';
            }
            ?>
            
            <div class="giveaway-card <?= $status ?>">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="delete_giveaway_id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this giveaway? This action cannot be undone.')">Delete</button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="toggle_verify" value="1">
                    <input type="hidden" name="giveaway_id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $g['verified'] ? 0 : 1 ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="verify-btn <?= $g['verified'] ? 'verified' : 'unverified' ?>" 
                            style="position: absolute; top: 10px; right: 110px;">
                        <?= $g['verified'] ? 'Unverify' : 'Verify' ?>
                    </button>
                </form>
                
                <div class="giveaway-title">
                    <?= htmlspecialchars($g['title']) ?>
                    <span class="status-badge status-<?= $status ?>"><?= $statusText ?></span>
                </div>
                
                <div class="giveaway-info">
                    <strong>Code:</strong> <?= htmlspecialchars($g['slug']) ?> | 
                    <strong>Winners:</strong> <?= $g['winner_count'] ?> | 
                    <strong>Entries:</strong> <?= $g['unique_entries'] ?> unique (<?= $g['total_entries'] ?> total)
                </div>
                
                <div class="giveaway-info">
                    <strong>Ends:</strong> <?= date('M j, Y g:i A', strtotime($g['countdown_datetime'])) ?>
                    <?php if (!$isActive): ?>
                        <?php
                        $hoursAgo = abs(round($timeDiff / 3600));
                        if ($hoursAgo >= 24) {
                            $daysAgo = round($hoursAgo / 24);
                            echo "<span style='color: #666;'>({$daysAgo} day" . ($daysAgo != 1 ? 's' : '') . " ago)</span>";
                        } else {
                            echo "<span style='color: #666;'>({$hoursAgo} hour" . ($hoursAgo != 1 ? 's' : '') . " ago)</span>";
                        }
                        ?>
                    <?php endif; ?>
                </div>
                
                <div class="giveaway-info">
                    <strong>Management Code:</strong> <?= htmlspecialchars($g['management_code']) ?>
                </div>
                
                <?php if ($g['winners']): ?>
                    <div class="winners-info">
                        <strong>🏆 Winners:</strong> <?= htmlspecialchars($g['winners']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="giveaway-info">
                    <a href="/<?= htmlspecialchars($g['slug']) ?>" target="_blank" class="giveaway-link">
                        View Giveaway →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Banned Words Tab -->
<div id="banned-words-tab" class="tab-content">
    <div class="banned-words-section">
        <h2>Banned Words Management</h2>
        
        <p style="font-size: 10px; color: #666; margin-bottom: 20px;">
            Manage words that are filtered from giveaway titles, usernames, and slugs. 
            The system automatically normalizes text to detect bypass attempts.
        </p>
        
        <!-- Add Single Word -->
        <form method="post" class="banned-word-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="add_banned_word" value="1">
            
            <div>
                <label>Word:</label><br>
                <input type="text" name="word" placeholder="Enter word" required style="width: 150px;">
            </div>
            
            <div>
                <label>Category:</label><br>
                <select name="category">
                    <option value="profanity">Profanity</option>
                    <option value="spam">Spam</option>
                    <option value="inappropriate">Inappropriate</option>
                    <option value="reserved">Reserved</option>
                </select>
            </div>
            
            <div>
                <label>Severity:</label><br>
                <select name="severity">
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            
            <div>
                <button type="submit">Add Word</button>
            </div>
        </form>
        
        <!-- Bulk Add Section -->
        <div class="bulk-add-section">
            <h3 style="margin-top: 0; font-size: 12px;">Bulk Add Words</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="bulk_add_words" value="1">
                
                <textarea name="bulk_words" placeholder="Enter words, one per line..."></textarea>
                
                <div class="banned-word-form" style="margin-top: 10px;">
                    <div>
                        <label>Category:</label><br>
                        <select name="bulk_category">
                            <option value="profanity">Profanity</option>
                            <option value="spam">Spam</option>
                            <option value="inappropriate">Inappropriate</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Severity:</label><br>
                        <select name="bulk_severity">
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit">Add All Words</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Current Banned Words -->
        <h3 style="font-size: 12px; margin-top: 30px;">Current Banned Words (<?= count($banned_words) ?>)</h3>
        
        <?php if (empty($banned_words)): ?>
            <p style="color: #666; font-size: 10px;">No banned words configured.</p>
        <?php else: ?>
            <table class="banned-words-table">
                <thead>
                    <tr>
                        <th>Word</th>
                        <th>Category</th>
                        <th>Severity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banned_words as $word): ?>
                        <tr>
                            <td><?= htmlspecialchars($word['word']) ?></td>
                            <td>
                                <span class="category-badge category-<?= $word['category'] ?>">
                                    <?= ucfirst($word['category']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="category-badge severity-<?= $word['severity'] ?>">
                                    <?= ucfirst($word['severity']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="delete_banned_word" value="1">
                                    <input type="hidden" name="word_id" value="<?= $word['id'] ?>">
                                    <button type="submit" 
                                            style="background: #e74c3c; color: white; border: none; padding: 3px 6px; border-radius: 3px; cursor: pointer; font-size: 8px;"
                                            onclick="return confirm('Delete this banned word?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked nav tab
    event.target.classList.add('active');
}
</script>

</body>
</html>