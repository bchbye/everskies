<?php
include 'auth.php';

$giveaway_id = $_POST['giveaway_id'];

$stmt = $pdo->prepare("SELECT username FROM entries WHERE giveaway_id = ?");
$stmt->execute([$giveaway_id]);
$entries = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($entries) {
  $winner = $entries[array_rand($entries)];

  $stmt = $pdo->prepare("UPDATE giveaways SET winner = ? WHERE id = ?");
  $stmt->execute([$winner, $giveaway_id]);
}

header("Location: dashboard.php");
exit;
?>
