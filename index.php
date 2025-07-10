<?php
session_start();
include 'db.php';

// Get the requested path, remove query string
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    // Show welcome page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <title>ES Giveaways</title>
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
        
        h1 {
          color: #ff3399;
          margin-bottom: 30px;
          font-size: 2rem;
        }
        
        .main-box {
          background: #fff0f5;
          border: 2px solid #ff99cc;
          border-radius: 8px;
          padding: 20px;
          margin: 20px auto;
          max-width: 500px;
          box-shadow: 0 0 10px rgba(255, 51, 153, 0.2);
        }
        
        .create-btn {
          display: inline-block;
          margin: 20px 0;
          padding: 15px 30px;
          background: #ff3399;
          color: white;
          text-decoration: none;
          border-radius: 6px;
          font-size: 14px;
          transition: background 0.3s ease;
        }
        
        .create-btn:hover {
          background: #e60073;
          transform: translateY(-2px);
        }
        
        h3 {
          color: #ff3399;
          margin: 20px 0 15px 0;
        }
        
        .giveaways-list {
          list-style: none;
          padding: 0;
          margin: 0;
          text-align: left;
        }
        
        .giveaway-item {
          background: #fff;
          border: 1px solid #ffccdd;
          border-radius: 4px;
          padding: 12px;
          margin: 8px 0;
          transition: background-color 0.3s ease;
          font-size: 12px;
        }
        
        .giveaway-item:hover {
          background-color: #ffe6f0;
        }
        
        .giveaway-item a {
          color: #ff3399;
          text-decoration: none;
          font-weight: bold;
        }
        
        .giveaway-item a:hover {
          color: #e60073;
          text-decoration: underline;
        }
        
        .host-name {
          color: #a30057;
          font-size: 10px;
        }
        
        .no-giveaways {
          color: #666;
          font-style: italic;
          font-size: 12px;
          padding: 20px;
        }
        
        .admin-login {
          position: fixed;
          bottom: 20px;
          right: 20px;
          background: #666;
          color: white;
          padding: 8px 12px;
          border-radius: 4px;
          text-decoration: none;
          font-size: 10px;
          opacity: 0.7;
          transition: opacity 0.3s ease;
        }
        
        .admin-login:hover {
          opacity: 1;
          background: #555;
        }
        
        .subtitle {
          color: #666;
          font-size: 12px;
          margin-bottom: 20px;
        }
		h1 {
    color: #ff3399;
    margin-bottom: 5px;
    font-size: 2rem;
    border-bottom: 2px solid #ff99cc;
    padding-bottom: 8px;
    display: inline-block;
}

