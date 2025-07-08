<?php
include 'db.php';

header('Content-Type: application/json');

$giveaway_id = $_GET['giveaway_id'] ?? null;

if (!$giveaway_id) {
    echo json_encode(['success' => false, 'error' => 'Missing giveaway ID']);
    exit;
}

function pickMultipleWinners($pdo, $giveaway_id, $winner_count) {
    // Get all entries for this giveaway
    $stmt = $pdo->prepare("SELECT username FROM entries WHERE giveaway_id = ?");
    $stmt->execute([$giveaway_id]);
    $allEntries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($allEntries) == 0) {
        return [];
    }
    
    // If we need more winners than entries, just pick all unique users
    $uniqueUsers = array_unique($allEntries);
    if (count($uniqueUsers) <= $winner_count) {
        return $uniqueUsers;
    }
    
    $winners = [];
    $remainingEntries = $allEntries;
    
    for ($i = 0; $i < $winner_count && count($remainingEntries) > 0; $i++) {
        // Pick a random winner
        $randomIndex = array_rand($remainingEntries);
        $winner = $remainingEntries[$randomIndex];
        $winners[] = $winner;
        
        // Remove all entries from this winner to avoid duplicates
        $remainingEntries = array_filter($remainingEntries, function($entry) use ($winner) {
            return $entry !== $winner;
        });
        
        // Reindex array
        $remainingEntries = array_values($remainingEntries);
    }
    
    return $winners;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM giveaways WHERE id = ?");
    $stmt->execute([$giveaway_id]);
    $giveaway = $stmt->fetch();
    
    if (!$giveaway) {
        echo json_encode(['success' => false, 'error' => 'Giveaway not found']);
        exit;
    }
    
    // Check if winners already exist
    $stmt = $pdo->prepare("SELECT username, position FROM winners WHERE giveaway_id = ? AND status = 'active' ORDER BY position");
    $stmt->execute([$giveaway_id]);
    $existingWinners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($existingWinners) > 0) {
        // Winners already picked
        echo json_encode([
            'success' => true, 
            'winners' => $existingWinners,
            'winner_count' => $giveaway['winner_count']
        ]);
        exit;
    }
    
    // Check if giveaway has ended
    $now = time();
    $end = strtotime($giveaway['countdown_datetime']);
    
    if ($end <= $now) {
        // ONLY auto-pick if this is an AUTO mode giveaway
        if (isset($giveaway['winner_selection_mode']) && $giveaway['winner_selection_mode'] === 'auto') {
            $winners = pickMultipleWinners($pdo, $giveaway_id, $giveaway['winner_count']);
            
            if (count($winners) > 0) {
                // Insert winners into database with status
                $stmt = $pdo->prepare("INSERT INTO winners (giveaway_id, username, position, status) VALUES (?, ?, ?, 'active')");
                $stmt2 = $pdo->prepare("INSERT INTO winner_history (giveaway_id, username, position, action) VALUES (?, ?, ?, 'selected')");
                
                foreach ($winners as $position => $winner) {
                    $stmt->execute([$giveaway_id, $winner, $position + 1]);
                    $stmt2->execute([$giveaway_id, $winner, $position + 1]);
                }
                
                // Also update the legacy winner field for compatibility
                $stmt = $pdo->prepare("UPDATE giveaways SET winner = ? WHERE id = ?");
                $stmt->execute([$winners[0], $giveaway_id]);
                
                // Return the winners
                $winnerData = [];
                foreach ($winners as $position => $winner) {
                    $winnerData[] = [
                        'username' => $winner,
                        'position' => $position + 1
                    ];
                }
                
                echo json_encode([
                    'success' => true, 
                    'winners' => $winnerData,
                    'winner_count' => $giveaway['winner_count']
                ]);
                exit;
            }
        }
        // If manual mode, don't auto-pick - just return empty winners array
    }
    
    echo json_encode([
        'success' => true, 
        'winners' => [],
        'winner_count' => $giveaway['winner_count']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>