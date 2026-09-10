<?php

session_start();
require '../database/config.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$timeObj = DateTime::createFromFormat('H:i', $time);

if (!$dateObj || !$timeObj) {
    echo json_encode(['booked' => [], 'error' => 'Invalid date or time.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT station_number FROM booking_stations WHERE booking_date = ? AND booking_time = ?'
);
$stmt->execute([$date, $time . ':00']);
$booked = array_map('intval', array_column($stmt->fetchAll(), 'station_number'));

echo json_encode(['booked' => $booked]);