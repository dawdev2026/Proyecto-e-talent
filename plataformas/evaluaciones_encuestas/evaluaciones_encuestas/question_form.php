<?php
$options = array_values($question['options'] ?? []);
$trueFalseCorrect = '';
foreach ($options as $option) {
    $value = (string) ($option['option_value'] ?? '');
    if (in_array($value, ['true', 'false'], true) && (float) ($option['score_value'] ?? 0) > 0) {
        $trueFalseCorrect = $value;
        break;
    }
}
$choiceOptions = array_values(array_filter($options, static fn(array $option): bool => !in_array((string) ($option['option_value'] ?? ''), ['true', 'false'], true)));
if (!$choiceOptions) {
    $choiceOptions = [
        ['option_label' => '', 'score_value' => ''],
        ['option_label' => '', 'score_value' => ''],
    ];
}
$isSurvey = (string) ($form['form_type'] ?? '') === 'survey';
$selectedType = (string) ($question['question_type'] ?? '');
$allowedQuestionTypeKeys = $isSurvey
    ? ['single_choice', 'multiple_choice', 'likert', 'nps', 'rating', 'matrix', 'text']
    : ['single_choice', 'multiple_choice', 'true_false', 'likert', 'nps', 'rating', 'matrix', 'text'];
$availableQuestionTypes = array_intersect_key($questionTypes, array_flip($allowedQuestionTypeKeys));
if (!isset($availableQuestionTypes[$selectedType])) {
    $selectedType = $questionId > 0 ? 'single_choice' : '';
}
$nonScoredQuestionTypes = ['text', 'likert', 'nps', 'rating', 'matrix'];
$showsChoiceOptions = in_array($selectedType, ['single_choice', 'multiple_choice', 'matrix'], true);
$showsTrueFalse = !$isSurvey && $selectedType === 'true_false';
$showsPoints = !$isSurvey && $selectedType !== '' && !in_array($selectedType, $nonScoredQuestionTypes, true);
?>
<?php $isDrawer = !empty($isDrawer); ?>
<?php
$questionAction = $questionId > 0
    ? route_url('evaluation-surveys.question.edit', $questionId)
    : route_url('evaluation-surveys.question.new', (int) $form['id']);
if ($isDrawer) {
    $questionAction .= '?drawer=1';
}
?>
<?php if (!$isDrawer): ?>
<section class="page-header" data-page-back-url="<?= e(route_url('evaluation-surveys.form.edit', (int) $form['id'])) ?>">
    <div>
        <p class="dashboard-kicker mb-2">Preguntas</p>
        <h1 class="fw-bold mb-1"><?= $questionId > 0 ? 'Editar pregunta' : 'Nueva pregunta' ?></h1>
        <p class="text-muted mb-0"><?= e($form['title']) ?></p>
    </div>
</section>
<?php endif; ?>

