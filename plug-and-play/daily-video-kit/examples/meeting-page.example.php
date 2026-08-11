<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/DailyMeetingService.php';
require_once __DIR__ . '/../backend/DailySettings.php';

$config = DailySettings::normalize(require __DIR__ . '/../backend/daily_config.example.php');
$daily = new DailyMeetingService($config);

// Reemplaza estos valores por los datos reales del proyecto destino.
$currentUser = [
    'id' => 123,
    'name' => 'Participante Demo',
    'is_moderator' => true,
];

$meeting = $daily->meetingPayload([
    'room_slug' => 'demo-sala-daily',
    'user_name' => $currentUser['name'],
    'user_id' => 'user-' . $currentUser['id'],
    'is_owner' => $currentUser['is_moderator'],
    'transcription_enabled' => true,
    'transcription_auto_start' => true,
    'transcription_url' => '/daily/transcription-snapshot',
    'transcription_snapshot_interval_seconds' => 20,
    'csrf_token' => 'csrf-demo',
    'minutes_pool' => [
        'remaining_minutes' => 10000,
        'future_required_minutes' => 450,
        'projected_balance_minutes' => 9550,
    ],
]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Video Kit</title>
    <link rel="stylesheet" href="../frontend/daily-meeting.css">
</head>
<body>
    <main style="max-width: 1100px; margin: 2rem auto; padding: 0 1rem;">
        <h1>Ejemplo Daily Video Kit</h1>
        <?php $dailySettings = $config; ?>
        <?php require __DIR__ . '/../views/daily-settings.partial.php'; ?>
        <hr>
        <?php require __DIR__ . '/../views/daily-meeting.partial.php'; ?>
    </main>
    <script src="../frontend/daily-meeting.js" defer></script>
</body>
</html>
