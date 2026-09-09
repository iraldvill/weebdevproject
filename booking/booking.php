<?php
session_start();
require '../database/config.php';

// ---- Require login ----
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

$allowedPlans   = ['Hourly Gaming', 'Extended Gaming', 'Gaming Package'];
$allowedMethods = ['gcash', 'maya'];

// Price per plan, in pesos. Flat per booking, matching the prices shown
// on the homepage — not multiplied by station count.
$planPrices = [
    'Hourly Gaming'   => 50,
    'Extended Gaming' => 200,
    'Gaming Package'  => 180,
];

// Plan can arrive via ?plan=... from the homepage's Choose Plan buttons.
$selectedPlan = $_GET['plan'] ?? '';
if (!in_array($selectedPlan, $allowedPlans, true)) {
    $selectedPlan = '';
}

$errors  = [];
$success = false;
$successMethod = '';

$old = [
    'plan'           => $selectedPlan,
    'date'           => '',
    'time'           => '',
    'stations'       => 1,
    'payment_method' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan          = $_POST['plan'] ?? '';
    $date          = $_POST['booking_date'] ?? '';
    $time          = $_POST['booking_time'] ?? '';
    $stations      = (int)($_POST['stations'] ?? 1);
    $paymentMethod = $_POST['payment_method'] ?? '';

    $old = [
        'plan'           => $plan,
        'date'           => $date,
        'time'           => $time,
        'stations'       => $stations,
        'payment_method' => $paymentMethod,
    ];

    // ---- Validation (server-side, the real gate) ----
    if (!in_array($plan, $allowedPlans, true)) {
        $errors[] = 'Please choose a valid plan.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    $today   = new DateTime('today');
    if (!$dateObj || $dateObj < $today) {
        $errors[] = 'Please choose a valid date that is today or later.';
    }

    if (!DateTime::createFromFormat('H:i', $time)) {
        $errors[] = 'Please choose a valid time.';
    }

    if ($stations < 1 || $stations > 30) {
        $errors[] = 'Number of stations must be between 1 and 30.';
    }

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please choose where you will pay.';
    }

    // ---- Save the booking (payment is recorded, not processed) ----
    if (empty($errors)) {
        $amountCentavos = $planPrices[$plan] * 100;

        $stmt = $pdo->prepare(
            'INSERT INTO bookings (user_id, plan_name, booking_date, booking_time, stations, amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $plan, $date, $time, $stations, $amountCentavos, $paymentMethod]);

        $success       = true;
        $successMethod = $paymentMethod;
        $old = ['plan' => '', 'date' => '', 'time' => '', 'stations' => 1, 'payment_method' => ''];
    }
}

$methodLabels = ['gcash' => 'GCash', 'maya' => 'Maya'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Seat — Villaflores Gaming Cafe</title>
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
            max-width: 480px;
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
        .auth-field input,
        .auth-field select {
            background-color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--foreground);
            font-family: var(--font-body);
            font-size: 14px;
        }
        .auth-field input:focus,
        .auth-field select:focus {
            outline: none;
            border-color: var(--cerulean);
        }
        .auth-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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
        .auth-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            margin: 4px 0;
        }
        .payment-picked-row {
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--muted);
            padding: 12px 16px;
        }
        .payment-picked-row span {
            font-size: 14px;
            color: var(--foreground);
        }
        .payment-picked-row button {
            background: none;
            border: none;
            color: var(--cerulean);
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
        }

        /* ---- Payment method pop-up ---- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 24px;
        }
        .modal-box {
            width: 100%;
            max-width: 380px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 32px;
            text-align: center;
        }
        .modal-box h2 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .modal-box p {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted-foreground);
        }
        .payment-modal-options {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .payment-modal-options button {
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--muted);
            color: var(--foreground);
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 14px 0;
            cursor: pointer;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .payment-modal-options button.gcash-option:hover {
            border-color: var(--cerulean);
            color: var(--cerulean);
        }
        .payment-modal-options button.maya-option:hover {
            border-color: var(--pink);
            color: var(--pink);
        }

        /* ---- Review / confirm pop-up ---- */
        .review-list {
            margin-top: 20px;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .review-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }
        .review-row span:first-child {
            color: var(--muted-foreground);
        }
        .review-row span:last-child {
            color: var(--foreground);
            font-weight: 600;
            text-align: right;
        }
        .review-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
        }
        .review-actions button {
            flex: 1;
            border-radius: 6px;
            padding: 12px 0;
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
            border: none;
        }
        .review-cancel {
            background-color: var(--muted);
            color: var(--foreground);
            border: 1px solid var(--border) !important;
        }
        .review-confirm {
            background-color: var(--pink);
            color: #000000;
        }
    </style>