<section class="<?= $isDrawer ? 'drawer-form' : 'content-panel' ?>">
    <form method="post" action="<?= e($questionAction) ?>" class="row g-4 app-form-stack needs-validation" novalidate data-evaluation-question-form data-form-type="<?= e((string) ($form['form_type'] ?? 'assessment')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="col-12">
            <label class="form-label" for="question_text">Enunciado</label>
            <textarea id="question_text" class="form-control" name="question_text" rows="6" data-evaluation-richtext-editor required><?= e($question['question_text'] ?? '') ?></textarea>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="question_type">Tipo</label>
            <select id="question_type" class="form-select" name="question_type" data-question-type-select required>
                <option value="" <?= $selectedType === '' ? 'selected' : '' ?>>Seleccionar tipo de pregunta</option>
                <?php foreach ($availableQuestionTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $selectedType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text" data-question-type-help></div>
        </div>
        <div class="col-12 col-md-4<?= $showsPoints ? '' : ' d-none' ?>" data-question-points-field>
            <label class="form-label" for="points">Puntaje de la pregunta</label>
            <input id="points" class="form-control" type="number" min="0" step="0.01" name="points" value="<?= e((string) ($question['points'] ?? 1)) ?>" <?= $showsPoints ? '' : 'disabled' ?>>
            <div class="form-text" data-question-points-help></div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-4">
            <div class="form-check form-switch">
                <input id="is_required" class="form-check-input" type="checkbox" name="is_required" <?= (int) ($question['is_required'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_required">Obligatoria</label>
            </div>
            <div class="form-check form-switch">
                <input id="is_active" class="form-check-input" type="checkbox" name="is_active" <?= (int) ($question['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Activa</label>
            </div>
        </div>
        <div class="col-12<?= $selectedType === '' ? ' d-none' : '' ?>" data-question-rule-section>
            <div class="border rounded-3 p-3">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-1" data-question-rule-title>Regla de correccion</h2>
                        <p class="text-muted small mb-0" data-question-rule-help></p>
                    </div>
                    <span class="badge text-bg-light border" data-question-rule-badge>Automático</span>
                </div>
            </div>
        </div>
        <div class="col-12<?= $showsChoiceOptions ? '' : ' d-none' ?>" data-question-options-section>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h6 fw-bold mb-0" data-question-options-title>Alternativas</h2>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-add-question-option>
                    <i class="bi bi-plus-lg me-1"></i> Agregar alternativa
                </button>
            </div>
            <p class="text-muted small mb-3" data-question-options-help></p>
            <div class="vstack gap-2" data-question-options-list>
                <?php foreach ($choiceOptions as $index => $option): ?>
                    <div class="row g-2 align-items-center" data-question-option-row>
                        <div class="col-12 col-md">
                            <input class="form-control" name="option_label[]" value="<?= e($option['option_label'] ?? '') ?>" placeholder="Alternativa <?= (int) ($index + 1) ?>" data-question-option-label>
                        </div>
                        <div class="col-8 col-md-3">
                            <div class="form-check mb-0<?= !$isSurvey && $selectedType === 'single_choice' ? '' : ' d-none' ?>" data-single-correct-control>
                                <input class="form-check-input" type="radio" name="option_correct_single" value="<?= (int) $index ?>" <?= (float) ($option['score_value'] ?? 0) > 0 ? 'checked' : '' ?> data-question-correct-control>
                                <label class="form-check-label">Correcta</label>
                            </div>
                            <div class="form-check mb-0<?= !$isSurvey && $selectedType === 'multiple_choice' ? '' : ' d-none' ?>" data-multiple-correct-control>
                                <input class="form-check-input" type="checkbox" name="option_correct[]" value="<?= (int) $index ?>" <?= (float) ($option['score_value'] ?? 0) > 0 ? 'checked' : '' ?> data-question-correct-control>
                                <label class="form-check-label">Correcta</label>
                            </div>
                        </div>
                        <div class="col-4 col-md-auto text-end">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-remove-question-option aria-label="Eliminar alternativa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text mt-2" data-question-options-footnote></div>
        </div>
        <div class="col-12<?= $showsTrueFalse ? '' : ' d-none' ?>" data-question-true-false-section>
            <div class="border rounded-3 p-3">
                <h2 class="h6 fw-bold mb-2">Respuesta correcta</h2>
                <p class="text-muted small mb-3">Selecciona cual de las dos opciones sera considerada correcta para el calculo automático.</p>
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-check border rounded-2 p-3 h-100">
                            <input class="form-check-input" type="radio" name="true_false_correct" value="true" <?= $trueFalseCorrect === 'true' ? 'checked' : '' ?> <?= $showsTrueFalse ? '' : 'disabled' ?> data-question-true-false-input>
                            <span class="form-check-label">Verdadero</span>
                        </label>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-check border rounded-2 p-3 h-100">
                            <input class="form-check-input" type="radio" name="true_false_correct" value="false" <?= $trueFalseCorrect === 'false' ? 'checked' : '' ?> <?= $showsTrueFalse ? '' : 'disabled' ?> data-question-true-false-input>
                            <span class="form-check-label">Falso</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary px-4" type="submit"><i class="bi bi-check2 me-1"></i> Guardar pregunta</button>
            <?php if ($isDrawer): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-app-drawer-close>Cancelar</button>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('evaluation-surveys.form.edit', (int) $form['id'])) ?>">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<script>
function initEvaluationSurveyQuestionForms(scope) {
    (scope || document).querySelectorAll('[data-evaluation-question-form]').forEach(function (form) {
        if (form.dataset.questionFormReady === '1') {
            return;
        }
        form.dataset.questionFormReady = '1';
        var typeSelect = form.querySelector('[data-question-type-select]');
        var typeHelp = form.querySelector('[data-question-type-help]');
        var pointsHelp = form.querySelector('[data-question-points-help]');
        var pointsField = form.querySelector('[data-question-points-field]');
        var ruleSection = form.querySelector('[data-question-rule-section]');
        var optionsSection = form.querySelector('[data-question-options-section]');
        var optionsTitle = form.querySelector('[data-question-options-title]');
        var optionsHelp = form.querySelector('[data-question-options-help]');
        var ruleTitle = form.querySelector('[data-question-rule-title]');
        var ruleHelp = form.querySelector('[data-question-rule-help]');
        var ruleBadge = form.querySelector('[data-question-rule-badge]');
        var optionsFootnote = form.querySelector('[data-question-options-footnote]');
        var optionsList = form.querySelector('[data-question-options-list]');
        var addOptionButton = form.querySelector('[data-add-question-option]');
        var trueFalseSection = form.querySelector('[data-question-true-false-section]');
        var trueFalseInputs = form.querySelectorAll('[data-question-true-false-input]');
        var isSurvey = form.getAttribute('data-form-type') === 'survey';

        var config = {
            single_choice: {
                typeHelp: 'El usuario podrá marcar solo una alternativa.',
                pointsHelp: 'Puntaje completo si el usuario marca la alternativa correcta.',
                showOptions: true,
                optionsTitle: 'Alternativas de seleccion unica',
                optionsHelp: 'Agrega las alternativas y marca solo una como correcta.',
                optionsFootnote: 'Regla: correcta = puntaje completo de la pregunta; cualquier otra = 0.',
                ruleTitle: 'Correccion automática por respuesta unica',
                ruleHelp: 'El sistema compara la alternativa seleccionada contra la alternativa marcada como correcta.',
                ruleBadge: '1 correcta'
            },
            multiple_choice: {
                typeHelp: 'El usuario podrá marcar una o varias alternativas.',
                pointsHelp: 'Puntaje completo solo si marca todas las correctas y ninguna incorrecta.',
                showOptions: true,
                optionsTitle: 'Alternativas de seleccion multiple',
                optionsHelp: 'Agrega alternativas y marca una o más como correctas.',
                optionsFootnote: 'Regla inicial: respuesta exacta. Si falta una correcta o marca una incorrecta, obtiene 0.',
                ruleTitle: 'Correccion automática por conjunto exacto',
                ruleHelp: 'El sistema exige que el usuario seleccione exactamente el mismo conjunto de alternativas correctas.',
                ruleBadge: '1 o más correctas'
            },
            true_false: {
                typeHelp: 'El usuario elegira Verdadero o Falso.',
                pointsHelp: 'Puntaje completo si coincide con la opcion correcta.',
                showOptions: false,
                showTrueFalse: true,
                ruleTitle: 'Correccion automática verdadero/falso',
                ruleHelp: 'El sistema compara la opcion marcada por el usuario contra la respuesta definida como correcta.',
                ruleBadge: '1 correcta'
            },
            likert: {
                typeHelp: 'Escala estandar de 1 a 5 para medir grado de acuerdo.',
                pointsHelp: 'Este tipo se usa para encuestas y no se corrige automaticamente.',
                showOptions: false,
                ruleTitle: 'Escala de satisfacción',
                ruleHelp: 'El sistema generara las opciones: muy en desacuerdo, en desacuerdo, neutral, de acuerdo y muy de acuerdo.',
                ruleBadge: 'Encuesta'
            },
            nps: {
                typeHelp: 'Escala de recomendacion de 0 a 10.',
                pointsHelp: 'Este tipo se usa para encuestas y no se corrige automaticamente.',
                showOptions: false,
                ruleTitle: 'Net Promoter Score',
                ruleHelp: 'El sistema generara la escala de 0 a 10 para medir recomendacion.',
                ruleBadge: 'Encuesta'
            },
            rating: {
                typeHelp: 'Calificacion visual de 1 a 5 estrellas.',
                pointsHelp: 'Este tipo se usa para encuestas y no se corrige automaticamente.',
                showOptions: false,
                ruleTitle: 'Calificacion por estrellas',
                ruleHelp: 'El sistema generara cinco opciones de calificacion para experiencia general.',
                ruleBadge: 'Encuesta'
            },
            matrix: {
                typeHelp: 'Permite evaluar varios criterios con la misma escala de 1 a 5.',
                pointsHelp: 'Este tipo se usa para encuestas y no se corrige automaticamente.',
                showOptions: true,
                optionsTitle: 'Criterios de la matriz',
                optionsHelp: 'Agrega cada aspecto que el usuario deberá evaluar, por ejemplo contenido, relator, materiales o duración.',
                optionsFootnote: 'Cada criterio se respondera con una escala de 1 a 5.',
                ruleTitle: 'Matriz de satisfacción',
                ruleHelp: 'El sistema guarda una respuesta por cada criterio usando la misma escala.',
                ruleBadge: 'Encuesta'
            },
            text: {
                typeHelp: 'El usuario escribira una respuesta abierta.',
                pointsHelp: 'Este tipo no se corrige automaticamente.',
                showOptions: false,
                ruleTitle: 'Respuesta abierta sin nota automática',
                ruleHelp: 'El sistema guarda el texto para revision o analisis, sin sumar puntaje automático.',
                ruleBadge: 'Sin nota'
            }
        };

        function optionRows() {
            return Array.prototype.slice.call(optionsList.querySelectorAll('[data-question-option-row]'));
        }

        function syncOptionIndexes() {
            optionRows().forEach(function (row, index) {
                var label = row.querySelector('[data-question-option-label]');
                var single = row.querySelector('[data-single-correct-control] input');
                var multiple = row.querySelector('[data-multiple-correct-control] input');
                if (label) {
                    label.placeholder = 'Alternativa ' + (index + 1);
                }
                if (single) {
                    single.value = String(index);
                }
                if (multiple) {
                    multiple.value = String(index);
                }
            });
        }

        function addOption() {
            var row = document.createElement('div');
            row.className = 'row g-2 align-items-center';
            row.setAttribute('data-question-option-row', '');
            row.innerHTML = '<div class="col-12 col-md">'
                + '<input class="form-control" name="option_label[]" placeholder="Alternativa" data-question-option-label>'
                + '</div>'
                + '<div class="col-8 col-md-3">'
                + '<div class="form-check mb-0" data-single-correct-control>'
                + '<input class="form-check-input" type="radio" name="option_correct_single" value="" data-question-correct-control>'
                + '<label class="form-check-label">Correcta</label>'
                + '</div>'
                + '<div class="form-check mb-0 d-none" data-multiple-correct-control>'
                + '<input class="form-check-input" type="checkbox" name="option_correct[]" value="" data-question-correct-control>'
                + '<label class="form-check-label">Correcta</label>'
                + '</div>'
                + '</div>'
                + '<div class="col-4 col-md-auto text-end">'
                + '<button class="btn btn-sm btn-outline-secondary" type="button" data-remove-question-option aria-label="Eliminar alternativa">'
                + '<i class="bi bi-trash"></i>'
                + '</button>'
                + '</div>';
            optionsList.appendChild(row);
            syncOptionIndexes();
            applyTypeState();
        }

        function applyTypeState() {
            var current = config[typeSelect.value] || null;
            if (!current) {
                typeHelp.textContent = 'Selecciona un tipo para configurar la correccion y las alternativas.';
                pointsHelp.textContent = '';
                ruleSection.classList.add('d-none');
                optionsSection.classList.add('d-none');
                trueFalseSection.classList.add('d-none');
                pointsField.classList.add('d-none');
                pointsField.querySelectorAll('input').forEach(function (input) {
                    input.disabled = true;
                });
                optionsList.querySelectorAll('input').forEach(function (input) {
                    input.disabled = true;
                });
                trueFalseInputs.forEach(function (input) {
                    input.disabled = true;
                });
                return;
            }
            typeHelp.textContent = current.typeHelp || '';
            pointsHelp.textContent = current.pointsHelp || '';
            var nonScored = isSurvey || ['text', 'likert', 'nps', 'rating', 'matrix'].indexOf(typeSelect.value) !== -1;
            pointsField.classList.toggle('d-none', nonScored);
            pointsField.querySelectorAll('input').forEach(function (input) {
                input.disabled = nonScored;
            });
            ruleSection.classList.remove('d-none');
            var surveyChoice = isSurvey && ['single_choice', 'multiple_choice'].indexOf(typeSelect.value) !== -1;
            ruleTitle.textContent = surveyChoice ? 'Pregunta cerrada sin correccion' : (current.ruleTitle || 'Regla de correccion');
            ruleHelp.textContent = surveyChoice
                ? 'El sistema guarda la respuesta seleccionada para analisis de satisfacción, sin puntaje ni alternativa correcta.'
                : (current.ruleHelp || '');
            ruleBadge.textContent = isSurvey ? 'Encuesta' : (current.ruleBadge || 'Automático');

            optionsSection.classList.toggle('d-none', !current.showOptions);
            trueFalseSection.classList.toggle('d-none', isSurvey || !current.showTrueFalse);
            optionsList.querySelectorAll('input').forEach(function (input) {
                input.disabled = !current.showOptions;
            });
            trueFalseInputs.forEach(function (input) {
                input.disabled = isSurvey || !current.showTrueFalse;
            });

            if (current.showOptions) {
                optionsTitle.textContent = current.optionsTitle || 'Alternativas';
                optionsHelp.textContent = surveyChoice ? 'Agrega las alternativas que el usuario podrá seleccionar.' : (current.optionsHelp || '');
                optionsFootnote.textContent = surveyChoice ? 'Las respuestas se guardan sin puntaje y sin marcar alternativas correctas.' : (current.optionsFootnote || '');
                optionRows().forEach(function (row) {
                    row.querySelector('[data-single-correct-control]').classList.toggle('d-none', isSurvey || typeSelect.value !== 'single_choice');
                    row.querySelector('[data-multiple-correct-control]').classList.toggle('d-none', isSurvey || typeSelect.value !== 'multiple_choice');
                    row.querySelectorAll('[data-question-correct-control]').forEach(function (input) {
                        input.disabled = isSurvey;
                    });
                });
                return;
            }
        }

        addOptionButton.addEventListener('click', addOption);
        optionsList.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-question-option]');
            if (!button) {
                return;
            }
            if (optionRows().length <= 2) {
                return;
            }
            button.closest('[data-question-option-row]').remove();
            syncOptionIndexes();
        });
        typeSelect.addEventListener('change', applyTypeState);
        syncOptionIndexes();
        applyTypeState();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initEvaluationSurveyQuestionForms(document);
    });
} else {
    initEvaluationSurveyQuestionForms(document);
}
</script>
