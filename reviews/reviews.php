<?php
session_start();
require '../database/config.php';

// ---- Require login ----
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

$errors  = [];
$success = false;
$oldRating  = 0;
$oldComment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $oldRating  = $rating;
    $oldComment = $comment;

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please choose a rating between 1 and 5 stars.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $rating, $comment !== '' ? $comment : null]);

        $success    = true;
        $oldRating  = 0;
        $oldComment = '';
    }
}

// Recent reviews, most recent first.
$reviews = $pdo->query(
    "SELECT r.rating, r.comment, r.created_at, u.full_name
     FROM reviews r
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC
     LIMIT 20"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Us — Villaflores Gaming Cafe</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .review-wrap {
            min-height: 100vh;
            padding: 48px 32px;
            display: flex;
            justify-content: center;
        }
        .review-container {
            width: 100%;
            max-width: 560px;
        }
        .auth-card {
            border-radius: 16px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 40px;
        }
        .auth-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .auth-sub {
            margin-top: 8px;
            font-size: 14px;
            color: var(--muted-foreground);
        }
        .auth-field {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .auth-field label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted-foreground);
        }
        .auth-field textarea {
            background-color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--foreground);
            font-family: var(--font-body);
            font-size: 14px;
            resize: vertical;
            min-height: 90px;
        }
        .auth-field textarea:focus {
            outline: none;
            border-color: var(--cerulean);
        }

        /* Star rating input, pure CSS: radios reversed + sibling selectors */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 4px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 32px;
            line-height: 1;
            color: var(--muted);
            cursor: pointer;
            transition: color 0.15s ease;
            text-transform: none;
            letter-spacing: normal;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: var(--pink);
        }

        .auth-submit {
            margin-top: 28px;
            width: 100%;
            border: none;
            border-radius: 6px;
            padding: 14px 0;
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background-color: var(--pink);
            color: #000000;
            cursor: pointer;
        }
        .auth-footer {
            margin-top: 20px;
            font-size: 14px;
            color: var(--muted-foreground);
            text-align: center;
        }
        .auth-footer a {
            color: var(--cerulean);
            text-decoration: none;
        }
        .auth-errors {
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid var(--pink);
            background-color: rgba(246, 4, 126, 0.08);
            padding: 14px 16px;
        }
        .auth-errors p {
            font-size: 13px;
            color: var(--pink);
            margin: 4px 0;
        }
        .auth-success {
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid var(--cerulean);
            background-color: rgba(0, 161, 245, 0.08);
            padding: 14px 16px;
        }
        .auth-success p {
            font-size: 13px;
            color: var(--cerulean);
            margin: 0;
        }

        .reviews-list {
            margin-top: 24px;
        }
        .review-item {
            border-radius: 12px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 20px;
            margin-bottom: 12px;
        }
        .review-item-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .review-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--foreground);
        }
        .review-date {
            font-size: 11px;
            color: var(--muted-foreground);
        }
        .review-stars {
            margin-top: 4px;
            color: var(--pink);
            font-size: 14px;
            letter-spacing: 2px;
        }
        .review-comment {
            margin-top: 10px;
            font-size: 14px;
            color: var(--muted-foreground);
            line-height: 1.5;
        }
        .section-heading {
            margin-top: 40px;
            margin-bottom: 12px;
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="review-wrap brand-grid">
        <div class="review-container">
            <div class="auth-card">
                <h1 class="auth-title">Rate Us</h1>
                <p class="auth-sub">Tell us about your visit — it helps other gamers, and helps us improve.</p>

                <?php if ($success): ?>
                    <div class="auth-success">
                        <p>Thanks for the feedback! Your review has been posted.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="auth-errors">
                        <?php foreach ($errors as $error): ?>
                            <p>• <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reviews.php" novalidate>
                    <div class="auth-field">
                        <label>Your Rating</label>
                        <div class="star-rating">
                            <input type="radio" name="rating" id="star5" value="5" <?php echo $oldRating === 5 ? 'checked' : ''; ?>>
                            <label for="star5">★</label>
                            <input type="radio" name="rating" id="star4" value="4" <?php echo $oldRating === 4 ? 'checked' : ''; ?>>
                            <label for="star4">★</label>
                            <input type="radio" name="rating" id="star3" value="3" <?php echo $oldRating === 3 ? 'checked' : ''; ?>>
                            <label for="star3">★</label>
                            <input type="radio" name="rating" id="star2" value="2" <?php echo $oldRating === 2 ? 'checked' : ''; ?>>
                            <label for="star2">★</label>
                            <input type="radio" name="rating" id="star1" value="1" <?php echo $oldRating === 1 ? 'checked' : ''; ?>>
                            <label for="star1">★</label>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="comment">Your Feedback (optional)</label>
                        <textarea id="comment" name="comment" placeholder="What did you like? What can we improve?"><?php echo htmlspecialchars($oldComment); ?></textarea>
                    </div>

                    <button type="submit" class="auth-submit">Submit Review</button>
                </form>

                <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
            </div>

            <?php if (!empty($reviews)): ?>
                <h2 class="section-heading">Recent Reviews</h2>
                <div class="reviews-list">
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-item">
                            <div class="review-item-head">
                                <span class="review-name"><?php echo htmlspecialchars($r['full_name'] ?? 'Anonymous'); ?></span>
                                <span class="review-date"><?php echo htmlspecialchars($r['created_at']); ?></span>
                            </div>
                            <div class="review-stars"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></div>
                            <?php if (!empty($r['comment'])): ?>
                                <p class="review-comment"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>