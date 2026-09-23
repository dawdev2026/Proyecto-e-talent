<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 3) . '/sistema/bootstrap.php';

$maxJobs = 10;
$daemon = in_array('--daemon', $argv, true);
foreach ($argv as $argument) {
    if (strpos($argument, '--max-jobs=') === 0) {
        $maxJobs = max(1, min(100, (int) substr($argument, 11)));
    }
}

do {
    $controller = new InterviewController();
    $processed = 0;
    $types = ['moderator_brief', 'final_report'];

    foreach ($types as $type) {
        while ($processed < $maxJobs && $controller->processOneJob($type)) {
            $processed++;
        }
    }

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'processed' => $processed,
        'max_jobs' => $maxJobs,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL);

    if ($daemon) {
        sleep(2);
    }
} while ($daemon);
