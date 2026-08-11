<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/DailySettings.php';

$dailySettings = DailySettings::normalize(is_array($dailySettings ?? null) ? $dailySettings : []);
$dailySettingsEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>

<section class="daily-kit-settings">
    <div class="daily-kit-settings-header">
        <h2>Configuracion Daily</h2>
        <p>Centraliza videollamada, transcripcion y bolsa de minutos.</p>
    </div>

    <div class="daily-kit-settings-grid">
        <label class="daily-kit-field daily-kit-switch">
            <input type="checkbox" name="daily_enabled" value="1" <?= !empty($dailySettings['enabled']) ? 'checked' : '' ?>>
            <span>Habilitar Daily</span>
        </label>

        <label class="daily-kit-field">
            <span>Dominio Daily</span>
            <input type="text" name="daily_domain" value="<?= $dailySettingsEscape((string) $dailySettings['domain']) ?>" placeholder="tu-dominio.daily.co">
        </label>

        <label class="daily-kit-field">
            <span>API Key</span>
            <input type="password" name="daily_api_key" value="" placeholder="<?= $dailySettings['api_key'] !== '' ? 'API Key guardada' : 'Daily API Key' ?>">
        </label>

        <label class="daily-kit-field">
            <span>Webhook secret</span>
            <input type="password" name="daily_webhook_secret" value="" placeholder="<?= $dailySettings['webhook_secret'] !== '' ? 'Webhook guardado' : 'Opcional' ?>">
        </label>

        <label class="daily-kit-field">
            <span>TTL token</span>
            <input type="number" min="300" max="86400" step="60" name="daily_token_ttl_seconds" value="<?= (int) $dailySettings['token_ttl_seconds'] ?>">
        </label>

        <label class="daily-kit-field">
            <span>Idioma</span>
            <input type="text" maxlength="2" name="daily_default_lang" value="<?= $dailySettingsEscape((string) $dailySettings['default_lang']) ?>">
        </label>

        <label class="daily-kit-field daily-kit-switch">
            <input type="checkbox" name="daily_transcription_enabled" value="1" <?= !empty($dailySettings['transcription_enabled']) ? 'checked' : '' ?>>
            <span>Habilitar transcripcion</span>
        </label>

        <label class="daily-kit-field daily-kit-switch">
            <input type="checkbox" name="daily_transcription_auto_start_default" value="1" <?= !empty($dailySettings['transcription_auto_start_default']) ? 'checked' : '' ?>>
            <span>Auto-iniciar transcripcion</span>
        </label>

        <label class="daily-kit-field">
            <span>Guardado incremental</span>
            <input type="number" min="5" max="300" step="1" name="daily_transcription_snapshot_interval_seconds" value="<?= (int) $dailySettings['transcription_snapshot_interval_seconds'] ?>">
            <small>Siempre expresado en segundos.</small>
        </label>

        <label class="daily-kit-field daily-kit-switch">
            <input type="checkbox" name="daily_minutes_pool_enabled" value="1" <?= !empty($dailySettings['minutes_pool_enabled']) ? 'checked' : '' ?>>
            <span>Controlar bolsa de minutos</span>
        </label>

        <label class="daily-kit-field">
            <span>Minutos contratados</span>
            <input type="number" min="0" step="1" name="daily_minutes_pool_total" value="<?= (int) $dailySettings['minutes_pool_total'] ?>">
        </label>

        <label class="daily-kit-field">
            <span>Avisar desde</span>
            <input type="number" min="1" max="100" step="1" name="daily_minutes_pool_warning_percent" value="<?= (int) $dailySettings['minutes_pool_warning_percent'] ?>">
            <small>Porcentaje de uso/proyeccion de la bolsa.</small>
        </label>
    </div>
</section>
