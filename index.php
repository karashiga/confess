<?php
session_start();

// Configuration
$DATA_FILE = 'confessions.json';
$MAX_AGE = 24 * 60 * 60; // 24 hours in seconds

// Generate a user token if it doesn't exist
if (!isset($_SESSION['user_token'])) {
    $_SESSION['user_token'] = bin2hex(random_bytes(16));
}

// Load existing confessions
$confessions = [];
if (file_exists($DATA_FILE)) {
    $confessions = json_decode(file_get_contents($DATA_FILE), true);
    
    // Filter out old confessions
    $current_time = time();
    $confessions = array_filter($confessions, function($confession) use ($current_time, $MAX_AGE) {
        return ($current_time - $confession['timestamp']) <= $MAX_AGE;
    });
    
    // Save filtered list back to file
    file_put_contents($DATA_FILE, json_encode(array_values($confessions)));
}

// Handle new confession submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confession'])) {
    $newConfession = [
        'id' => uniqid(),
        'user_token' => $_SESSION['user_token'],
        'username' => !empty($_POST['username']) ? htmlspecialchars(trim($_POST['username'])) : 'Anonymous',
        'confession' => htmlspecialchars(trim($_POST['confession'])),
        'timestamp' => time(),
        'reactions' => [
            'heart' => 0,
            'sad' => 0,
            'care' => 0
        ],
        'comments' => []
    ];
    
    $confessions[] = $newConfession;
    file_put_contents($DATA_FILE, json_encode($confessions));
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && isset($_POST['confession_id'])) {
    $confession_id = $_POST['confession_id'];
    $comment = htmlspecialchars(trim($_POST['comment']));
    
    foreach ($confessions as &$confession) {
        if ($confession['id'] === $confession_id) {
            $newComment = [
                'id' => uniqid(),
                'user_token' => $_SESSION['user_token'],
                'username' => !empty($_POST['comment_username']) ? htmlspecialchars(trim($_POST['comment_username'])) : 'Anonymous',
                'comment' => $comment,
                'timestamp' => time()
            ];
            $confession['comments'][] = $newComment;
            file_put_contents($DATA_FILE, json_encode($confessions));
            break;
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle reaction updates
if (isset($_GET['react']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $reaction = $_GET['react'];
    
    foreach ($confessions as &$confession) {
        if ($confession['id'] === $id && isset($confession['reactions'][$reaction])) {
            $confession['reactions'][$reaction]++;
            file_put_contents($DATA_FILE, json_encode($confessions));
            break;
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle confession deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    foreach ($confessions as $key => $confession) {
        if ($confession['id'] === $id && $confession['user_token'] === $_SESSION['user_token']) {
            unset($confessions[$key]);
            file_put_contents($DATA_FILE, json_encode(array_values($confessions)));
            break;
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Sort confessions by newest first
usort($confessions, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confession Wall</title>
    <style>
        :root {
            --primary-color: #6a5acd;
            --secondary-color: #9370db;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            margin: 0;
            font-size: 2.2rem;
        }
        
        .confession-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        button, .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        button:hover, .btn:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .confession {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            position: relative;
        }
        
        .confession-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .username {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .timestamp {
            color: #777;
            font-size: 0.85rem;
        }
        
        .confession-content {
            margin-bottom: 15px;
            white-space: pre-line;
        }
        
        .reactions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .reaction-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background-color: #f0f0f0;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 0.9rem;
        }
        
        .reaction-btn:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .heart { color: #e91e63; }
        .sad { color: #2196f3; }
        .care { color: #4caf50; }
        
        .time-left {
            font-size: 0.8rem;
            color: #999;
            margin-top: 5px;
        }
        
        .comments-section {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .comment-form {
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .comment {
            padding: 10px;
            margin: 10px 0;
            background-color: #f9f9f9;
            border-radius: 5px;
            border-left: 3px solid var(--secondary-color);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .comment-username {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .comment-timestamp {
            color: #777;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #777;
        }
        
        .confession-actions {
            margin-top: 10px;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }
            
            header {
                padding: 15px 0;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Confession Wall</h1>
            <p>Share your thoughts anonymously or with your name</p>
        </div>
    </header>
    
    <div class="container">
        <div class="confession-form">
            <h2>Share Your Confession</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="confession">Your Confession</label>
                    <textarea id="confession" name="confession" required placeholder="What's on your mind?"></textarea>
                </div>
                <div class="form-group">
                    <label for="username">Your Name (optional)</label>
                    <input type="text" id="username" name="username" placeholder="Anonymous">
                </div>
                <button type="submit">Post Confession</button>
            </form>
        </div>
        
        <div class="confessions-list">
            <h2>Recent Confessions</h2>
            <?php if (empty($confessions)): ?>
                <div class="empty-state">
                    <p>No confessions yet. Be the first to share!</p>
                </div>
            <?php else: ?>
                <?php foreach ($confessions as $confession): ?>
                    <?php
                    $time_passed = time() - $confession['timestamp'];
                    $time_left = $MAX_AGE - $time_passed;
                    $hours_left = floor($time_left / 3600);
                    $minutes_left = floor(($time_left % 3600) / 60);
                    $is_owner = ($confession['user_token'] === $_SESSION['user_token']);
                    ?>
                    <div class="confession">
                        <div class="confession-header">
                            <span class="username"><?= $confession['username'] ?></span>
                            <span class="timestamp"><?= date('M j, Y g:i a', $confession['timestamp']) ?></span>
                        </div>
                        <div class="confession-content"><?= $confession['confession'] ?></div>
                        <div class="reactions">
                            <a href="?react=heart&id=<?= $confession['id'] ?>" class="reaction-btn heart">❤️ <span><?= $confession['reactions']['heart'] ?></span></a>
                            <a href="?react=sad&id=<?= $confession['id'] ?>" class="reaction-btn sad">😢 <span><?= $confession['reactions']['sad'] ?></span></a>
                            <a href="?react=care&id=<?= $confession['id'] ?>" class="reaction-btn care">🤗 <span><?= $confession['reactions']['care'] ?></span></a>
                        </div>
                        <div class="time-left">
                            Will be deleted in <?= $hours_left > 0 ? "$hours_left hour" . ($hours_left !== 1 ? 's' : '') . " and " : '' ?><?= $minutes_left ?> minute<?= $minutes_left !== 1 ? 's' : '' ?>
                        </div>
                        
                        <?php if ($is_owner): ?>
                            <div class="confession-actions">
                                <a href="?delete=1&id=<?= $confession['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this confession?')">Delete Confession</a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="comments-section">
                            <h3>Comments (<?= count($confession['comments']) ?>)</h3>
                            
                            <?php foreach ($confession['comments'] as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <span class="comment-username"><?= $comment['username'] ?></span>
                                        <span class="comment-timestamp"><?= date('M j, Y g:i a', $comment['timestamp']) ?></span>
                                    </div>
                                    <div class="comment-content"><?= $comment['comment'] ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="comment-form">
                                <form method="POST">
                                    <input type="hidden" name="confession_id" value="<?= $confession['id'] ?>">
                                    <div class="form-group">
                                        <textarea name="comment" required placeholder="Write a comment..." rows="2"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <input type="text" name="comment_username" placeholder="Your name (optional)">
                                    </div>
                                    <button type="submit">Post Comment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
