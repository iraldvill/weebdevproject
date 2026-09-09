<?php

session_start();
require '../database/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

function h($val) {
    return htmlspecialchars($val ?? "", ENT_QUOTES, "UTF-8");
}

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($userId <= 0) {
    header('Location: admin.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['admin_flash'] = "Account #$userId not found.";
    header('Location: admin.php');
    exit;
}

$errors = [];
$old = ['full_name' => $user['full_name'], 'email' => $user['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    $old = ['full_name' => $full_name, 'email' => $email];

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($new_password !== '' && (strlen($new_password) < 8 || strlen($new_password) > 64)) {
        $errors[] = 'New password must be between 8 and 64 characters (or leave it blank to keep the current one).';
    }

    if (empty($errors)) {
        // Make sure the email isn't already used by a *different* account.
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            $errors[] = 'Another account already uses that email.';
        }
    }

    if (empty($errors)) {
        if ($new_password !== '') {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $update = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password_hash = ? WHERE id = ?');
            $update->execute([$full_name, $email, $hash, $userId]);
        } else {
            $update = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
            $update->execute([$full_name, $email, $userId]);
        }

        $_SESSION['admin_flash'] = "Account #$userId updated.";
        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Account — Villaflores Admin</title>
<link rel="stylesheet" href="../style.css">
<style>
    .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
    .auth-card { width: 100%; max-width: 440px; border-radius: 16px; border: 1px solid var(--border); background-color: var(--card); padding: 40px; }
    .auth-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; text-transform: uppercase; }
    .auth-sub { margin-top: 8px; font-size: 14px; color: var(--muted-foreground); }
    .auth-field { margin-top: 20px; display: flex; flex-direction: column; gap: 6px; }
    .auth-field label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted-foreground); }
    .auth-field input { background-color: var(--muted); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; color: var(--foreground); font-family: var(--font-body); font-size: 14px; }
    .auth-field input:focus { outline: none; border-color: var(--cerulean); }
    .auth-hint { font-size: 12px; color: var(--muted-foreground); margin-top: 2px; }
    .auth-submit { margin-top: 28px; width: 100%; border: none; border-radius: 6px; padding: 14px 0; font-family: var(--font-display); font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; background-color: var(--cerulean); color: #000000; cursor: pointer; }
    .auth-errors { margin-top: 20px; border-radius: 8px; border: 1px solid var(--pink); background-color: rgba(246,4,126,0.08); padding: 14px 16px; }
    .auth-errors p { font-size: 13px; color: var(--pink); margin: 4px 0; }
    .auth-footer { margin-top: 20px; font-size: 13px; color: var(--muted-foreground); text-align: center; }
    .auth-footer a { color: var(--cerulean); text-decoration: none; }
</style>
</head>
<body>
<div class="auth-wrap brand-grid">
    <div class="auth-card">
        <h1 class="auth-title">Edit Account</h1>
        <p class="auth-sub">Account #<?= h($userId) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="auth-errors">
                <?php foreach ($errors as $error): ?><p>• <?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_user.php?id=<?= h($userId) ?>" novalidate>
            <input type="hidden" name="id" value="<?= h($userId) ?>">
            <div class="auth-field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old['full_name']); ?>" required>
            </div>
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required>
            </div>
            <div class="auth-field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" maxlength="64">
                <span class="auth-hint">Leave blank to keep their current password.</span>
            </div>
            <button type="submit" class="auth-submit">Save Changes</button>
        </form>

        <p class="auth-footer">← <a href="admin.php">Back to dashboard</a></p>
    </div>
</div>
</body>
</html>