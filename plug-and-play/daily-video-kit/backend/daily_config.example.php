<?php
declare(strict_types=1);

return [
    'enabled' => true,
    'domain' => 'tu-dominio.daily.co',
    'api_key' => 'REEMPLAZAR_CON_DAILY_API_KEY',
    'api_base_url' => 'https://api.daily.co/v1',
    'daily_js_url' => 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js/dist/daily-iframe.js',
    'token_ttl_seconds' => 21600,
    'default_lang' => 'es',
    'transcription_enabled' => true,
    'transcription_snapshot_interval_seconds' => 20,
    'minutes_pool_total' => 10000,
    'minutes_pool_warning_percent' => 80,
];
