<?php
include 'auth.php';

$giveaway_id = $_POST['giveaway_id'] ?? null;
$username = trim($_POST['username'] ?? '');

if ($giveaway_id && $username !== '') {
  $stmt = $pdo->prepare("INSERT INTO entries (giveaway_id, username) VALUES (?, ?)");
  $stmt->execute([$giveaway_id, $username]);
}

header("Location: dashboard.php");
exit;
