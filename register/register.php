<?php
session_start();
require '../database/config.php';

$errors = [];
$old = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old['full_name'] = $full_name;
    $old['email']      = $email;

    // ---- Validation ----
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 8 || strlen($password) > 64) {
        $errors[] = 'Password must be between 8 and 64 characters.';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-\[\]\/\\\\+=~`\';]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)'
            );
            $insert->execute([$full_name, $email, $hash]);

            $_SESSION['registered'] = true;
            header('Location: ../login/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Villaflores Gaming Cafe</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .auth-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
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
        .auth-field input {
            background-color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--foreground);
            font-family: var(--font-body);
            font-size: 14px;
        }
        .auth-field input:focus {
            outline: none;
            border-color: var(--cerulean);
        }
        .auth-hint {
            font-size: 12px;
            color: var(--muted-foreground);
            margin-top: 2px;
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
        .auth-footer.small {
            margin-top: 8px;
            font-size: 12px;
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
    </style>
</head>
<body>
    <div class="auth-wrap brand-grid">
        <div class="auth-card">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-sub">Sign up to book a gaming station at Villaflores.</p>

            <?php if (!empty($errors)): ?>
                <div class="auth-errors">
                    <?php foreach ($errors as $error): ?>
                        <p>• <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" novalidate>
                <div class="auth-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old['full_name']); ?>" required>
                </div>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="64" required>
                    <span class="auth-hint">8–64 characters, at least 1 special character (e.g. ! @ # $ %).</span>
                </div>

                <div class="auth-field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="64" required>
                </div>

                <button type="submit" class="auth-submit">Register</button>
            </form>

            <p class="auth-footer">Already have an account? <a href="../login/login.php">Log in</a></p>
            <p class="auth-footer small">Are you staff? <a href="../admin/admin_register.php">Register as admin</a></p>
        </div>
    </div>
</body>
</html>