<?php
// 2. CREATE admin_dashboard.php
include 'admin_auth.php'; // We'll create this file too

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

// Fetch all giveaways (active and expired)
$stmt = $pdo->prepare("
    SELECT g.*, 
           COUNT(DISTINCT e.username) as unique_entries,
           COUNT(e.id) as total_entries,
           GROUP_CONCAT(DISTINCT w.username ORDER BY w.position SEPARATOR ', ') as winners
    FROM giveaways g
    LEFT JOIN entries e ON g.id = e.giveaway_id
    LEFT JOIN winners w ON g.id = w.giveaway_id
    GROUP BY g.id
    ORDER BY g.countdown_datetime DESC
");
$stmt->execute();
$giveaways = $stmt->fetchAll();

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

        @media (max-width: 600px) {
            .stats-bar {
                flex-direction: column;
            }
            
            .giveaway-card {
                margin: 10px 0;
                padding: 10px;
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

<h2 style="color: #ff3399; text-align: center;">All Giveaways</h2>

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
                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this giveaway? This action cannot be undone.')">Delete</button>
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

</body>
</html>