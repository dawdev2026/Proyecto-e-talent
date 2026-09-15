<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 3) . '/sistema/bootstrap.php';

$maxJobs = 5;
$daemon = in_array('--daemon', $argv, true);
foreach ($argv as $argument) {
    if (strpos($argument, '--max-jobs=') === 0) {
        $maxJobs = max(1, min(50, (int) substr($argument, 11)));
    }
}

do {
    $service = new EvaluationSurveyMediaProcessingService();
    $processed = 0;
    while ($processed < $maxJobs && $service->processNext()) {
        $processed++;
    }
    $testService = new TestMediaProcessingService();
    while ($processed < $maxJobs && $testService->processNext()) {
        $processed++;
    }
    fwrite(STDOUT, json_encode(['ok' => true, 'processed' => $processed, 'max_jobs' => $maxJobs], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    if ($daemon) {
        sleep(2);
    }
} while ($daemon);
