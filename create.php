<?php
include 'db.php';
include 'content_filter.php'; // Include the content filter

// Add after session_start()
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// For POST requests, check CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
}

$error = '';
$success = '';
$giveaway_data = null;

// Generate short management code (8 characters: letters + numbers)
function generateManagementCode() {
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
		
		// RECAPTCHA VALIDATION FIRST
        $recaptcha_secret = '6Lf6O30rAAAAAKEdgZJdA8bpLwyOAYYdMMYAST8e'; // Replace with your secret key
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        
        if (empty($recaptcha_response)) {
            throw new Exception("Please complete the reCAPTCHA verification.");
        }
        
        // Verify reCAPTCHA with Google
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $verify_data = [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $verify_response = file_get_contents($verify_url . '?' . http_build_query($verify_data));
        $verify_result = json_decode($verify_response, true);
        
        if (!$verify_result['success']) {
            throw new Exception("reCAPTCHA verification failed. Please try again.");
        }
		
		
        // Get form data
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $countdown_datetime = $_POST['countdown_datetime'];
        $user_timezone = $_POST['user_timezone'] ?? 'UTC';
        $es_link = trim($_POST['es_link']);
        $winner_count = (int)$_POST['winner_count'];
        $winner_selection_mode = $_POST['winner_selection_mode'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // === CONTENT FILTER VALIDATION ===
        
        // Check creation rate limit first
        $rateLimit = SmartContentFilter::checkCreationRateLimit($pdo, $ip_address);
        if ($rateLimit) {
            throw new Exception($rateLimit);
        }
        
        // Validate title with database filter
        if (empty($title)) {
            throw new Exception("Title is required.");
        }
        $titleValidation = SmartContentFilter::validateTitle($pdo, $title);
        if ($titleValidation !== true) {
            throw new Exception($titleValidation);
        }
        
        // Validate slug with database filter
        if (empty($slug)) {
            throw new Exception("Giveaway code is required.");
        }
        $slugValidation = SmartContentFilter::validateSlug($pdo, $slug);
        if ($slugValidation !== true) {
            throw new Exception($slugValidation);
        }
        
        // === EXISTING VALIDATION ===
        
        if ($winner_count < 1 || $winner_count > 10) {
            throw new Exception("Invalid number of winners. Must be between 1-10.");
        }

        if (!in_array($winner_selection_mode, ['auto', 'manual'])) {
            throw new Exception("Invalid winner selection mode.");
        }
        
        if (empty($countdown_datetime)) {
            throw new Exception("Countdown end time is required.");
        }

        // Convert to UTC
        $date = new DateTime($countdown_datetime, new DateTimeZone($user_timezone));
        $date->setTimezone(new DateTimeZone('UTC'));
        $countdown_datetime = $date->format('Y-m-d H:i:s');

        // Check if the datetime is in the future
        if ($date->getTimestamp() <= time()) {
            throw new Exception("Countdown end time must be in the future.");
        }

        // Check if slug is unique
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Giveaway code already exists. Please choose another.");
        }

        // Generate unique management code
        do {
            $management_code = generateManagementCode();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM giveaways WHERE management_code = ?");
            $stmt->execute([$management_code]);
        } while ($stmt->fetchColumn() > 0);

        // Insert giveaway with IP tracking
        $stmt = $pdo->prepare("INSERT INTO giveaways (title, slug, countdown_datetime, es_link, management_code, winner_count, winner_selection_mode, created_ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $slug, $countdown_datetime, $es_link, $management_code, $winner_count, $winner_selection_mode, $ip_address]);

        // Success! Store data to show success message
        $success = "Giveaway created successfully!";
        $giveaway_data = [
            'title' => $title,
            'slug' => $slug,
            'management_code' => $management_code,
            'winner_count' => $winner_count,
            'winner_selection_mode' => $winner_selection_mode
        ];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Giveaway - ES Giveaways</title>
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
        .form-box {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px auto;
            max-width: 400px;
            text-align: left;
        }
        .success-box {
            background: #fff0f5;
            border: 2px solid #ff99cc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px auto;
            max-width: 500px;
            box-shadow: 0 0 10px #ff99cc;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: center;
        }
        h1 { color: #ff3399; text-align: center; }
        input, select, button {
            font-family: 'Press Start 2P', cursive;
            padding: 8px;
            margin: 8px 0;
            border: 2px solid #ff99cc;
            border-radius: 4px;
            background: #fff;
            color: #333;
            width: 90%;
        }
        select {
            width: 95%;
        }
        button {
            background: #ff3399;
            color: white;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #e60073; }
        label { 
            display: block; 
            margin: 10px 0 5px 0;
            font-size: 12px;
        }
        small {
            display: block;
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }
        a {
            color: #ff3399;
            text-decoration: none;
        }
        a:hover {
            color: #e60073;
            text-decoration: underline;
        }
        
        .error {
            background: #ffebee;
            border: 2px solid #f44336;
            color: #c62828;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 11px;
            text-align: center;
        }
        
        .success {
            background: #e8f5e8;
            border: 2px solid #4caf50;
            color: #2e7d32;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 11px;
            text-align: center;
        }
        
        .radio-group {
            margin: 10px 0;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            margin: 8px 0;
            padding: 8px;
            border: 1px solid #ff99cc;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .radio-option:hover {
            background: #ffe6f0;
        }
        
        .radio-option input[type="radio"] {
            margin: 0 10px 0 0;
            width: auto;
        }
        
        .radio-option.selected {
            background: #ffe6f0;
            border-color: #ff3399;
        }
        
        .radio-label {
            font-size: 11px;
            flex: 1;
        }
        
        .radio-description {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        
        .code { 
            background: #ff3399; 
            color: white; 
            padding: 10px; 
            border-radius: 4px; 
            font-size: 18px;
            margin: 10px 0;
            letter-spacing: 2px;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .giveaway-link {
            background: #ff3399;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .giveaway-link a {
            color: white;
            text-decoration: none;
        }
        .warning {
            background: #ffeb3b;
            color: #333;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
        .info {
            background: #e3f2fd;
            color: #333;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 12px;
        }
        .button-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px 15px;
            background: #ff3399;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
        }
        .btn:hover { background: #e60073; }
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

.logo-link:hover {
    text-decoration: none !important;
}

.logo-link:hover h1 {
    color: #e60073; /* slightly darker pink on hover */
}
.g-recaptcha {
    transform: scale(0.9);
    transform-origin: 0 0;
    margin: 10px 0;
}

@media (max-width: 400px) {
    .g-recaptcha {
        transform: scale(0.75);
    }
}
    </style>
	<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <?php if ($success && $giveaway_data): ?>
        <!-- Success Message -->
        <div class="success-box">
            <h1>🎉 Giveaway Created!</h1>
            
            <div class="giveaway-link">
                <strong>Your Giveaway:</strong><br>
                <a href="/<?= htmlspecialchars($giveaway_data['slug']) ?>" target="_blank">
                    esgiveaways.online/<?= htmlspecialchars($giveaway_data['slug']) ?>
                </a>
            </div>
            
            <div class="info">
                🏆 This giveaway will pick <?= $giveaway_data['winner_count'] ?> winner<?= $giveaway_data['winner_count'] > 1 ? 's' : '' ?>!<br>
                <?php if ($giveaway_data['winner_selection_mode'] === 'auto'): ?>
                    ⚡ Winners will be picked automatically when countdown ends
                <?php else: ?>
                    🎯 You'll trigger winner selection manually when ready
                <?php endif; ?>
            </div>
            
            <p><strong>Management Code:</strong></p>
            <div class="code"><?= htmlspecialchars($giveaway_data['management_code']) ?></div>
            
            <div class="warning">
                ⚠️ SAVE THIS CODE! You need it to manage your giveaway (add/remove entries, etc.)
            </div>
            
            <div class="button-container">
                <a href="/manage?code=<?= urlencode($giveaway_data['management_code']) ?>" class="btn">Manage Giveaway</a>
                <a href="/create" class="btn">Create Another</a>
            </div>
        </div>
		
		<script>
// Show popup immediately when page loads
window.addEventListener('DOMContentLoaded', function() {
    showManagementCodePopup();
});

function showManagementCodePopup() {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create popup
    const popup = document.createElement('div');
    popup.style.cssText = `
        background: #fff0f5;
        border: 3px solid #ff3399;
        border-radius: 8px;
        padding: 30px;
        max-width: 400px;
        text-align: center;
        font-family: 'Press Start 2P', cursive;
        box-shadow: 0 0 20px rgba(255, 51, 153, 0.5);
        animation: pulse 1s infinite;
    `;
    
    popup.innerHTML = `
        <h2 style="color: #ff3399; margin-bottom: 20px;">🚨 IMPORTANT! 🚨</h2>
        <p style="font-size: 12px; margin-bottom: 20px;">Save your Management Code NOW!</p>
        <div id="managementCode" onclick="copyCode()" style="background: #ff3399; color: white; padding: 15px; border-radius: 4px; font-size: 16px; letter-spacing: 2px; margin: 15px 0; cursor: pointer; transition: background 0.3s;">
    <?= htmlspecialchars($giveaway_data['management_code']) ?>
</div>
<p style="font-size: 9px; color: #333; margin-top: -10px;">Click code to copy</p>

         <p style="font-size: 10px; color: #ff0000; margin-bottom: 20px; line-height: 1.4;">
        ⚠️ Cannot be recovered if lost!<br>
        📝 Save it + screenshot this popup
    </p>
        <button onclick="closePopup()" style="background: #ff3399; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-family: 'Press Start 2P', cursive; cursor: pointer;">
            I've Saved It!
        </button>
    `;
    
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
    
    // Close popup function
    window.closePopup = function() {
        document.body.removeChild(overlay);
    };
}

// Copy code function
window.copyCode = function() {
    const code = '<?= htmlspecialchars($giveaway_data['management_code']) ?>';
    navigator.clipboard.writeText(code).then(function() {
        const codeDiv = document.getElementById('managementCode');
        codeDiv.style.background = '#28a745';
        codeDiv.innerHTML = '✅ Copied!';
        setTimeout(() => {
            codeDiv.style.background = '#ff3399';
            codeDiv.innerHTML = code;
        }, 1500);
    });
};
</script>

    <?php else: ?>
	
<a href="/" class="logo-link">
    <h1>ES Giveaways</h1>
    <div class="tagline">countdown + winner picker</div>
</a>

        <!-- Create Form -->
        <div class="form-box">
            <h1>Create Giveaway</h1>
		<!--	<h1 class="form-title">Create Giveaway</h1> no underline -->
            
            <?php if ($error): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <label>Title:</label>
                <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                
                <label>Giveaway Code (used in link):</label>
                <small>esgiveaways.online/yourcode (max 12 characters)</small>
                <input type="text" name="slug" id="slugInput" pattern="[a-zA-Z0-9_-]+" title="No spaces. Letters, numbers, - or _ only" maxlength="12" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" required>
                
                <label>Number of Winners:</label>
                <small>How many winners should be picked?</small>
                <select name="winner_count" required>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>" <?= (($_POST['winner_count'] ?? 1) == $i) ? 'selected' : '' ?>>
                            <?= $i ?> Winner<?= $i > 1 ? 's' : '' ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <label>Winner Selection:</label>
                <div class="radio-group">
                    <div class="radio-option <?= ($_POST['winner_selection_mode'] ?? 'manual') === 'auto' ? 'selected' : '' ?>" onclick="selectRadio('auto')">
                        <input type="radio" name="winner_selection_mode" value="auto" id="auto" <?= ($_POST['winner_selection_mode'] ?? 'manual') === 'auto' ? 'checked' : '' ?>>
                        <div class="radio-label">
                            ⚡ Automatic - Pick winner when countdown ends
                           <div class="radio-description">Winners selected immediately (entries must be added before countdown ends)</div>
                        </div>
                    </div>
                    <div class="radio-option <?= ($_POST['winner_selection_mode'] ?? 'manual') === 'manual' ? 'selected' : '' ?>" onclick="selectRadio('manual')">
                        <input type="radio" name="winner_selection_mode" value="manual" id="manual" <?= ($_POST['winner_selection_mode'] ?? 'manual') === 'manual' ? 'checked' : '' ?>>
                        <div class="radio-label">
                            🎯 Manual - I'll trigger winner selection when ready
                            <div class="radio-description">Perfect for adding entries after countdown ends (recommended)</div>
                        </div>
                    </div>
                </div>
                
                <label>Everskies Giveaway Link:</label>
                <small>You can update this later</small>
                <input type="url" name="es_link" placeholder="https://everskies.com/..." value="<?= htmlspecialchars($_POST['es_link'] ?? 'https://everskies.com/') ?>">
                
                <label>Countdown end datetime:</label>
                <input type="datetime-local" name="countdown_datetime" value="<?= htmlspecialchars($_POST['countdown_datetime'] ?? '') ?>" required>
                
                <input type="hidden" name="user_timezone" id="user_timezone">
				
				<label>Security Check:</label>
<div class="g-recaptcha" data-sitekey="6Lf6O30rAAAAAFereD0SLwYPs3vljcVxuyVy4M7n" style="margin: 10px 0;"></div>


				<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit">Create Giveaway</button>
            </form>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="/">← Back to Home</a>
            </p>
        </div>
    <?php endif; ?>

    <script>
        document.getElementById('user_timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
        
        const slugInput = document.getElementById('slugInput');
        
        if (slugInput) {
            // Prevent spaces and enforce character limit
            slugInput.addEventListener('input', () => {
                // Remove spaces
                slugInput.value = slugInput.value.replace(/\s/g, '');
                
                // Show character count
                const remaining = 12 - slugInput.value.length;
                const counter = document.getElementById('char-counter');
                if (counter) {
                    counter.textContent = `${remaining} characters remaining`;
                    counter.style.color = remaining < 3 ? '#ff3399' : '#666';
                }
            });
            
            // Add character counter
            const counter = document.createElement('small');
            counter.id = 'char-counter';
            counter.style.display = 'block';
            counter.style.fontSize = '10px';
            counter.style.color = '#666';
            counter.textContent = '12 characters remaining';
            slugInput.parentNode.insertBefore(counter, slugInput.nextSibling);
        }
        
        // Radio button selection
        function selectRadio(mode) {
            // Remove selected class from all options
            document.querySelectorAll('.radio-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(mode).checked = true;
        }
    </script>
	
</body>
</html>