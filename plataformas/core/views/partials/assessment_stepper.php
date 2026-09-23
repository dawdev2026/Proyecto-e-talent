<?php
$assessmentFlowSteps = is_array($assessmentFlowSteps ?? null) ? $assessmentFlowSteps : [
    ['id' => 'identity', 'label' => 'Reconocimiento facial'],
    ['id' => 'components', 'label' => 'Validación de componentes'],
    ['id' => 'instructions', 'label' => 'Instrucciones y registro'],
    ['id' => 'assessment', 'label' => (string) ($assessmentFinalLabel ?? 'Evaluación o test')],
];
$assessmentStep = (int) ($assessmentStep ?? 1);
$assessmentCurrentStage = (string) ($assessmentCurrentStage ?? ($assessmentFlowSteps[0]['id'] ?? 'assessment'));
?>
<nav class="assessment-stepper" data-assessment-stepper data-current-step="<?= max(1, $assessmentStep) ?>" aria-label="Pasos para realizar la evaluación">
    <ol>
        <?php foreach ($assessmentFlowSteps as $index => $flowStep): ?>
            <?php $step = $index + 1; $id = (string) ($flowStep['id'] ?? ''); $label = (string) ($flowStep['label'] ?? 'Paso'); $isCurrent = $id === $assessmentCurrentStage; $isComplete = $step < $assessmentStep; ?>
            <li data-assessment-step="<?= e($id) ?>" class="<?= $isComplete ? 'is-complete' : ($isCurrent ? 'is-current' : '') ?>" <?= $isCurrent ? 'aria-current="step"' : '' ?>>
                <span class="assessment-stepper-marker" aria-hidden="true"><?= $isComplete ? '<i class="bi bi-check-lg"></i>' : (string) $step ?></span>
                <span class="assessment-stepper-label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
