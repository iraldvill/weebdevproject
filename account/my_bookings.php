<?php
session_start();
require '../database/config.php';

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

// ---- Let the customer cancel their own pending/confirmed booking ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        // The "AND user_id = ?" here is what stops someone from cancelling
        // a booking that isn't theirs by guessing another booking's id.
        $stmt = $pdo->prepare(
            "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
    }
    header('Location: my_bookings.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT * FROM bookings WHERE user_id = ? ORDER BY booking_date DESC, booking_time DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

$methodLabels = ['gcash' => 'GCash', 'maya' => 'Maya'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings — Villaflores Gaming Cafe</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .bookings-wrap {
            min-height: 100vh;
            padding: 48px 32px;
            display: flex;
            justify-content: center;
        }
        .bookings-container {
            width: 100%;
            max-width: 720px;
        }
        .bookings-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }
        .bookings-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .bookings-sub {
            margin-top: 6px;
            font-size: 14px;
            color: var(--muted-foreground);
        }
        .btn-new-booking {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 12px 22px;
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            background-color: var(--pink);
            color: #000000;
            text-decoration: none;
        }
        .booking-card {
            border-radius: 12px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 24px;
            margin-bottom: 16px;
        }
        .booking-card-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
        }
        .booking-plan {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .booking-meta {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
        }
        .booking-meta-item span {
            display: block;
        }
        .booking-meta-item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted-foreground);
        }
        .booking-meta-item .value {
            margin-top: 2px;
            font-size: 14px;
            color: var(--foreground);
        }
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .badge.confirmed, .badge.active, .badge.paid { background: rgba(0,161,245,0.15); color: var(--cerulean); }
        .badge.pending, .badge.unpaid { background: var(--muted); color: var(--muted-foreground); border: 1px solid var(--border); }
        .badge.cancelled, .badge.failed { background: rgba(246,4,126,0.15); color: var(--pink); }
        .cancel-form {
            margin-top: 16px;
        }
        .cancel-btn {
            background: none;
            border: 1px solid var(--pink);
            color: var(--pink);
            border-radius: 6px;
            padding: 8px 16px;
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .cancel-btn:hover {
            background-color: var(--pink);
            color: #000000;
        }
        .empty-state {
            border-radius: 12px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 40px;
            text-align: center;
            color: var(--muted-foreground);
            font-size: 14px;
        }
        .auth-footer {
            margin-top: 24px;
            font-size: 14px;
            color: var(--muted-foreground);
            text-align: center;
        }
        .auth-footer a {
            color: var(--cerulean);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="bookings-wrap brand-grid">
        <div class="bookings-container">
            <div class="bookings-head">
                <div>
                    <div class="bookings-title">My Bookings</div>
                    <div class="bookings-sub">Hi <?php echo htmlspecialchars($_SESSION['full_name']); ?>, here's everything you've booked.</div>
                </div>
                <a href="../booking/booking.php" class="btn-new-booking">+ New Booking</a>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="empty-state">You haven't booked a seat yet.</div>
            <?php else: foreach ($bookings as $b):
                $status = strtolower($b['status'] ?? '');
                $statusBadge = in_array($status, ['confirmed', 'active', 'pending', 'cancelled']) ? $status : 'default';

                $paymentStatus = strtolower($b['payment_status'] ?? '');
                $paymentBadge  = in_array($paymentStatus, ['paid', 'unpaid', 'failed']) ? $paymentStatus : 'default';
                $methodLabel   = $methodLabels[$b['payment_method']] ?? '—';

                $canCancel = in_array($status, ['pending', 'confirmed'], true);
            ?>
                <div class="booking-card">
                    <div class="booking-card-head">
                        <span class="booking-plan"><?php echo htmlspecialchars($b['plan_name']); ?></span>
                        <span class="badge <?php echo htmlspecialchars($statusBadge); ?>"><?php echo htmlspecialchars($b['status']); ?></span>
                    </div>

                    <div class="booking-meta">
                        <div class="booking-meta-item">
                            <span class="label">Date</span>
                            <span class="value"><?php echo htmlspecialchars($b['booking_date']); ?></span>
                        </div>
                        <div class="booking-meta-item">
                            <span class="label">Time</span>
                            <span class="value"><?php echo htmlspecialchars($b['booking_time']); ?></span>
                        </div>
                        <div class="booking-meta-item">
                            <span class="label">Stations</span>
                            <span class="value"><?php echo htmlspecialchars($b['stations']); ?></span>
                        </div>
                        <div class="booking-meta-item">
                            <span class="label">Payment</span>
                            <span class="value">
                                <?php echo htmlspecialchars($methodLabel); ?> —
                                <span class="badge <?php echo htmlspecialchars($paymentBadge); ?>"><?php echo htmlspecialchars($b['payment_status'] ?? '—'); ?></span>
                            </span>
                        </div>
                    </div>

                    <?php if ($canCancel): ?>
                        <form class="cancel-form" method="POST" action="my_bookings.php" onsubmit="return confirm('Cancel this booking?');">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                            <button type="submit" class="cancel-btn">Cancel Booking</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>

            <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
        </div>
    </div>
</body>
</html>