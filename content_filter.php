<?php
// content_filter.php - Simplified Smart Content Filter

class SmartContentFilter {
    
    private static $cachedBannedWords = null;
    
    // Load banned words from database (cached for performance)
    private static function loadBannedWords($pdo) {
        if (self::$cachedBannedWords === null) {
            $stmt = $pdo->query("SELECT word, category, severity FROM banned_words");
            self::$cachedBannedWords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return self::$cachedBannedWords;
    }
    
    // Normalize text to prevent bypass attempts
    private static function normalizeText($text) {
        $text = strtolower($text);
        
        // Remove spaces, underscores, hyphens
        $text = str_replace([' ', '_', '-', '.'], '', $text);
        
        // Replace common character substitutions
        $substitutions = [
            // Numbers as letters
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b',
            
            // Special characters as letters
            '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i', '€' => 'e',
            
            // Unicode/accented characters
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        
        foreach ($substitutions as $find => $replace) {
            $text = str_replace($find, $replace, $text);
        }
        
        // Only reduce excessive repeating characters (3+ in a row)
        // "asssss" -> "ass" (still detectable), "assessment" -> "assessment" (unchanged)
        $text = preg_replace('/(.)\1{2,}/', '$1$1', $text);
        
        return $text;
    }
    
    // Check if it's actually a bad word and not part of innocent word
    private static function isStandaloneBadWord($normalizedText, $normalizedBanned, $originalBannedWord) {
        // For very short words (2-3 characters), be more strict
        if (strlen($originalBannedWord) <= 3) {
            // Only match if it's the whole word or clearly separated
            $pattern = '/\b' . preg_quote($normalizedBanned, '/') . '\b/';
            return preg_match($pattern, $normalizedText);
        }
        
        // For longer words (4+ characters), current logic is fine
        return true;
    }
    
    // Check if text contains banned words with smart detection
    public static function containsBannedWords($pdo, $text, $context = 'general') {
        $bannedWords = self::loadBannedWords($pdo);
        $normalizedText = self::normalizeText($text);
        $originalText = strtolower($text);
        
        foreach ($bannedWords as $wordData) {
            $bannedWord = $wordData['word'];
            $category = $wordData['category'];
            $severity = $wordData['severity'];
            
            // Skip reserved words for usernames
            if ($category === 'reserved' && $context === 'username') {
                continue;
            }
            
            $normalizedBanned = self::normalizeText($bannedWord);
            
            // Method 1: Exact match in original text
            if (strpos($originalText, strtolower($bannedWord)) !== false) {
                return ['word' => $bannedWord, 'category' => $category, 'severity' => $severity];
            }
            
            // Method 2: Check normalized text for bypass attempts
            if (strpos($normalizedText, $normalizedBanned) !== false) {
                if (self::isStandaloneBadWord($normalizedText, $normalizedBanned, $bannedWord)) {
                    return ['word' => $bannedWord, 'category' => $category, 'severity' => $severity];
                }
            }
        }
        
        return false;
    }
    
    // Validate username/entry (simplified - no rate limiting)
    public static function validateUsername($pdo, $username) {
        $username = trim($username);
        
        // Basic validation
        if (strlen($username) < 2) {
            return "Username must be at least 2 characters long";
        }
        
        if (strlen($username) > 30) {
            return "Username must be 30 characters or less";
        }
        
        // Check for banned words
        $bannedCheck = self::containsBannedWords($pdo, $username, 'username');
        if ($bannedCheck) {
            switch ($bannedCheck['severity']) {
                case 'high':
                    return "Username contains inappropriate content and cannot be used";
                case 'medium':
                    return "Username contains inappropriate language: '{$bannedCheck['word']}'";
                case 'low':
                    return "Username contains flagged content: '{$bannedCheck['word']}'";
            }
        }
        
        // Additional spam checks
        if (preg_match('/[^a-zA-Z0-9_.-]/', $username)) {
            return "Username contains invalid characters";
        }
        
        if (preg_match('/(.)\1{4,}/', $username)) {
            return "Username has too many repeating characters";
        }
        
        return true;
    }
    
    // Validate giveaway title
    public static function validateTitle($pdo, $title) {
        $title = trim($title);
        
        if (strlen($title) < 3) {
            return "Title must be at least 3 characters long";
        }
        
        if (strlen($title) > 100) {
            return "Title must be 100 characters or less";
        }
        
        // Check for banned words
        $bannedCheck = self::containsBannedWords($pdo, $title, 'title');
        if ($bannedCheck) {
            return "Title contains inappropriate content: '{$bannedCheck['word']}'";
        }
        
        // Check for excessive caps
        $uppercaseCount = strlen(preg_replace('/[^A-Z]/', '', $title));
        $totalLetters = strlen(preg_replace('/[^A-Za-z]/', '', $title));
        if ($totalLetters > 0 && ($uppercaseCount / $totalLetters) > 0.6) {
            return "Title cannot be mostly uppercase";
        }
        
        return true;
    }
    
    // Validate giveaway slug
    public static function validateSlug($pdo, $slug) {
        $slug = trim($slug);
        
        if (strlen($slug) < 2) {
            return "Giveaway code must be at least 2 characters long";
        }
        
        if (strlen($slug) > 12) {
            return "Giveaway code must be 12 characters or less";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return "Giveaway code can only contain letters, numbers, hyphens, and underscores";
        }
        
        // Check for banned words (including reserved words)
        $bannedCheck = self::containsBannedWords($pdo, $slug, 'slug');
        if ($bannedCheck) {
            if ($bannedCheck['category'] === 'reserved') {
                return "This giveaway code is reserved for system use";
            } else {
                return "Giveaway code contains inappropriate content: '{$bannedCheck['word']}'";
            }
        }
        
        return true;
    }
    
    // Rate limiting for giveaway creation (keep this for spam prevention)
    public static function checkCreationRateLimit($pdo, $ip_address) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM giveaways 
            WHERE created_ip = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip_address]);
        
        if ($stmt->fetchColumn() >= 3) {
            return "Too many giveaways created from your connection. Please wait 1 hour.";
        }
        
        return false;
    }
}
?>