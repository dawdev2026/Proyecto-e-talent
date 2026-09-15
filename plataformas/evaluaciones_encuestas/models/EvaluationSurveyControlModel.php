<?php
declare(strict_types=1);

final class EvaluationSurveyControlModel
{
    private Database $db;

    private const EVENTS = [
        'attempt_opened', 'supervised_started', 'fullscreen_entered', 'fullscreen_exited',
        'fullscreen_failed', 'fullscreen_denied', 'fullscreen_unavailable', 'tab_hidden', 'inactive_detected',
        'tab_visible', 'window_blurred', 'window_focused', 'answer_changed', 'draft_saved',
        'attempt_completed', 'attempt_expired', 'copy_blocked', 'cut_blocked', 'paste_blocked',
        'print_blocked', 'context_menu_blocked', 'drag_blocked', 'suspicious_key_printscreen',
        'suspicious_key_print', 'suspicious_key_save', 'suspicious_key_copy', 'suspicious_key_devtools',
        'audio_visual_recording_started', 'audio_visual_recording_interrupted', 'audio_visual_recording_recovered',
        'audio_visual_upload_completed', 'audio_visual_upload_failed', 'audio_visual_risk',
        'audio_visual_screen_capture_completed', 'multiple_voice_possible',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('evaluaciones_encuestas');
    }

    public function isAllowedEvent(string $eventType): bool
    {
        return in_array($eventType, self::EVENTS, true);
    }

    public function record(array $attempt, string $eventType, array $metadata = [], int $questionId = 0): void
    {
        if (!$this->isAllowedEvent($eventType) || (int) ($attempt['id'] ?? 0) <= 0) {
            return;
        }
        $mode = (string) ($attempt['control_mode'] ?? 'off');
        if ($mode === 'off') {
            return;
        }
        $safeMetadata = [];
        foreach ($metadata as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            if ($key === '' || strlen($key) > 40) continue;
            if (is_array($value)) $safeMetadata[$key] = array_slice(array_map('strval', $value), 0, 20);
            elseif (is_bool($value) || is_numeric($value)) $safeMetadata[$key] = $value;
            else $safeMetadata[$key] = mb_substr(trim((string) $value), 0, 180);
        }
        $json = $safeMetadata ? json_encode($safeMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $this->db->execute('INSERT INTO evaluation_survey_activity_events (attempt_id,form_id,user_id,event_type,question_id,metadata,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)', [
            (int) $attempt['id'], (int) ($attempt['form_id'] ?? 0), (int) ($attempt['user_id'] ?? 0), $eventType,
            $questionId > 0 ? $questionId : null, $json, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    public function eventsForAttempt(int $attemptId): array
    {
        return $this->db->fetchAll('SELECT * FROM evaluation_survey_activity_events WHERE attempt_id=? ORDER BY created_at ASC,id ASC', [$attemptId]);
    }
}
