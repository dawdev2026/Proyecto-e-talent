<?php
declare(strict_types=1);

final class ProcessPrerequisiteService
{
    private const POLICIES = ['inherit', 'required', 'disabled'];

    /** Resolve process-level overrides against the activity's existing configuration. */
    public static function resolve(array $activity): array
    {
        $baseMode = (string) ($activity['control_mode'] ?? 'off');
        if (!in_array($baseMode, ['off', 'activity', 'supervised', 'supervised_audio_visual'], true)) {
            $baseMode = 'off';
        }
        $baseActions = (int) ($activity['track_activity_enabled'] ?? 0) === 1
            || in_array($baseMode, ['activity', 'supervised', 'supervised_audio_visual'], true);
        $avPolicy = self::policy($activity['audio_visual_recording_policy'] ?? 'inherit');
        $actionsPolicy = self::policy($activity['action_logging_policy'] ?? 'inherit');
        $componentPolicy = self::policy($activity['component_validation_policy'] ?? 'inherit');
        $facialPolicy = self::policy($activity['facial_enrollment_policy'] ?? 'inherit');

        $recordAudioVisual = $avPolicy === 'required'
            || ($avPolicy === 'inherit' && $baseMode === 'supervised_audio_visual');
        $recordActions = $actionsPolicy === 'required'
            || ($actionsPolicy === 'inherit' && $baseActions);
        $mode = $baseMode;
        if ($avPolicy === 'required') {
            $mode = 'supervised_audio_visual';
        } elseif ($avPolicy === 'disabled' && $mode === 'supervised_audio_visual') {
            $mode = 'supervised';
        }
        if ($actionsPolicy === 'required' && $mode === 'off') {
            $mode = 'activity';
        } elseif ($actionsPolicy === 'disabled' && $mode === 'activity') {
            $mode = 'off';
        }
        if ($recordAudioVisual) {
            $mode = 'supervised_audio_visual';
        }

        $legacyFacialRequired = (int) ($activity['require_facial_enrollment'] ?? 0) === 1;
        $facialRequired = $facialPolicy === 'required'
            || ($facialPolicy === 'inherit' && $legacyFacialRequired);
        $legacyComponentRequired = $baseMode === 'supervised_audio_visual' && $avPolicy !== 'disabled';
        $componentRequired = $recordAudioVisual
            || $componentPolicy === 'required'
            || ($componentPolicy === 'inherit' && $legacyComponentRequired);

        return array_merge($activity, [
            'control_mode' => $mode,
            'track_activity_enabled' => $recordActions ? 1 : 0,
            'require_facial_enrollment' => $facialRequired ? 1 : 0,
            'component_validation_required' => $componentRequired ? 1 : 0,
            'record_audio_visual' => $recordAudioVisual ? 1 : 0,
            'record_activity_actions' => $recordActions ? 1 : 0,
        ]);
    }

    public static function requirements(array $activity): array
    {
        if (!array_key_exists('component_validation_required', $activity) || !array_key_exists('require_facial_enrollment', $activity)) {
            $activity = self::resolve($activity);
        }
        return [
            'component_required' => (int) ($activity['component_validation_required'] ?? ((string) ($activity['control_mode'] ?? '') === 'supervised_audio_visual' ? 1 : 0)) === 1,
            'facial_required' => (int) ($activity['require_facial_enrollment'] ?? 0) === 1,
        ];
    }

    public static function isReady(array $activity, bool $componentValidationPassed, bool $facialEnrollmentActive): bool
    {
        $requirements = self::requirements($activity);

        return (!$requirements['component_required'] || $componentValidationPassed)
            && (!$requirements['facial_required'] || $facialEnrollmentActive);
    }

    public static function missingLabels(array $activity, bool $componentValidationPassed, bool $facialEnrollmentActive): array
    {
        $requirements = self::requirements($activity);
        $missing = [];
        if ($requirements['component_required'] && !$componentValidationPassed) $missing[] = 'validar los componentes';
        if ($requirements['facial_required'] && !$facialEnrollmentActive) $missing[] = 'enrolar tu usuario';
        return $missing;
    }

    // La plataforma QA/desarrollo actual ejecuta PHP 7.4; se mantiene sin
    // type-hint para que acepte valores provenientes de formularios y de SQL.
    public static function policy($value): string
    {
        $value = (string) $value;
        return in_array($value, self::POLICIES, true) ? $value : 'inherit';
    }
}
