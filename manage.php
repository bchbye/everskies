<?php
include 'db.php';
include 'content_filter.php'; // Include the content filter

$error = '';
$success = '';
$giveaway = null;

// Check if management code is provided in URL
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $management_code = trim($_GET['code']);
    
    $stmt = $pdo->prepare("SELECT * FROM giveaways WHERE management_code = ?");
    $stmt->execute([$management_code]);
    $giveaway = $stmt->fetch();
    
    if (!$giveaway) {
        $error = "Invalid management code.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Manual login with just management code
        $management_code = trim($_POST['management_code']);
        
        $stmt = $pdo->prepare("SELECT * FROM giveaways WHERE management_code = ?");
        $stmt->execute([$management_code]);
        $giveaway = $stmt->fetch();
        
        if (!$giveaway) {
            $error = "Invalid management code.";
        }
    }
    
    if (isset($_POST['start_winner_selection']) && $giveaway) {
        // Manual winner selection
        $stmt = $pdo->prepare("SELECT username FROM entries WHERE giveaway_id = ?");
        $stmt->execute([$giveaway['id']]);
        $allEntries = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($allEntries) > 0) {
            // Check if winners already exist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM winners WHERE giveaway_id = ? AND status = 'active'");
            $stmt->execute([$giveaway['id']]);
            $existingWinners = $stmt->fetchColumn();
            
            if ($existingWinners == 0) {
                // Pick winners using the same logic as auto-pick
                $uniqueUsers = array_unique($allEntries);
                $winnersToPick = min($giveaway['winner_count'], count($uniqueUsers));
                
                $winners = [];
                $remainingEntries = $allEntries;
                
                for ($i = 0; $i < $winnersToPick && count($remainingEntries) > 0; $i++) {
                    $randomIndex = array_rand($remainingEntries);
                    $winner = $remainingEntries[$randomIndex];
                    $winners[] = $winner;
                    
                    // Remove all entries from this winner to avoid duplicates
                    $remainingEntries = array_filter($remainingEntries, function($entry) use ($winner) {
                        return $entry !== $winner;
                    });
                    $remainingEntries = array_values($remainingEntries);
                }
                
                // Save winners to database
                if (count($winners) > 0) {
                    $stmt = $pdo->prepare("INSERT INTO winners (giveaway_id, username, position, status) VALUES (?, ?, ?, 'active')");
                    $stmt2 = $pdo->prepare("INSERT INTO winner_history (giveaway_id, username, position, action) VALUES (?, ?, ?, 'selected')");
                    
                    foreach ($winners as $position => $winner) {
                        $stmt->execute([$giveaway['id'], $winner, $position + 1]);
                        $stmt2->execute([$giveaway['id'], $winner, $position + 1]);
                    }
                    
                    // Update legacy winner field
                    $stmt = $pdo->prepare("UPDATE giveaways SET winner = ? WHERE id = ?");
                    $stmt->execute([$winners[0], $giveaway['id']]);
                    
                    $success = "Winners selected successfully!";
                }
            }
        }
    }
    
    if (isset($_POST['repick_winner']) && $giveaway) {
        // Repick specific winner
        $winner_id = (int)$_POST['winner_id'];
        $reason = trim($_POST['reason']);
        $custom_reason = trim($_POST['custom_reason']);
        
        // Use custom reason if "other" is selected
        if ($reason === 'other' && !empty($custom_reason)) {
            $reason = $custom_reason;
        }
        
        if (!empty($reason)) {
            // Get the winner details
            $stmt = $pdo->prepare("SELECT * FROM winners WHERE id = ? AND giveaway_id = ?");
            $stmt->execute([$winner_id, $giveaway['id']]);
            $oldWinner = $stmt->fetch();
            
            if ($oldWinner) {
                // Mark old winner as disqualified
                $stmt = $pdo->prepare("UPDATE winners SET status = 'disqualified', disqualified_reason = ?, disqualified_at = NOW() WHERE id = ?");
                $stmt->execute([$reason, $winner_id]);
                
                // Add to history
                $stmt = $pdo->prepare("INSERT INTO winner_history (giveaway_id, username, position, action, reason) VALUES (?, ?, ?, 'disqualified', ?)");
                $stmt->execute([$giveaway['id'], $oldWinner['username'], $oldWinner['position'], $reason]);
                
                // Pick new winner for that position
                $stmt = $pdo->prepare("SELECT username FROM entries WHERE giveaway_id = ?");
                $stmt->execute([$giveaway['id']]);
                $allEntries = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Get currently active winners to exclude them
                $stmt = $pdo->prepare("SELECT username FROM winners WHERE giveaway_id = ? AND status = 'active'");
                $stmt->execute([$giveaway['id']]);
                $currentWinners = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Remove current winners and disqualified winner from available entries
                $availableEntries = array_filter($allEntries, function($entry) use ($currentWinners, $oldWinner) {
                    return !in_array($entry, $currentWinners) && $entry !== $oldWinner['username'];
                });
                
                if (count($availableEntries) > 0) {
                    // Pick new winner
                    $newWinner = $availableEntries[array_rand($availableEntries)];
                    
                    // Insert new winner
                    $stmt = $pdo->prepare("INSERT INTO winners (giveaway_id, username, position, status) VALUES (?, ?, ?, 'active')");
                    $stmt->execute([$giveaway['id'], $newWinner, $oldWinner['position']]);
                    
                    // Add to history
                    $stmt = $pdo->prepare("INSERT INTO winner_history (giveaway_id, username, position, action) VALUES (?, ?, ?, 'selected')");
                    $stmt->execute([$giveaway['id'], $newWinner, $oldWinner['position']]);
                    
                    $success = "Winner repicked successfully! New winner: " . htmlspecialchars($newWinner);
                } else {
                    $error = "No available entries to pick a replacement winner.";
                }
            }
        } else {
            $error = "Please provide a reason for repicking the winner.";
        }
    }
    
    if (isset($_POST['add_entry']) && $giveaway) {
        // Add entry with content filter
        $username = trim($_POST['username']);
        if ($username) {
            // Validate username with content filter (no rate limiting)
            $validation = SmartContentFilter::validateUsername($pdo, $username);
            
            if ($validation === true) {
                $stmt = $pdo->prepare("INSERT INTO entries (giveaway_id, username) VALUES (?, ?)");
                $stmt->execute([$giveaway['id'], $username]);
                $success = "Entry added successfully!";
            } else {
                $error = $validation;
            }
        } else {
            $error = "Username is required.";
        }
        
        // Refresh giveaway data
        $stmt = $pdo->prepare("SELECT * FROM giveaways WHERE id = ?");
        $stmt->execute([$giveaway['id']]);
        $giveaway = $stmt->fetch();
    }
    
    if (isset($_POST['remove_entry']) && $giveaway) {
        // Remove entry
        $entry_id = $_POST['entry_id'];
        $stmt = $pdo->prepare("DELETE FROM entries WHERE id = ? AND giveaway_id = ?");
        $stmt->execute([$entry_id, $giveaway['id']]);
    }
    
    if (isset($_POST['update_link']) && $giveaway) {
        // Update ES link
        $es_link = trim($_POST['es_link']);
        $stmt = $pdo->prepare("UPDATE giveaways SET es_link = ? WHERE id = ?");
        $stmt->execute([$es_link, $giveaway['id']]);
        $giveaway['es_link'] = $es_link;
    }
}

