<?php
declare(strict_types=1);

$meeting = is_array($meeting ?? null) ? $meeting : [];
$dailyEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>

<div class="daily-kit-shell" data-daily-shell>
    <?php if (empty($meeting['ok'])): ?>
        <div class="daily-kit-state">
            <div class="daily-kit-icon" aria-hidden="true">!</div>
            <h2>Videollamada no disponible</h2>
            <p><?= $dailyEscape((string) ($meeting['error'] ?? 'No se pudo preparar la sala.')) ?></p>
        </div>
    <?php else: ?>
        <div class="daily-kit-launcher" data-daily-launcher>
            <h2>Sala lista para ingresar</h2>
            <p>La videollamada se abrira dentro de esta pagina.</p>
            <button class="daily-kit-button" type="button" data-daily-launch>Unirse</button>
        </div>

        <div
            class="daily-kit-meeting"
            data-daily-meeting
            data-daily-url="<?= $dailyEscape((string) ($meeting['url'] ?? '')) ?>"
            data-daily-token="<?= $dailyEscape((string) ($meeting['token'] ?? '')) ?>"
            data-daily-user="<?= $dailyEscape((string) ($meeting['user_name'] ?? 'Participante')) ?>"
            data-daily-room="<?= $dailyEscape((string) ($meeting['room_name'] ?? '')) ?>"
            data-daily-api="<?= $dailyEscape((string) ($meeting['api_url'] ?? '')) ?>"
            data-daily-transcription-enabled="<?= !empty($meeting['transcription_enabled']) ? '1' : '0' ?>"
            data-daily-transcription-auto-start="<?= !empty($meeting['transcription_auto_start']) ? '1' : '0' ?>"
            data-transcription-url="<?= $dailyEscape((string) ($meeting['transcription_url'] ?? '')) ?>"
            data-transcription-snapshot-interval="<?= (int) ($meeting['transcription_snapshot_interval_seconds'] ?? 20) ?>"
            data-csrf-token="<?= $dailyEscape((string) ($meeting['csrf_token'] ?? '')) ?>"
            data-daily-title="Videollamada"
            data-daily-min-height="640px"
            hidden>
            <div class="daily-kit-state">
                <div class="daily-kit-icon" aria-hidden="true">...</div>
                <h2>Preparando sala</h2>
                <p>La videollamada se cargara en unos segundos.</p>
            </div>
        </div>

        <?php if (!empty($meeting['transcription_enabled'])): ?>
            <div class="daily-kit-transcription" data-daily-transcription-panel hidden>
                <span data-transcription-status>Transcripcion disponible</span>
                <div class="daily-kit-transcription-actions">
                    <button class="daily-kit-button daily-kit-button-secondary" type="button" data-transcription-start>Iniciar transcripcion</button>
                    <button class="daily-kit-button daily-kit-button-secondary" type="button" data-transcription-stop hidden>Detener transcripcion</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($meeting['minutes_pool'])): ?>
            <?php $pool = is_array($meeting['minutes_pool']) ? $meeting['minutes_pool'] : []; ?>
            <div class="daily-kit-pool">
                <strong>Bolsa Daily</strong>
                <span>
                    <?= number_format((int) ($pool['remaining_minutes'] ?? 0), 0, ',', '.') ?> min restantes /
                    <?= number_format((int) ($pool['future_required_minutes'] ?? 0), 0, ',', '.') ?> min requeridos futuros.
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
