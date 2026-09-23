<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

date_default_timezone_set('America/Santiago');

$baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ranking_reports';
$daemon = in_array('--daemon', $argv, true);
$interval = 60;
foreach ($argv as $argument) {
    if (strpos($argument, '--interval=') === 0) {
        $interval = max(30, min(3600, (int) substr($argument, 11)));
    }
}

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            $removeTree($child);
        } elseif (is_file($child)) {
            @unlink($child);
        }
    }
    @rmdir($path);
};

do {
    $removed = 0;
    $todayStart = strtotime('today');
    if (is_dir($baseDir)) {
        foreach ((array) scandir($baseDir) as $item) {
            if ($item === '.' || $item === '..' || !preg_match('/^[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $item)) {
                continue;
            }
            $path = $baseDir . DIRECTORY_SEPARATOR . $item;
            $modifiedAt = @filemtime($path);
            if ($modifiedAt !== false && $modifiedAt < $todayStart) {
                $removeTree($path);
                $removed++;
            }
        }
    }

    fwrite(STDOUT, json_encode(['ok' => true, 'removed' => $removed, 'checked_at' => date('c')], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    if ($daemon) {
        sleep($interval);
    }
} while ($daemon);