// If we have a giveaway, fetch entries, winners, and history
if ($giveaway) {
    $stmt = $pdo->prepare("SELECT * FROM entries WHERE giveaway_id = ? ORDER BY id DESC");
    $stmt->execute([$giveaway['id']]);
    $entries = $stmt->fetchAll();
    
    // Fetch active winners
    $stmt = $pdo->prepare("SELECT * FROM winners WHERE giveaway_id = ? AND status = 'active' ORDER BY position");
    $stmt->execute([$giveaway['id']]);
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch winner history
    $stmt = $pdo->prepare("SELECT * FROM winner_history WHERE giveaway_id = ? ORDER BY created_at DESC");
    $stmt->execute([$giveaway['id']]);
    $winnerHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate giveaway status
    $now = time();
    $end = strtotime($giveaway['countdown_datetime']);
    $hasEnded = $end <= $now;
    $timeRemaining = $end - $now;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Giveaway - ES Giveaways</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #ffe6f0;
            color: #333;
            text-align: center;
            padding: 20px;
            cursor: url('http://www.rw-designer.com/cursor-extern.php?id=176131'), auto;
        }
        .box {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px auto;
            max-width: 600px;
        }
        h1, h2 { color: #ff3399; }
        input, button, select {
            font-family: 'Press Start 2P', cursive;
            padding: 8px;
            margin: 5px;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            background: #fff;
        }
        button {
            background: #ff3399;
            color: white;
            cursor: pointer;
        }
        button:hover { background: #e60073; }
        
        .entry {
            background: #fff0f5;
            margin: 5px 0;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ff99cc;
            display: inline-block;
            font-size: 14px;
            position: relative;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: 10px;
        }
        
        .error { 
            color: #c62828; 
            margin: 10px 0; 
            background: #ffebee;
            border: 2px solid #f44336;
            padding: 12px;
            border-radius: 4px;
            font-size: 11px;
        }
        .success { 
            color: #2e7d32; 
            margin: 10px 0; 
            background: #e8f5e8;
            border: 2px solid #4caf50;
            padding: 12px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        a {
            color: #ff3399;
            text-decoration: none;
        }
        a:hover {
            color: #e60073;
            text-decoration: underline;
        }
        
        .status {
            background: #ffeb3b;
            color: #333;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
        
        .status.ended {
            background: #4caf50;
            color: white;
        }
        
        .winner-display {
            background: #4caf50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        .winner-item {
            background: #e8f5e8;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid #4caf50;
        }
        
        .repick-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 10px;
        }
        
        .history-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
            font-size: 10px;
            text-align: left;
        }
        
        .history-disqualified {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .history-selected {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .manual-pick-btn {
            background: #28a745;
            padding: 15px 30px;
            font-size: 14px;
            margin: 20px 0;
        }
        
        .manual-pick-btn:hover {
            background: #218838;
        }
        
        .show-more-btn {
            background: #ff99cc;
            color: #333;
            font-size: 10px;
            padding: 5px 10px;
            margin: 10px 0;
        }
        
        .entries-container {
            max-height: none;
        }
    </style>
</head>
<body>

<?php if (!$giveaway): ?>
    <div class="box">
        <h1>Manage Giveaway</h1>
        <form method="post">
            <input type="hidden" name="login" value="1">
            <p>
                <input type="text" name="management_code" placeholder="Management Code" required>
            </p>
            <button type="submit">Access Giveaway</button>
        </form>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <p><a href="/">← Back to Home</a></p>
    </div>
<?php else: ?>
    <div class="box">
        <h1><?= htmlspecialchars($giveaway['title']) ?></h1>
        <p><a href="/<?= htmlspecialchars($giveaway['slug']) ?>" target="_blank">View Giveaway</a></p>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Giveaway Status -->
        <?php if ($hasEnded): ?>
            <div class="status ended">
                ✅ Giveaway has ended!
            </div>
            
            <?php if (count($winners) > 0): ?>
                <h2>🏆 Current Winners</h2>
                <?php foreach ($winners as $winner): ?>
                    <div class="winner-item">
                        <strong>#<?= $winner['position'] ?>: <?= htmlspecialchars($winner['username']) ?></strong>
                        
                        <div class="repick-section">
                            <form method="post">
                                <input type="hidden" name="repick_winner" value="1">
                                <input type="hidden" name="winner_id" value="<?= $winner['id'] ?>">
                                
                                <label>Reason for repicking:</label><br>
                                <select name="reason" onchange="toggleCustomReason(this, <?= $winner['id'] ?>)">
                                    <option value="">Select reason...</option>
                                    <option value="Alt Account/Multiple Accounts">Alt Account/Multiple Accounts</option>
                                    <option value="Invalid/Fake Account">Invalid/Fake Account</option>
                                    <option value="Rule Violation">Rule Violation</option>
                                    <option value="Account Inactive">Account Inactive</option>
                                    <option value="other">Other (specify)</option>
                                </select>
                                
                                <input type="text" name="custom_reason" id="custom_reason_<?= $winner['id'] ?>" placeholder="Custom reason..." style="display: none;">
                                
                                <button type="submit" onclick="return confirm('Are you sure you want to repick this winner? This action cannot be undone.')">
                                    Repick Winner
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php if ($giveaway['winner_selection_mode'] === 'manual'): ?>
                    <form method="post">
                        <input type="hidden" name="start_winner_selection" value="1">
                        <button type="submit" class="manual-pick-btn">🎯 Start Winner Selection</button>
                    </form>
                <?php else: ?>
                    <div class="status">
                        ⚠️ Giveaway ended but no winners picked yet (needs entries)
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="status">
                ⏳ Giveaway is active - Ends: <?= date('M j, Y g:i A', strtotime($giveaway['countdown_datetime'])) ?>
                <br>🏆 Will pick <?= $giveaway['winner_count'] ?> winner<?= $giveaway['winner_count'] > 1 ? 's' : '' ?>
                <br><?= $giveaway['winner_selection_mode'] === 'auto' ? '⚡ Automatic selection' : '🎯 Manual selection' ?>
            </div>
        <?php endif; ?>
        
        <!-- Winner History -->
        <?php if (count($winnerHistory) > 0): ?>
            <h2>📋 Winner History</h2>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($winnerHistory as $history): ?>
                    <div class="history-item history-<?= $history['action'] ?>">
                        <?php if ($history['action'] === 'selected'): ?>
                            ✅ <strong><?= htmlspecialchars($history['username']) ?></strong> selected as winner #<?= $history['position'] ?>
                        <?php else: ?>
                            ❌ <strong><?= htmlspecialchars($history['username']) ?></strong> disqualified from position #<?= $history['position'] ?>
                            <?php if ($history['reason']): ?>
                                <br><small>Reason: <?= htmlspecialchars($history['reason']) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                        <br><small><?= date('M j, Y g:i A', strtotime($history['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2>Update Everskies Link</h2>
        <form method="post">
            <input type="hidden" name="update_link" value="1">
            <input type="url" name="es_link" value="<?= htmlspecialchars($giveaway['es_link']) ?>" style="width: 80%;">
            <button type="submit">Update</button>
        </form>
        
        <h2>Entries (<?= count($entries) ?>)</h2>
        
        <form method="post">
            <input type="hidden" name="add_entry" value="1">
            <input type="text" name="username" placeholder="Username" required>
            <button type="submit">Add Entry</button>
        </form>
        
        <div class="entries-container">
            <?php if (empty($entries)): ?>
                <p>No entries yet.</p>
            <?php else: ?>
                <div id="entries-display">
                    <?php 
                    $showLimit = 10;
                    $totalEntries = count($entries);
                    $entriesToShow = array_slice($entries, 0, $showLimit);
                    ?>
                    
                    <?php foreach ($entriesToShow as $entry): ?>
                        <div class="entry">
                            <?= htmlspecialchars($entry['username']) ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="remove_entry" value="1">
                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                <button type="submit" class="remove-btn" onclick="return confirm('Remove entry?')">X</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($totalEntries > $showLimit): ?>
                        <div id="hidden-entries" style="display: none;">
                            <?php foreach (array_slice($entries, $showLimit) as $entry): ?>
                                <div class="entry">
                                    <?= htmlspecialchars($entry['username']) ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="remove_entry" value="1">
                                        <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                        <button type="submit" class="remove-btn" onclick="return confirm('Remove entry?')">X</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="show-more-btn" id="showMoreBtn" onclick="toggleEntries()">
                            Show <?= $totalEntries - $showLimit ?> More
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <p><a href="/manage">← Manage Another Giveaway</a></p>
    </div>
<?php endif; ?>

<script>
function toggleEntries() {
    const hiddenEntries = document.getElementById('hidden-entries');
    const showMoreBtn = document.getElementById('showMoreBtn');
    
    if (hiddenEntries.style.display === 'none') {
        hiddenEntries.style.display = 'block';
        showMoreBtn.textContent = 'Show Less';
    } else {
        hiddenEntries.style.display = 'none';
        showMoreBtn.textContent = 'Show <?= $totalEntries - $showLimit ?> More';
    }
}

function toggleCustomReason(select, winnerId) {
    const customReasonField = document.getElementById('custom_reason_' + winnerId);
    if (select.value === 'other') {
        customReasonField.style.display = 'inline';
        customReasonField.required = true;
    } else {
        customReasonField.style.display = 'none';
        customReasonField.required = false;
    }
}
</script>

</body>
</html>