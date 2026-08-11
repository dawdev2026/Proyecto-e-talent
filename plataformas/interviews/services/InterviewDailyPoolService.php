<?php
declare(strict_types=1);

final class InterviewDailyPoolService
{
    private InterviewProcessModel $interviews;

    public function __construct(?InterviewProcessModel $interviews = null)
    {
        $this->interviews = $interviews ?: new InterviewProcessModel();
    }

    public function summary(array $dailySettings): array
    {
        $settings = DailySettings::normalize($dailySettings);
        $total = max(0, (int) $settings['minutes_pool_total']);
        $warningPercent = max(1, min(100, (int) $settings['minutes_pool_warning_percent']));
        $futureProjection = DailyMeetingService::futureMinutesProjection($this->interviews->futureDailySubEvents());
        $usage = [
            'ok' => false,
            'error' => '',
            'participant_minutes' => 0,
            'meeting_minutes' => 0,
            'meetings' => 0,
            'participants' => 0,
        ];

        if (!empty($settings['enabled']) && trim((string) $settings['api_key']) !== '' && trim((string) $settings['domain']) !== '') {
            $daily = new DailyMeetingService($settings);
            $usage = $daily->meetingUsageSummary($this->periodStart(), time());
        } elseif (!empty($settings['enabled'])) {
            $usage['error'] = 'Configura dominio y API Key Daily para consultar consumo real.';
        } else {
            $usage['error'] = 'Daily esta inactivo.';
        }

        $consumed = max(0, (int) ($usage['participant_minutes'] ?? 0));
        $futureRequired = max(0, (int) ($futureProjection['required_minutes'] ?? 0));
        $remainingReal = max(0, $total - $consumed);
        $remainingProjected = max(0, $remainingReal - $futureRequired);
        $consumedPercent = $total > 0 ? min(100, (int) round(($consumed / $total) * 100)) : 0;
        $committedPercent = $total > 0 ? min(100, (int) round((($consumed + $futureRequired) / $total) * 100)) : 0;
        $status = 'sufficient';

        if (!$settings['minutes_pool_enabled']) {
            $status = 'disabled';
        } elseif ($total <= 0 || $remainingProjected <= 0) {
            $status = 'critical';
        } elseif ($committedPercent >= $warningPercent) {
            $status = 'warning';
        }

        return [
            'enabled' => (bool) $settings['minutes_pool_enabled'],
            'ok' => !empty($usage['ok']),
            'error' => (string) ($usage['error'] ?? ''),
            'queried_at' => date('d/m/Y H:i'),
            'period_label' => date('d/m/Y', $this->periodStart()),
            'contracted_minutes' => $total,
            'consumed_minutes' => $consumed,
            'remaining_real_minutes' => $remainingReal,
            'future_required_minutes' => $futureRequired,
            'remaining_projected_minutes' => $remainingProjected,
            'consumed_percent' => $consumedPercent,
            'committed_percent' => $committedPercent,
            'warning_percent' => $warningPercent,
            'future_items' => $futureProjection['items'] ?? [],
            'future_quantifiable_count' => (int) ($futureProjection['quantifiable_count'] ?? 0),
            'future_unquantifiable_count' => (int) ($futureProjection['unquantifiable_count'] ?? 0),
            'meetings' => (int) ($usage['meetings'] ?? 0),
            'participants' => (int) ($usage['participants'] ?? 0),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'status_class' => $this->statusClass($status),
        ];
    }

    private function periodStart(): int
    {
        return (new DateTimeImmutable('first day of this month 00:00:00'))->getTimestamp();
    }

    private function statusLabel(string $status): string
    {
        return [
            'disabled' => 'Inactivo',
            'critical' => 'Critico',
            'warning' => 'Advertencia',
            'sufficient' => 'Suficiente',
        ][$status] ?? 'Suficiente';
    }

    private function statusClass(string $status): string
    {
        return [
            'disabled' => 'text-bg-secondary',
            'critical' => 'text-bg-danger',
            'warning' => 'text-bg-warning',
            'sufficient' => 'text-bg-success',
        ][$status] ?? 'text-bg-success';
    }
}
