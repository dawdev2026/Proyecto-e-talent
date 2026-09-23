<?php
declare(strict_types=1);

final class InterviewSettings
{
    public const DEFAULT_AI = [
        'enabled' => false,
        'provider' => 'openai_compatible',
        'base_url' => 'https://api.openai.com/v1',
        'api_key' => '',
        'model' => '',
        'timeout_seconds' => 45,
        'prompt_version' => 'e_talent-interviews-v1',
    ];

    public static function daily(): array
    {
        $integrations = load_config('integrations');
        $daily = $integrations['daily'] ?? [];

        return DailySettings::normalize(is_array($daily) ? $daily : []);
    }

    public static function ai(): array
    {
        return (new PlatformSettingsModel())->aiSettings(load_config('integrations'), true);
    }

    public static function save(array $post): void
    {
        $integrations = load_config('integrations');
        $currentDaily = self::daily();
        $currentAi = self::ai();

        $integrations['daily'] = DailySettings::fromPost($post, $currentDaily);
        $integrations['interviews_ai'] = [
            'enabled' => isset($post['ai_enabled']),
            'provider' => trim((string) ($post['ai_provider'] ?? $currentAi['provider'])) ?: self::DEFAULT_AI['provider'],
            'base_url' => self::httpsUrl((string) ($post['ai_base_url'] ?? $currentAi['base_url']), self::DEFAULT_AI['base_url']),
            'api_key' => trim((string) ($post['ai_api_key'] ?? '')) !== '' ? trim((string) $post['ai_api_key']) : $currentAi['api_key'],
            'model' => trim((string) ($post['ai_model'] ?? $currentAi['model'])),
            'timeout_seconds' => max(10, min(180, (int) ($post['ai_timeout_seconds'] ?? $currentAi['timeout_seconds']))),
            'prompt_version' => trim((string) ($post['ai_prompt_version'] ?? $currentAi['prompt_version'])) ?: self::DEFAULT_AI['prompt_version'],
        ];

        write_secure_config('integrations', $integrations);
    }

    public static function validationErrors(array $daily, array $ai): array
    {
        $errors = DailySettings::validationErrors($daily);
        if (!empty($ai['enabled']) && trim((string) ($ai['api_key'] ?? '')) === '') {
            $errors[] = 'La API Key de IA es obligatoria si la generacion automatica esta activa.';
        }
        if (!empty($ai['enabled']) && trim((string) ($ai['model'] ?? '')) === '') {
            $errors[] = 'El modelo de IA es obligatorio si la generacion automatica esta activa.';
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

    private static function httpsUrl(string $value, string $fallback): string
    {
        $value = rtrim(trim($value), '/');

        return preg_match('/^https:\/\//i', $value) ? $value : $fallback;
    }
}
