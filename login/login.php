<?php
session_start();

$justRegistered = !empty($_SESSION['registered']);
unset($_SESSION['registered']);

$accountDeleted = !empty($_SESSION['account_deleted']);
unset($_SESSION['account_deleted']);

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$oldEmail = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In — Villaflores Gaming Cafe</title>
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
            background-color: var(--cerulean);
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
            margin: 0;
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
    </style>
</head>
<body>
    <div class="auth-wrap brand-grid">
        <div class="auth-card">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-sub">Log in to book your next gaming session.</p>

            <?php if ($justRegistered): ?>
                <div class="auth-success">
                    <p>Account created successfully. You can now log in.</p>
                </div>
            <?php endif; ?>

            <?php if ($accountDeleted): ?>
                <div class="auth-success">
                    <p>Your account has been deleted.</p>
                </div>
            <?php endif; ?>

            <?php if ($loginError): ?>
                <div class="auth-errors">
                    <p><?php echo htmlspecialchars($loginError); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="login_function.php" novalidate>
                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($oldEmail); ?>" required>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="auth-submit">Log In</button>
            </form>

            <p class="auth-footer">Don't have an account? <a href="../register/register.php">Register</a></p>
        </div>
    </div>
</body>
</html>