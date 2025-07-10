<?php
include 'auth.php';

$entry_id = $_POST['entry_id'] ?? null;

if ($entry_id) {
  // Optional: verify entry belongs to this host’s giveaways before deleting
  $stmt = $pdo->prepare("DELETE FROM entries WHERE id = ?");
  $stmt->execute([$entry_id]);
}

header("Location: dashboard.php");
exit;
