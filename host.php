<?php
include 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE username = ?");
    $stmt->execute([$username]);
    $host = $stmt->fetch();

    if ($host && password_verify($password, $host['password_hash'])) {
        $_SESSION['host_id'] = $host['id'];
        $_SESSION['host_username'] = $host['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Host Login</title>
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
        }

    </style>
</head>
<body>

<div class="login-box">
    <h1>Host Login</h1>
    <form method="post" action="">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
    <?php if ($error): ?>
    <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
	
	
		<br><br/>
<p>Contact <a href="https://everskies.com/user/Dan-1035" class="contact-link">@Dan</a> for access.</p>
</div>

</body>
</html>
