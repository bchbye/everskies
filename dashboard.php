<?php
include 'auth.php';

// Handle create giveaway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_giveaway'])) {
    // Check how many giveaways the host already has
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE host_id = ?");
    $stmt->execute([$_SESSION['host_id']]);
    $giveawayCount = $stmt->fetchColumn();

    if ($giveawayCount >= 2) {
        $error = "You can only create up to 2 giveaways. Please delete an existing giveaway before creating a new one.";
    } else {
        // Process form submission
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $countdown_datetime = $_POST['countdown_datetime'];
        $user_timezone = $_POST['user_timezone'] ?? 'UTC';
		$es_link = trim($_POST['es_link']);

        // Convert to UTC
        $date = new DateTime($countdown_datetime, new DateTimeZone($user_timezone));
        $date->setTimezone(new DateTimeZone('UTC'));
        $countdown_datetime = $date->format('Y-m-d H:i:s');

        // Check unique slug
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Giveaway code already exists. Choose another.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO giveaways (host_id, title, slug, countdown_datetime, es_link) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['host_id'], $title, $slug, $countdown_datetime, $es_link]);
            header("Location: dashboard.php");
            exit;
        }
    }
}

// Handle update of Everskies link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_es_link_id'])) {
    $update_id = (int)$_POST['update_es_link_id'];
    $es_link = trim($_POST['es_link']);

    // Verify giveaway belongs to current host
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE id = ? AND host_id = ?");
    $stmtCheck->execute([$update_id, $_SESSION['host_id']]);
    if ($stmtCheck->fetchColumn() > 0) {
        // Update the link
        $stmt = $pdo->prepare("UPDATE giveaways SET es_link = ? WHERE id = ?");
        $stmt->execute([$es_link, $update_id]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Giveaway not found or you do not have permission to update it.";
    }
}


// Handle delete giveaway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_giveaway_id'])) {
    $delete_id = (int)$_POST['delete_giveaway_id'];

    // Verify giveaway belongs to current host
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE id = ? AND host_id = ?");
    $stmtCheck->execute([$delete_id, $_SESSION['host_id']]);
    if ($stmtCheck->fetchColumn() > 0) {
        // Delete entries first
        $stmtDelEntries = $pdo->prepare("DELETE FROM entries WHERE giveaway_id = ?");
        $stmtDelEntries->execute([$delete_id]);

        // Delete giveaway
        $stmtDel = $pdo->prepare("DELETE FROM giveaways WHERE id = ?");
        $stmtDel->execute([$delete_id]);

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Giveaway not found or you do not have permission to delete it.";
    }
}

// Fetch giveaways
$stmt = $pdo->prepare("SELECT * FROM giveaways WHERE host_id = ?");
$stmt->execute([$_SESSION['host_id']]);
$giveaways = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Host Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #ffe6f0;
            color: #333;
            text-align: center;
            padding: 20px;
        }

        h1, h2, h3, h4 {
            color: #ff3399;
        }

        input, button {
            font-family: 'Press Start 2P', cursive;
            padding: 8px;
            margin: 5px;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            background: #fff0f5;
            color: #333;
        }

        button {
            background: #ff3399;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #e60073;
        }

        .entry {
            background: #fff0f5;
            margin: 5px 0;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ff99cc;
            display: inline-block;
            font-size: 14px;
        }

        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            padding: 2px 6px;
        }

        hr {
            border: 0;
            height: 2px;
            background: #ff99cc;
            margin: 20px 0;
        }

        form {
            display: inline-block;
        }

        a.logout {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background: #ff3399;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        a.logout:hover {
            background: #e60073;
        }

        .delete-btn {
            background: #e74c3c;
            border: none;
            padding: 5px 10px;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            margin-top: 10px;
        }

        .delete-btn:hover {
            background: #cc0000;
        }
    </style>
</head>
<body>

<h1>Welcome, <?=htmlspecialchars($_SESSION['host_username'])?></h1>
<a class="logout" href="logout.php">Logout</a>

<h2>Create New Giveaway</h2>
<form method="post" action="">
    <input type="hidden" name="create_giveaway" value="1">
    Title:<br>
    <input type="text" name="title" required><br>
    Giveaway Code (used in link):<br>esgiveaways.com/yourcode<br>
    <input type="text" name="slug" id="slugInput" pattern="[a-zA-Z0-9_-]+" title="No spaces. Letters, numbers, - or _ only" required><br>
	Everskies Giveaway Link:<br>You can update this link later.<br>
<input type="url" name="es_link" placeholder="https://everskies.com/..." value="https://everskies.com/"><br>
    Countdown end datetime:<br>
    <input type="datetime-local" name="countdown_datetime" required><br><br>
    <button type="submit">Create Giveaway</button>
	<input type="hidden" name="user_timezone" id="user_timezone">

<script>
document.getElementById('user_timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
</script>
</form>

<?php if (!empty($error)): ?>
    <p style="color:red"><?=htmlspecialchars($error)?></p>
<?php endif; ?>

<hr>

<h2>Your Giveaways</h2>

<?php if (empty($giveaways)): ?>
    <p>No giveaways yet.</p>
<?php endif; ?>

<?php foreach ($giveaways as $g): ?>
    <h3><?=htmlspecialchars($g['title'])?> (Code: <?=htmlspecialchars($g['slug'])?>)</h3>
    <p>Ends at: <?=htmlspecialchars($g['countdown_datetime'])?></p>
    <p>Winner: <?= $g['winner'] ? htmlspecialchars($g['winner']) : 'Not picked yet' ?></p>
    <p>Link: <a href="/<?=htmlspecialchars($g['slug'])?>" target="_blank">https://esgiveaways.online/<?=htmlspecialchars($g['slug'])?></a></p>
	
	<h4>Update Everskies Link</h4>
<form method="post" action="">
    <input type="hidden" name="update_es_link_id" value="<?= $g['id'] ?>">
    <input type="url" name="es_link" value="<?= htmlspecialchars($g['es_link']) ?>" placeholder="https://everskies.com/...">
    <button type="submit">Update Link</button>
</form>

    <?php
    // Fetch entries
    $stmt2 = $pdo->prepare("SELECT * FROM entries WHERE giveaway_id = ?");
    $stmt2->execute([$g['id']]);
    $entries = $stmt2->fetchAll();
    ?>

    <h4>Entries</h4>
    <?php if (empty($entries)): ?>
        <p>No entries yet.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0;">
        <?php foreach ($entries as $entry): ?>
            <li class="entry">
                <?=htmlspecialchars($entry['username'])?>
                <form method="post" action="remove_entry.php" style="display:inline">
                    <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                    <button type="submit" class="remove-btn" onclick="return confirm('Remove this entry?')">X</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h4>Add Entry</h4>
    <form method="post" action="add_entry.php">
        <input type="hidden" name="giveaway_id" value="<?= $g['id'] ?>">
        <input type="text" name="username" placeholder="Username" required>
        <button type="submit">Add Entry</button>
    </form>

    <!-- Delete Giveaway Button -->
    <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this giveaway? This action cannot be undone.')">
        <input type="hidden" name="delete_giveaway_id" value="<?= $g['id'] ?>">
        <button type="submit" class="delete-btn">Delete Giveaway</button>
    </form>

    <hr>
<?php endforeach; ?>

<script>
    // Prevent spaces in slug input by removing them immediately on input
    const slugInput = document.getElementById('slugInput');
    slugInput.addEventListener('input', () => {
        slugInput.value = slugInput.value.replace(/\s/g, '');
    });
</script>

</body>
</html>