</head>
<body>

    <?php if (!$success): ?>
    <!-- Payment method pop-up: blocks the form until a choice is made -->
    <div class="modal-overlay" id="paymentModalOverlay">
        <div class="modal-box">
            <h2>Where will you pay?</h2>
            <p>Choose a payment method to continue booking.</p>
            <div class="payment-modal-options">
                <button type="button" class="gcash-option" onclick="choosePaymentMethod('gcash', 'GCash')">GCash</button>
                <button type="button" class="maya-option" onclick="choosePaymentMethod('maya', 'Maya')">Maya</button>
            </div>
        </div>
    </div>

    <!-- Review pop-up: shown right before the booking is actually submitted -->
    <div class="modal-overlay" id="reviewModalOverlay" style="display: none;">
        <div class="modal-box">
            <h2>Confirm Your Booking</h2>
            <p>Please double check the details below before booking.</p>
            <div class="review-list">
                <div class="review-row"><span>Plan</span><span id="reviewPlan">—</span></div>
                <div class="review-row"><span>Date</span><span id="reviewDate">—</span></div>
                <div class="review-row"><span>Time</span><span id="reviewTime">—</span></div>
                <div class="review-row"><span>Stations</span><span id="reviewStations">—</span></div>
                <div class="review-row"><span>Payment method</span><span id="reviewMethod">—</span></div>
            </div>
            <div class="review-actions">
                <button type="button" class="review-cancel" onclick="closeReviewModal()">Go Back</button>
                <button type="button" class="review-confirm" onclick="submitBooking()">Confirm Booking</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="auth-wrap brand-grid">
        <div class="auth-card">
            <h1 class="auth-title">Book a Seat</h1>
            <p class="auth-sub">Hi <?php echo htmlspecialchars($_SESSION['full_name']); ?>, reserve your gaming station below.</p>

            <?php if ($success): ?>
                <div class="auth-success">
                    <p><strong>Booking submitted!</strong></p>
                    <p>
                        To confirm your seat, please send your payment via
                        <strong><?php echo htmlspecialchars($methodLabels[$successMethod]); ?></strong> to
                        <strong><?php echo htmlspecialchars($successMethod === 'gcash' ? CAFE_GCASH_NUMBER : CAFE_MAYA_NUMBER); ?></strong>.
                        Our staff will verify and confirm your booking shortly.
                    </p>
                </div>
                <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="auth-errors">
                        <?php foreach ($errors as $error): ?>
                            <p>• <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="booking.php" novalidate id="bookingForm">
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="<?php echo htmlspecialchars($old['payment_method']); ?>">

                    <div class="auth-field">
                        <label for="plan">Plan</label>
                        <select id="plan" name="plan" required>
                            <option value="" disabled <?php echo $old['plan'] === '' ? 'selected' : ''; ?>>Select a plan</option>
                            <?php foreach ($allowedPlans as $planOption): ?>
                                <option value="<?php echo htmlspecialchars($planOption); ?>" <?php echo $old['plan'] === $planOption ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($planOption); ?> (₱<?php echo number_format($planPrices[$planOption], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="auth-row">
                        <div class="auth-field">
                            <label for="booking_date">Date</label>
                            <input type="date" id="booking_date" name="booking_date" value="<?php echo htmlspecialchars($old['date']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="auth-field">
                            <label for="booking_time">Time</label>
                            <input type="time" id="booking_time" name="booking_time" value="<?php echo htmlspecialchars($old['time']); ?>" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="stations">Number of Stations</label>
                        <input type="number" id="stations" name="stations" min="1" max="30" value="<?php echo htmlspecialchars($old['stations']); ?>" required>
                    </div>

                    <div class="payment-picked-row">
                        <span>Paying via: <strong id="paymentMethodLabel"><?php echo htmlspecialchars($methodLabels[$old['payment_method']] ?? 'Not selected'); ?></strong></span>
                        <button type="button" onclick="openPaymentModal()">Change</button>
                    </div>

                    <button type="button" class="auth-submit" id="reviewBtn" onclick="openReviewModal()">Proceed to Book</button>
                </form>

                <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
                <p class="auth-footer" style="margin-top: 8px; font-size: 12px;">
                    <a href="../account/delete_account.php" style="color: var(--muted-foreground);">Delete my account</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$success): ?>
    <script>
        const paymentOverlay = document.getElementById('paymentModalOverlay');
        const reviewOverlay  = document.getElementById('reviewModalOverlay');
        const methodInput    = document.getElementById('paymentMethodInput');
        const methodLabel    = document.getElementById('paymentMethodLabel');
        const reviewBtn      = document.getElementById('reviewBtn');
        const form           = document.getElementById('bookingForm');

        const methodDisplayNames = { gcash: 'GCash', maya: 'Maya' };

        function choosePaymentMethod(value, label) {
            methodInput.value = value;
            methodLabel.textContent = label;
            paymentOverlay.style.display = 'none';
            reviewBtn.disabled = false;
        }

        function openPaymentModal() {
            paymentOverlay.style.display = 'flex';
        }

        // If a method was already picked (e.g. form re-rendered after a
        // validation error), skip showing the pop-up again.
        if (methodInput.value) {
            paymentOverlay.style.display = 'none';
        } else {
            reviewBtn.disabled = true;
        }

        function openReviewModal() {
            // Basic client-side check before showing the review pop-up —
            // the server re-checks everything regardless.
            if (!form.reportValidity()) {
                return;
            }
            if (!methodInput.value) {
                openPaymentModal();
                return;
            }

            const plan     = document.getElementById('plan').value;
            const date     = document.getElementById('booking_date').value;
            const time     = document.getElementById('booking_time').value;
            const stations = document.getElementById('stations').value;

            document.getElementById('reviewPlan').textContent     = plan || '—';
            document.getElementById('reviewDate').textContent     = date || '—';
            document.getElementById('reviewTime').textContent     = time || '—';
            document.getElementById('reviewStations').textContent = stations || '—';
            document.getElementById('reviewMethod').textContent   = methodDisplayNames[methodInput.value] || '—';

            reviewOverlay.style.display = 'flex';
        }

        function closeReviewModal() {
            reviewOverlay.style.display = 'none';
        }

        function submitBooking() {
            form.submit();
        }
    </script>
    <?php endif; ?>
</body>
</html>