<?php
declare(strict_types=1);

final class DailySettings
{
    public const DEFAULTS = [
        'enabled' => true,
        'domain' => 'tu-dominio.daily.co',
        'api_key' => '',
        'webhook_secret' => '',
        'api_base_url' => 'https://api.daily.co/v1',
        'daily_js_url' => 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js/dist/daily-iframe.js',
        'token_ttl_seconds' => 21600,
        'default_lang' => 'es',
        'transcription_enabled' => true,
        'transcription_auto_start_default' => true,
        'transcription_snapshot_interval_seconds' => 20,
        'minutes_pool_enabled' => true,
        'minutes_pool_total' => 10000,
        'minutes_pool_warning_percent' => 80,
    ];

    public static function normalize(array $settings): array
    {
        $settings = array_merge(self::DEFAULTS, array_intersect_key($settings, self::DEFAULTS));

        return [
            'enabled' => self::bool($settings['enabled']),
            'domain' => self::domain((string) $settings['domain']),
            'api_key' => trim((string) $settings['api_key']),
            'webhook_secret' => trim((string) $settings['webhook_secret']),
            'api_base_url' => self::url((string) $settings['api_base_url'], self::DEFAULTS['api_base_url']),
            'daily_js_url' => self::url((string) $settings['daily_js_url'], self::DEFAULTS['daily_js_url']),
            'token_ttl_seconds' => max(300, min(86400, (int) $settings['token_ttl_seconds'])),
            'default_lang' => self::lang((string) $settings['default_lang']),
            'transcription_enabled' => self::bool($settings['transcription_enabled']),
            'transcription_auto_start_default' => self::bool($settings['transcription_auto_start_default']),
            'transcription_snapshot_interval_seconds' => max(5, min(300, (int) $settings['transcription_snapshot_interval_seconds'])),
            'minutes_pool_enabled' => self::bool($settings['minutes_pool_enabled']),
            'minutes_pool_total' => max(0, (int) $settings['minutes_pool_total']),
            'minutes_pool_warning_percent' => max(1, min(100, (int) $settings['minutes_pool_warning_percent'])),
        ];
    }

    public static function fromPost(array $post, array $current = []): array
    {
        $current = self::normalize($current);

        return self::normalize([
            'enabled' => isset($post['daily_enabled']),
            'domain' => $post['daily_domain'] ?? $current['domain'],
            'api_key' => trim((string) ($post['daily_api_key'] ?? '')) !== '' ? (string) $post['daily_api_key'] : $current['api_key'],
            'webhook_secret' => trim((string) ($post['daily_webhook_secret'] ?? '')) !== '' ? (string) $post['daily_webhook_secret'] : $current['webhook_secret'],
            'api_base_url' => $post['daily_api_base_url'] ?? $current['api_base_url'],
            'daily_js_url' => $post['daily_js_url'] ?? $current['daily_js_url'],
            'token_ttl_seconds' => $post['daily_token_ttl_seconds'] ?? $current['token_ttl_seconds'],
            'default_lang' => $post['daily_default_lang'] ?? $current['default_lang'],
            'transcription_enabled' => isset($post['daily_transcription_enabled']),
            'transcription_auto_start_default' => isset($post['daily_transcription_auto_start_default']),
            'transcription_snapshot_interval_seconds' => $post['daily_transcription_snapshot_interval_seconds'] ?? $current['transcription_snapshot_interval_seconds'],
            'minutes_pool_enabled' => isset($post['daily_minutes_pool_enabled']),
            'minutes_pool_total' => $post['daily_minutes_pool_total'] ?? $current['minutes_pool_total'],
            'minutes_pool_warning_percent' => $post['daily_minutes_pool_warning_percent'] ?? $current['minutes_pool_warning_percent'],
        ]);
    }

    public static function validationErrors(array $settings): array
    {
        $settings = self::normalize($settings);
        $errors = [];
        if ($settings['enabled'] && $settings['domain'] === '') {
            $errors[] = 'El dominio Daily es obligatorio y no puede quedar con el valor de ejemplo.';
        }
        if ($settings['enabled'] && $settings['api_key'] === '') {
            $errors[] = 'La API Key Daily es obligatoria.';
        }
        if ($settings['minutes_pool_enabled'] && $settings['minutes_pool_total'] <= 0) {
            $errors[] = 'La bolsa Daily debe tener minutos contratados mayores a cero.';
        }

        return $errors;
    }

    private static function bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    private static function domain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/^https?:\/\//', '', $value) ?? '';
        $value = trim($value, "/ \t\n\r\0\x0B");
        if (in_array($value, ['tu-dominio.daily.co', 'your-domain.daily.co', 'example.daily.co'], true)) {
            return '';
        }

        return preg_match('/^[a-z0-9.-]+$/', $value) ? $value : '';
    }

    private static function url(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^https:\/\//i', $value) ? $value : $fallback;
    }

    private static function lang(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[a-z]{2}$/', $value) ? $value : 'es';
    }
}