.tagline {
    color: #ff99cc;
    font-size: 11px;
    margin-top: 10px;
    margin-bottom: 20px;
    letter-spacing: 1px;
}
h1.form-title {
    border-bottom: none;
}
.logo-link {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

.logo-link:hover h1 {
    color: #e60073; /* slightly darker pink on hover */
}
  </style>
</head>
<body>

<a href="/" class="logo-link">
    <h1>ES Giveaways</h1>
    <div class="tagline">countdown + winner picker</div>
</a>
    
    <div class="main-box">
        <p class="subtitle">Create and manage your Everskies giveaways easily with automatic winner selection!</p>
        
        <a href="/create" class="create-btn">🎁 Create New Giveaway</a>
        
        <h3>Verified Giveaways</h3>
        
        <?php
        // Fetch active giveaways (future countdown)
        $stmt = $pdo->prepare("
SELECT giveaways.slug, giveaways.title, hosts.username, giveaways.countdown_datetime, giveaways.winner_count
FROM giveaways
LEFT JOIN hosts ON giveaways.host_id = hosts.id
WHERE giveaways.countdown_datetime > UTC_TIMESTAMP() AND giveaways.verified = 1
ORDER BY giveaways.countdown_datetime ASC
");
        $stmt->execute();
        $activeGiveaways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <?php if (empty($activeGiveaways)): ?>
            <div class="no-giveaways">
                No verified giveaways at the moment.<br>
                Be the first to create one! 🚀
            </div>
        <?php else: ?>
            <ul class="giveaways-list">
              <?php foreach ($activeGiveaways as $g): ?>
                <li class="giveaway-item">
                    <a href="/<?= htmlspecialchars($g['slug']) ?>">
                        <?= htmlspecialchars($g['title']) ?>
                    </a>
                    <?php if ($g['username']): ?>
                        <br><span class="host-name">by <?= htmlspecialchars($g['username']) ?></span>
                    <?php endif; ?>
                    <br><span class="host-name">🏆 <?= $g['winner_count'] ?> winner<?= $g['winner_count'] > 1 ? 's' : '' ?></span>
                    <br><span class="host-name">Ends: <?= date('M j, g:i A', strtotime($g['countdown_datetime'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <p style="margin-top: 30px;">
            <a href="/manage" style="color: #ff3399; text-decoration: none; font-size: 12px;">
                Already have a giveaway? Manage it here →
            </a>
        </p>
    </div>

    <!-- Admin login for legacy access -->
    <a href="/admin.php" class="admin-login">Admin</a>

    </body>
    </html>
    <?php
    exit;
}

// If we reach here, treat $uri as giveaway slug
$slug = $uri;

// Fetch giveaway
$stmt = $pdo->prepare("SELECT * FROM giveaways WHERE slug = ?");
$stmt->execute([$slug]);
$giveaway = $stmt->fetch();

if (!$giveaway) {
    echo "Giveaway not found.";
    exit;
}

// Fetch host username
$stmtHost = $pdo->prepare("SELECT username FROM hosts WHERE id = ?");
$stmtHost->execute([$giveaway['host_id']]);
$host = $stmtHost->fetch();

$hostUsername = $host ? $host['username'] : 'Unknown';

// Fetch usernames with counts of entries, order by count DESC
$stmt = $pdo->prepare("
SELECT username, COUNT(*) AS entry_count, MIN(id) as first_entry_id
FROM entries
WHERE giveaway_id = ?
GROUP BY username
ORDER BY entry_count DESC, first_entry_id ASC
");
$stmt->execute([$giveaway['id']]);
$entriesWithCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all entries usernames (including duplicates) for winner picking
$stmt = $pdo->prepare("SELECT username FROM entries WHERE giveaway_id = ? ORDER BY RAND()");
$stmt->execute([$giveaway['id']]);
$allEntries = $stmt->fetchAll(PDO::FETCH_COLUMN);

$uniqueUsers = count($entriesWithCounts);
$totalEntries = count($allEntries);
$extraEntries = $totalEntries - $uniqueUsers;

$now = time();
$end = strtotime($giveaway['countdown_datetime']);
$remaining = $end - $now;

/* Check if giveaway is expired (3 days after ending)
$expirySeconds = 3 * 24 * 3600;
$timeSinceEnded = -$remaining;
$isExpired = $timeSinceEnded > $expirySeconds;

if ($isExpired) {
    // Delete giveaway and all related data
    $stmt = $pdo->prepare("DELETE FROM winners WHERE giveaway_id = ?");
    $stmt->execute([$giveaway['id']]);
    $stmt = $pdo->prepare("DELETE FROM entries WHERE giveaway_id = ?");
    $stmt->execute([$giveaway['id']]);
    $stmt = $pdo->prepare("DELETE FROM giveaways WHERE id = ?");
    $stmt->execute([$giveaway['id']]);

    echo "<p style='color:#ff3399; font-family: Press Start 2P, cursive; font-weight:bold;'>This giveaway has expired and has been deleted.</p>";
    exit;
}


// Auto pick winners if countdown ended and no winners yet

if ($remaining <= 0 && count($allEntries) > 0) {
    // STRICT CHECK: Only auto-pick if explicitly set to 'auto' mode
    if (isset($giveaway['winner_selection_mode']) && $giveaway['winner_selection_mode'] === 'auto') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM winners WHERE giveaway_id = ? AND status = 'active'");
        $stmt->execute([$giveaway['id']]);
        $winnersExist = $stmt->fetchColumn() > 0;
        
        // ADDITIONAL CHECK: Only pick if no winners exist AND giveaway actually ended
        if (!$winnersExist && $remaining <= -60) { // Wait 1 minute after end time
            // Pick multiple winners
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
                
                // Update legacy winner field for compatibility
                $stmt = $pdo->prepare("UPDATE giveaways SET winner = ? WHERE id = ?");
                $stmt->execute([$winners[0], $giveaway['id']]);
                $giveaway['winner'] = $winners[0];
            }
        }
    }
    // If manual mode, do nothing - winners must be picked via manage.php
}
*/

// Fetch active winners for display
$stmt = $pdo->prepare("SELECT username, position FROM winners WHERE giveaway_id = ? AND status = 'active' ORDER BY position");
$stmt->execute([$giveaway['id']]);
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch winner history for transparency (only show disqualifications)
$stmt = $pdo->prepare("SELECT * FROM winner_history WHERE giveaway_id = ? AND action = 'disqualified' ORDER BY created_at DESC");
$stmt->execute([$giveaway['id']]);
$disqualificationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if giveaway ended but no winners yet (manual mode)
/*
$needsManualPick = false;
if ($remaining <= 0 && count($winners) == 0 && count($allEntries) > 0 && $giveaway['winner_selection_mode'] === 'manual') {
    $needsManualPick = true;
}
*/
$needsManualPick = false;
if ($remaining <= 0 && count($winners) == 0 && $giveaway['winner_selection_mode'] === 'manual') {
    $needsManualPick = true;
}
?>


<!DOCTYPE html>
<html>
<head>
 <title><?= htmlspecialchars($giveaway['title']) ?> - ES Giveaways</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

body {
  font-family: 'Press Start 2P', cursive;
  background-color: #ffe6f0;
  color: #333;
  text-align: center;
  padding: 30px 15px;
  margin: 0;
  cursor: url('http://www.rw-designer.com/cursor-extern.php?id=176131'), auto;
}

h1 {
  color: #ff3399;
  font-size: 1.8rem;
  margin-bottom: 20px;
}

ul {
  list-style: none;
  padding: 0;
  max-width: 320px;
  margin: 0 auto 25px;
  text-align: left;
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #ff99cc;
  border-radius: 6px;
  background-color: #fff0f5;
  box-sizing: border-box;
  transition: box-shadow 0.3s ease;
}

ul:hover {
  box-shadow: 0 0 8px rgba(255, 51, 153, 0.6);
}

ul::-webkit-scrollbar {
  width: 8px;
}

ul::-webkit-scrollbar-track {
  background: #fff0f5;
  border-radius: 6px;
}

ul::-webkit-scrollbar-thumb {
  background-color: #ff3399;
  border-radius: 6px;
  border: 2px solid #fff0f5;
}

ul::-webkit-scrollbar-thumb:hover {
  background-color: #e60073;
}

ul {
  scrollbar-width: thin;
  scrollbar-color: #ff3399 #fff0f5;
}

li {
  background: #fff;
  border-bottom: 1px solid #ffccdd;
  padding: 8px 12px;
  font-size: 14px;
  color: #a30057;
  word-break: break-word;
  transition: background-color 0.3s ease;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

li:last-child {
  border-bottom: none;
}

li:hover {
  background-color: #ffe6f0;
  cursor: default;
}

li.winner-highlight {
  background-color: #ffe6f0 !important;
  transform: scale(1.02);
  box-shadow: 0 0 15px rgba(255, 51, 153, 1);
  animation: winnerGlow 2s infinite alternate;
}

/* Single winner gets pink border */
li.winner-single {
  border-left: 5px solid #ff3399; /* Pink for single winner */
}

/* Multiple winners get medal colors */
li.winner-1 {
  border-left: 5px solid #ffd700; /* Gold for 1st place */
}

li.winner-2 {
  border-left: 5px solid #c0c0c0; /* Silver for 2nd place */
}

li.winner-3 {
  border-left: 5px solid #cd7f32; /* Bronze for 3rd place */
}

li.winner-other {
  border-left: 5px solid #ff3399; /* Pink for 4th+ place winners */
}

@keyframes winnerGlow {
  0% { box-shadow: 0 0 15px rgba(255, 51, 153, 0.8); }
  100% { box-shadow: 0 0 25px rgba(255, 51, 153, 1); }
}

#countdown {
  font-size: 20px;
  margin-top: 15px;
  color: #ff3399;
  font-weight: bold;
  text-shadow: 0 0 5px #ff99cc;
  animation: pulse 2s infinite ease-in-out;
}

#winners {
  font-size: 20px;
  font-weight: bold;
  margin-top: 25px;
  color: #ff3399;
  text-shadow: 1px 1px 5px #fff;
  animation: glow 3s infinite alternate;
  line-height: 1.8;
}

.winner-item {
  display: block;
  margin: 5px 0;
}

.winner-position {
  font-size: 16px;
  color: #a30057;
}

#endtime {
  font-size: 20px;
  margin-top: 15px;
  color: #ff3399;
  font-weight: bold;
  text-shadow: 0 0 5px #ff99cc;
  font-family: 'Press Start 2P', cursive;
  animation: pulse 2s infinite ease-in-out;
}

@keyframes pulse {
  0%, 100% { text-shadow: 0 0 5px #ff99cc; }
  50% { text-shadow: 0 0 15px #ff3399; }
}

@keyframes glow {
  0% { text-shadow: 1px 1px 5px #fff; }
  100% { text-shadow: 2px 2px 15px #ff3399; }
}

@media (max-width: 400px) {
  ul {
    max-width: 90vw;
    max-height: 200px;
  }
  h1 {
    font-size: 1.4rem;
  }
  #countdown {
    font-size: 18px;
  }
  #winners {
    font-size: 18px;
  }
}

.giveaway-stats {
  background: #fff0f5;
  border: 2px solid #ff99cc;
  border-radius: 8px;
  display: inline-block;
  text-align: left;
  padding: 10px 15px;
  margin: 20px 0;
  font-size: 14px;
  color: #a30057;
  box-shadow: 0 0 10px rgba(255, 51, 153, 0.2);
  font-family: 'Press Start 2P', cursive;
}

.giveaway-stats h3 {
  margin-top: 0;
  margin-bottom: 10px;
  color: #ff3399;
  font-size: 16px;
}

.multiplier-badge {
  background: #ff3399;
  color: white;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 10px;
  font-weight: bold;
}

.winner-info {
  font-size: 12px;
  color: #666;
  margin-top: 10px;
}
.roulette-message {
  font-size: 18px;
  color: #ff3399;
  font-weight: bold;
  margin: 20px 0;
  text-shadow: 0 0 10px #ff99cc;
  animation: pulse 1s infinite ease-in-out;
}
.transparency-section {
  background: #fff0f5;
  border: 2px solid #ff99cc;
  border-radius: 8px;
  padding: 15px;
  margin: 20px auto;
  max-width: 400px;
  font-size: 12px;
  color: #a30057;
  text-align: left;
  box-shadow: 0 0 10px rgba(255, 51, 153, 0.2);
  font-family: 'Press Start 2P', cursive;
}

.transparency-section h4 {
  color: #ff3399;
  font-size: 14px;
  margin: 0 0 15px 0;
  text-align: center;
}

.transparency-item {
  margin: 10px 0;
  padding: 10px;
  background: #fff;
  border-radius: 6px;
  border-left: 4px solid #ff3399;
  font-size: 11px;
  line-height: 1.6;
}

.transparency-item strong {
  color: #ff3399;
}


#manual-waiting {
  font-size: 20px;
  font-weight: bold;
  margin-top: 25px;
  color: #ff3399;
  text-shadow: 1px 1px 5px #fff;
  animation: glow 3s infinite alternate;
  line-height: 1.8;
}

h1 {
    color: #ff3399;
    margin-bottom: 5px;
    font-size: 2rem;
    border-bottom: 2px solid #ff99cc;
    padding-bottom: 8px;
    display: inline-block;
}

.tagline {
    color: #ff99cc;
    font-size: 11px;
    margin-top: 10px;
    margin-bottom: 20px;
    letter-spacing: 1px;
}
h1.form-title {
    border-bottom: none;
}
.logo-link {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

.logo-link:hover h1 {
    color: #e60073; /* slightly darker pink on hover */
}
.giveaway-container {
    background: #fff0f5;
    border: 2px solid #ff99cc;
    border-radius: 8px;
    padding: 20px;
    margin: 20px auto;
    max-width: 90vw; /* Use 90% of viewport width */
    min-width: 300px; /* Smaller minimum for mobile */
    width: fit-content;
    box-shadow: 0 0 10px rgba(255, 51, 153, 0.2);
}

@media (max-width: 400px) {
    .giveaway-container {
        min-width: 280px; /* Even smaller on very small screens */
        padding: 15px; /* Less padding on mobile */
        margin: 10px auto; /* Less margin on mobile */
    }
}
  </style>
</head>
<body>

<a href="/" class="logo-link">
    <h1>ES Giveaways</h1>
    <div class="tagline">countdown + winner picker</div>
</a>

<div class="giveaway-container">
<h1 class="form-title"><?= htmlspecialchars($giveaway['title']) ?></h1>

<!-- Add Everskies link here -->
<?php if (!empty($giveaway['es_link'])): ?>
<!--<p style="margin: 15px 0; font-size: 12px;"> -->
<p style="margin: 15px 0; font-size: 12px; background: #ffe6f0; padding: 8px; border-radius: 4px; border-left: 3px solid #ff99cc;">
  📌 <a href="<?= htmlspecialchars($giveaway['es_link']) ?>" target="_blank" 
        style="color: #333; text-decoration: none; font-weight: bold;">
    View Original Post on Everskies →
  </a>
</p>
<?php endif; ?>


<!-- COUNTDOWN OR WINNERS -->
<?php if (count($winners) > 0): ?>
  <div id="winners">
    <?php if (count($winners) == 1): ?>
      🎉 Winner: <?= htmlspecialchars($winners[0]['username']) ?> 🎉
    <?php else: ?>
      🎉 Winners: 🎉
      <?php foreach ($winners as $winner): ?>
        <span class="winner-item">
          <span class="winner-position">#<?= $winner['position'] ?>:</span> <?= htmlspecialchars($winner['username']) ?>
        </span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <!-- Show transparency info if there were any disqualifications -->
  <?php if (count($disqualificationHistory) > 0): ?>
    <div class="transparency-section">
      <h4>ℹ️ Winner Updates</h4>
      <?php foreach ($disqualificationHistory as $history): ?>
        <div class="transparency-item">
          • Previous winner <strong><?= htmlspecialchars($history['username']) ?></strong> was disqualified from position #<?= $history['position'] ?>
          <?php if ($history['reason']): ?>
            <br><small>Reason: <?= htmlspecialchars($history['reason']) ?></small>
          <?php endif; ?>
        <br><small class="timestamp" data-utc="<?= $history['created_at'] ?>"></small>

<script>
document.querySelectorAll('.timestamp').forEach(function(el) {
    const utcTime = new Date(el.dataset.utc + ' UTC');
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    };
    el.textContent = utcTime.toLocaleDateString('en-US', options);
});
</script>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
<?php elseif ($needsManualPick): ?>
  <!-- Show waiting for manual pick message -->
  <div id="manual-waiting">
    ⏳ Giveaway Ended ⏳<br>
    <?php if (count($allEntries) > 0): ?>
      Waiting for host to trigger winner selection...
    <?php else: ?>
      Waiting for host to add entries and trigger winner selection...
    <?php endif; ?>
  </div>
  
<?php else: ?>
  <div id="countdown"></div>
  <?php
  $utc = new DateTime($giveaway['countdown_datetime'], new DateTimeZone('UTC'));
  $utcFormatted = $utc->format('F j, Y');
  ?>
  <div id="endtime"><?= htmlspecialchars($utcFormatted) ?></div>
<?php endif; ?>

<h3>Entries:</h3>

<?php if ($remaining > 0): ?>
  <p style="font-size: 12px; color: #ff3399; font-family: 'Press Start 2P', cursive; margin-top: 10px;">
    ⏳ This list updates as the host adds new entries.
  </p>
  <!--<div class="winner-info">
    🏆 This giveaway will pick <?= $giveaway['winner_count'] ?> winner<?= $giveaway['winner_count'] > 1 ? 's' : '' ?>!
  </div>!-->
<?php endif; ?>

<div id="roulette-message" class="roulette-message" style="display: none;">
  🎰 Selecting Winner<?= $giveaway['winner_count'] > 1 ? 's' : '' ?>... 🎰
</div>

<!-- ENTRIES LIST -->
<?php if (count($entriesWithCounts) > 0): 
    // Sort entries so winners appear first, in position order
    $sortedEntries = [];
    $nonWinners = [];
    
    // Create a lookup array for winners
    $winnerLookup = [];
    foreach ($winners as $winner) {
        $winnerLookup[$winner['username']] = $winner['position'];
    }
    
    // Separate winners and non-winners
    foreach ($entriesWithCounts as $entry) {
        if (isset($winnerLookup[$entry['username']])) {
            $entry['winner_position'] = $winnerLookup[$entry['username']];
            $sortedEntries[] = $entry;
        } else {
            $nonWinners[] = $entry;
        }
    }
    
    // Sort winners by position
    usort($sortedEntries, function($a, $b) {
        return $a['winner_position'] - $b['winner_position'];
    });
    
    // Combine winners first, then non-winners
    $finalEntries = array_merge($sortedEntries, $nonWinners);
?>
  <ul id="entries-list">
    <?php foreach ($finalEntries as $entry): ?>
      <li data-username="<?= htmlspecialchars($entry['username']) ?>"
          <?php 
          // Check if this user is a winner and add appropriate class
          $winnerPosition = null;
          foreach ($winners as $winner) {
              if ($winner['username'] === $entry['username']) {
                  $winnerPosition = $winner['position'];
                  break;
              }
          }
          
          if ($winnerPosition !== null) {
              echo 'class="winner-highlight ';
              if ($giveaway['winner_count'] == 1) {
                  echo 'winner-single'; // Pink for single winner
              } else {
                  // Multiple winners - use medal colors
                  if ($winnerPosition == 1) {
                      echo 'winner-1'; // Gold
                  } elseif ($winnerPosition == 2) {
                      echo 'winner-2'; // Silver  
                  } elseif ($winnerPosition == 3) {
                      echo 'winner-3'; // Bronze
                  } else {
                      echo 'winner-other'; // Pink for 4th+
                  }
              }
              echo '"';
          }
          ?>>
        <?= htmlspecialchars($entry['username']) ?>
        <?php if ($entry['entry_count'] > 1): ?>
          <span class="multiplier-badge">x<?= $entry['entry_count'] ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p>No entries yet.</p>
<?php endif; ?>

<!-- GIVEAWAY STATS -->
<?php if (count($entriesWithCounts) > 0): ?>
<div class="giveaway-stats">
  <h3>🎯 Giveaway Stats</h3>
  <p>👥 Unique Users: <?= $uniqueUsers ?></p>
  <p>➕ Extra Entries: <?= $extraEntries ?></p>
  <p>🗳️ Total Entries: <?= $totalEntries ?></p>
  <p>🏆 Winners: <?= $giveaway['winner_count'] ?></p>
</div>
<?php endif; ?>


<!-- MORE GIVEAWAYS BUTTON -->
<p style="margin-top: 30px;">
  <a href="/" style="display: inline-block; padding: 10px 20px; background: #ff3399; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; transition: background 0.3s ease;" onmouseover="this.style.background='#e60073'" onmouseout="this.style.background='#ff3399'">
    🎁 More Giveaways
  </a>
</p>
</div>
<!-- JAVASCRIPT -->
<?php if (count($winners) > 0): ?>
  <script>
    // Winners are already highlighted by PHP, just scroll to top
    document.addEventListener('DOMContentLoaded', function() {
      const entriesList = document.getElementById('entries-list');
      if (entriesList) {
        entriesList.scrollTop = 0;
      }
    });
  </script>
<?php elseif ($needsManualPick): ?>
  <script>
    // For manual mode - check periodically if winners have been picked
    function checkForManualWinners() {
      fetch('check_winner.php?giveaway_id=<?= $giveaway['id'] ?>')
        .then(response => response.json())
        .then(data => {
          if (data.winners && data.winners.length > 0) {
            // Reload page to show winners
            location.reload();
          } else {
            // Check again in 10 seconds
            setTimeout(checkForManualWinners, 10000);
          }
        })
        .catch(error => {
          console.error('Error checking winners:', error);
          setTimeout(checkForManualWinners, 10000);
        });
    }
    
    // Start checking for manual winners
    checkForManualWinners();
  </script>
<?php else: ?>
  <script>
let remaining = <?= max(0, $remaining) ?>;
const giveawayHasEnded = <?= $remaining <= 0 ? 'true' : 'false' ?>;
const hasWinners = <?= count($winners) > 0 ? 'true' : 'false' ?>;
const winnerCount = <?= $giveaway['winner_count'] ?>;
const isManualMode = <?= $giveaway['winner_selection_mode'] === 'manual' ? 'true' : 'false' ?>;

function formatTime(seconds) {
  const days = Math.floor(seconds / (3600 * 24));
  seconds %= 3600 * 24;
  const hours = Math.floor(seconds / 3600);
  seconds %= 3600;
  const minutes = Math.floor(seconds / 60);
  seconds %= 60;
  return `${days}d ${hours}h ${minutes}m ${seconds}s`;
}

const cdEl = document.getElementById('countdown');
let timer;

function updateCountdown() {
  if (remaining <= 0) {
    cdEl.textContent = "Giveaway ended!";
    clearInterval(timer);
    
    if (!hasWinners) {
      if (isManualMode) {
        // Show manual waiting message
        setTimeout(() => {
          location.reload(); // Reload to show manual waiting state
        }, 2000);
      } else {
        // Auto mode - show picking animation
        setTimeout(() => {
          cdEl.textContent = `🎰 Picking ${winnerCount} winner${winnerCount > 1 ? 's' : ''}... 🎰`;
          cdEl.style.animation = "pulse 0.5s infinite ease-in-out";
          checkForWinners();
        }, 1000);
      }
    }
    return;
  }
  cdEl.textContent = "Countdown: " + formatTime(remaining);
  remaining--;
}

function checkForWinners() {
  fetch('check_winner.php?giveaway_id=<?= $giveaway['id'] ?>')
    .then(response => response.json())
    .then(data => {
      if (data.winners && data.winners.length > 0) {
        setTimeout(() => {
          showWinnersReveal(data.winners);
        }, 5000);
      } else {
        setTimeout(checkForWinners, 2000);
      }
    })
    .catch(error => {
      console.error('Error checking winners:', error);
      setTimeout(checkForWinners, 2000);
    });
}

function showWinnersReveal(winners) {
  // Hide countdown and endtime
  cdEl.style.display = 'none';
  document.getElementById('endtime').style.display = 'none';
  
  // Highlight winners in entries list
  const entriesList = document.getElementById('entries-list');
  const listItems = entriesList.querySelectorAll('li');
  const winnerElements = [];
  
  winners.forEach(function(winner) {
    listItems.forEach(function(li) {
      if (li.dataset.username === winner.username) {
        li.classList.add('winner-highlight');
        
        // Add position-specific styling based on winner count
        if (winners.length === 1) {
          li.classList.add('winner-single'); // Pink for single winner
        } else {
          // Multiple winners - use medal colors
          if (winner.position === 1) {
            li.classList.add('winner-1'); // Gold
          } else if (winner.position === 2) {
            li.classList.add('winner-2'); // Silver
          } else if (winner.position === 3) {
            li.classList.add('winner-3'); // Bronze
          } else {
            li.classList.add('winner-other'); // Pink for 4th+
          }
        }
        
        winnerElements.push({element: li, position: winner.position});
      }
    });
  });
  
  // Sort winners by position and move them to the top with animation
  winnerElements.sort((a, b) => a.position - b.position);
  winnerElements.forEach(function(winner, index) {
    setTimeout(() => {
      entriesList.insertBefore(winner.element, entriesList.firstChild);
      // Scroll to top to show winners
      if (index === 0) {
        entriesList.scrollTop = 0;
      }
    }, index * 200); // Stagger the movement for visual effect
  });
  
  // Create winners announcement
  setTimeout(() => {
    const winnersDiv = document.createElement('div');
    winnersDiv.id = 'winners';
    
    if (winners.length === 1) {
      winnersDiv.innerHTML = '🎉 Winner: ' + winners[0].username + ' 🎉';
    } else {
      let winnersHTML = '🎉 Winners: 🎉';
      winners.forEach(function(winner) {
        winnersHTML += '<span class="winner-item"><span class="winner-position">#' + winner.position + ':</span> ' + winner.username + '</span>';
      });
      winnersDiv.innerHTML = winnersHTML;
    }
    
    // Insert before stats
    const statsDiv = document.querySelector('.giveaway-stats');
    statsDiv.parentNode.insertBefore(winnersDiv, statsDiv);
  }, 1000);
}

// Start countdown if giveaway hasn't ended yet
if (!giveawayHasEnded) {
  updateCountdown();
  timer = setInterval(updateCountdown, 1000);
}
  </script>
<?php endif; ?>

</body>
</html>