<?php
// 1. RENAME host.php TO admin.php
include 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE username = ?");
    $stmt->execute([$username]);
    $host = $stmt->fetch();

    if ($host && password_verify($password, $host['password_hash'])) {
        $_SESSION['admin_id'] = $host['id'];
        $_SESSION['admin_username'] = $host['username'];
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - ES Giveaways</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #ffe6f0;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            cursor: url('http://www.rw-designer.com/cursor-extern.php?id=176131'), auto;
        }

        .login-box {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0 10px #ff99cc;
            width: 300px;
        }

        h1 {
            color: #ff3399;
            margin-bottom: 20px;
        }

        input {
            width: 90%;
            padding: 8px;
            margin: 10px 0;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            background: #fff;
            font-family: 'Press Start 2P', cursive;
        }

        button {
            padding: 10px 20px;
            border: none;
            background: #ff3399;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
        }

        button:hover {
            background: #e60073;
        }

        .error {
            color: red;
            margin-top: 10px;
            font-size: 12px;
        }

        .back-link {
            margin-top: 20px;
        }

        .back-link a {
            color: #ff3399;
            text-decoration: none;
            font-size: 12px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>

<div class="login-box">
    <h1>Admin Panel</h1>
    <form method="post" action="">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
    <?php if ($error): ?>
    <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="/">← Back to Home</a>
    </div>
</div>

</body>
</html>