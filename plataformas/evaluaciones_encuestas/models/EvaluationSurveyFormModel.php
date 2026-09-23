<?php
declare(strict_types=1);

final class EvaluationSurveyFormModel
{
    private Database $db;
    public const FORM_TYPES = ['assessment' => 'Evaluación con nota', 'survey' => 'Encuesta de satisfacción'];
    public const FORM_STATUSES = ['draft' => 'Borrador', 'active' => 'Activo', 'inactive' => 'Inactivo'];
    public const QUESTION_TYPES = ['single_choice' => 'Selección única', 'multiple_choice' => 'Selección múltiple', 'true_false' => 'Verdadero/Falso', 'likert' => 'Escala Likert', 'nps' => 'NPS', 'rating' => 'Calificación por estrellas', 'matrix' => 'Matriz de evaluación', 'text' => 'Texto abierto'];
    private const NON_SCORED = ['text', 'likert', 'nps', 'rating', 'matrix'];
    public const QUESTION_ORDER_MODES = ['ordered' => 'Ordenadas', 'random' => 'Aleatorias'];
    public const RESULT_DISPLAY_MODES = ['best_only' => 'Mostrar solo el mejor resultado', 'collapsible_attempts' => 'Intentos colapsables'];
    public const CONTROL_MODES = [
        'off' => 'Sin registro',
        'activity' => 'Registro de actividad',
        'supervised' => 'Rendición supervisada',
        'supervised_audio_visual' => 'Rendición supervisada + control audiovisual',
    ];
    public const AUDIO_VISUAL_UPLOAD_FAILURE_POLICIES = ['continue' => 'Continuar y registrar incidencia', 'retry_once' => 'Reintentar una vez', 'block' => 'Bloquear la entrega'];
    public const AUDIO_VISUAL_INTERRUPTION_POLICIES = ['continue' => 'Continuar y registrar incidencia', 'pause' => 'Pausar hasta reconectar', 'block' => 'Bloquear la entrega'];
    public const AUDIO_VISUAL_VOICE_POLICIES = ['log' => 'Solo registrar', 'warn' => 'Advertir al usuario', 'pause' => 'Pausar la evaluación'];
    public const AUDIO_VISUAL_PERMISSION_POLICIES = ['continue' => 'Continuar y registrar incidencia', 'pause' => 'Pausar hasta autorizar', 'block' => 'Bloquear la evaluación'];
    public const AUDIO_VISUAL_QUALITY_PROFILES = ['economical' => 'Económico', 'standard' => 'Estándar', 'high' => 'Alta'];

    public function __construct(?Database $db = null) { $this->db = $db ?: database('evaluaciones_encuestas'); }

    public function formsByType(string $type): array
    {
        $type = isset(self::FORM_TYPES[$type]) ? $type : 'assessment';
        $scopeSql = $this->companyScopeSql('f');
        return $this->db->fetchAll('SELECT f.*,
            (SELECT COUNT(*) FROM evaluation_survey_questions q WHERE q.form_id=f.id AND q.is_active=1) questions_count,
            (SELECT COUNT(*) FROM evaluation_survey_attempts a WHERE a.form_id=f.id AND a.status <> "preparing") attempts_count,
            (SELECT COUNT(DISTINCT a.user_id)
             FROM evaluation_survey_attempts a
             JOIN evaluation_survey_answers ans ON ans.attempt_id = a.id
             WHERE a.form_id=f.id AND a.status <> "preparing" AND ans.answer_value IS NOT NULL AND TRIM(ans.answer_value) <> "") respondents_count,
            (SELECT COUNT(*) FROM evaluation_survey_attempts a WHERE a.form_id=f.id AND a.status = "completed") completed_attempts
            FROM evaluation_survey_forms f WHERE f.form_type=?' . $scopeSql . ' ORDER BY f.updated_at DESC,f.sort_order ASC,f.id DESC', array_merge([$type], $this->companyScopeParams()));
    }

    public function findForm(int $id): ?array { return $this->db->fetch('SELECT f.* FROM evaluation_survey_forms f WHERE f.id=?' . $this->companyScopeSql('f') . ' LIMIT 1', array_merge([$id], $this->companyScopeParams())); }

    public function findFormForCompany(int $id, int $companyId): ?array
    {
        if ($id <= 0 || $companyId <= 0) return null;
        return $this->db->fetch(
            'SELECT f.* FROM evaluation_survey_forms f
             WHERE f.id = ? AND (f.company_id IS NULL OR f.company_id = ?) LIMIT 1',
            [$id, $companyId]
        );
    }

    public function createFromMoodle(array $payload, ?int $userId): int
    {
        $questions = array_values((array) ($payload['questions'] ?? []));
        if (!$questions) throw new InvalidArgumentException('La importación no contiene preguntas.');
        $title = trim((string) ($payload['title'] ?? 'Evaluación importada desde Moodle'));
        $maxScore = max(1, (float) ($payload['max_score'] ?? 100));
        $passingScore = ($payload['passing_score'] ?? null) === null || $payload['passing_score'] === '' ? null : max(0, min($maxScore, (float) $payload['passing_score']));
        $displayLimit = array_key_exists('question_display_limit', $payload)
            ? max(0, min(count($questions), (int) $payload['question_display_limit']))
            : min(7, count($questions));
        $companyId = array_key_exists('company_id', $payload)
            ? max(0, (int) $payload['company_id'])
            : $this->currentCompanyId();
        return (int) $this->db->transaction(function (Database $db) use ($payload, $questions, $title, $maxScore, $passingScore, $displayLimit, $companyId, $userId): int {
            $formId = $db->insert('INSERT INTO evaluation_survey_forms (company_id,form_type,title,description,instructions,status,duration_minutes,question_order_mode,question_display_limit,max_attempts,max_score,passing_score,show_result_to_user,result_display_mode,show_correction_to_user,is_required,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $companyId ?: null, 'assessment', mb_substr($title, 0, 180), $payload['description'] ?? null, $payload['instructions'] ?? null, 'draft', 0, 'random', $displayLimit, 1, $maxScore, $passingScore, 1, 'best_only', 0, 1, 100, $userId,
            ]);
            foreach ($questions as $index => $question) {
                $questionText = trim((string) ($question['question_text'] ?? ''));
                if ($questionText === '') continue;
                $type = isset(self::QUESTION_TYPES[(string) ($question['question_type'] ?? '')]) ? (string) $question['question_type'] : 'text';
                $questionId = $db->insert('INSERT INTO evaluation_survey_questions (form_id,question_text,question_type,is_required,points,sort_order,is_active,source_pages,evidence,needs_review) VALUES (?,?,?,?,?,?,?,?,?,?)', [$formId, $questionText, $type, !empty($question['is_required']) ? 1 : 0, max(0, (float) ($question['points'] ?? 1)), ((int) $index + 1) * 10, 1, null, (string) ($question['evidence'] ?? 'Importado desde Moodle.'), !empty($question['needs_review']) || $type === 'text' ? 1 : 0]);
                foreach ((array) ($question['options'] ?? []) as $optionIndex => $option) {
                    if (!is_array($option) || trim((string) ($option['label'] ?? '')) === '') continue;
                    $db->execute('INSERT INTO evaluation_survey_question_options (question_id,option_label,option_value,score_value,sort_order,is_active) VALUES (?,?,?,?,?,1)', [$questionId, trim((string) $option['label']), (string) ($option['value'] ?? $option['label']), $type === 'text' ? null : (float) ($option['score'] ?? 0), ((int) $optionIndex + 1) * 10]);
                }
                foreach ((array) ($question['media'] ?? []) as $mediaIndex => $media) {
                    $source = trim((string) ($media['source_path'] ?? ''));
                    if ($source === '' || !is_file($source)) continue;
                    $hash = hash_file('sha256', $source);
                    $root = BASE_PATH . '/storage/evaluation-question-media';
                    $relativeDir = 'form_' . $formId;
                    if (!is_dir($root . '/' . $relativeDir)) mkdir($root . '/' . $relativeDir, 0770, true);
                    $extension = strtolower(pathinfo((string) ($media['original_name'] ?? ''), PATHINFO_EXTENSION)) ?: 'bin';
                    $relative = $relativeDir . '/question_' . $questionId . '_' . $hash . '.' . $extension;
                    if (!rename($source, $root . '/' . $relative)) continue;
                    $db->execute('INSERT IGNORE INTO evaluation_survey_question_media (question_id,media_type,original_name,storage_key,mime_type,file_size,sha256,sort_order) VALUES (?,?,?,?,?,?,?,?)', [$questionId, (string) ($media['media_type'] ?? 'audio'), mb_substr((string) ($media['original_name'] ?? 'media.' . $extension), 0, 255), $relative, (string) ($media['mime_type'] ?? 'application/octet-stream'), filesize($root . '/' . $relative), $hash, ((int) $mediaIndex + 1) * 10]);
                }
            }
            return (int) $formId;
        });
    }

    public function saveForm(int $formId, array $data, ?int $userId): int
    {
        $type = isset(self::FORM_TYPES[(string) ($data['form_type'] ?? '')]) ? (string) $data['form_type'] : 'assessment';
        $title = trim((string) ($data['title'] ?? '')); if ($title === '') throw new InvalidArgumentException('El título es obligatorio.');
        $status = isset(self::FORM_STATUSES[(string) ($data['status'] ?? '')]) ? (string) $data['status'] : 'draft';
        $maxScore = max(1, (float) ($data['max_score'] ?? 100));
        $controlMode = isset(self::CONTROL_MODES[(string) ($data['control_mode'] ?? '')]) ? (string) $data['control_mode'] : 'off';
        if ($type === 'survey') $controlMode = 'off';
        $uploadFailurePolicy = isset(self::AUDIO_VISUAL_UPLOAD_FAILURE_POLICIES[(string) ($data['audio_visual_upload_failure_policy'] ?? '')]) ? (string) $data['audio_visual_upload_failure_policy'] : 'continue';
        $interruptionPolicy = isset(self::AUDIO_VISUAL_INTERRUPTION_POLICIES[(string) ($data['audio_visual_interruption_policy'] ?? '')]) ? (string) $data['audio_visual_interruption_policy'] : 'pause';
        $voicePolicy = isset(self::AUDIO_VISUAL_VOICE_POLICIES[(string) ($data['audio_visual_voice_policy'] ?? '')]) ? (string) $data['audio_visual_voice_policy'] : 'warn';
        $permissionPolicy = isset(self::AUDIO_VISUAL_PERMISSION_POLICIES[(string) ($data['audio_visual_permission_policy'] ?? '')]) ? (string) $data['audio_visual_permission_policy'] : 'pause';
        $qualityProfile = isset(self::AUDIO_VISUAL_QUALITY_PROFILES[(string) ($data['audio_visual_quality_profile'] ?? '')]) ? (string) $data['audio_visual_quality_profile'] : 'economical';
        $params = [$type, $title, trim((string) ($data['description'] ?? '')) ?: null, trim((string) ($data['instructions'] ?? '')) ?: null, $status, max(0, (int) ($data['duration_minutes'] ?? 0)), $controlMode, $uploadFailurePolicy, $interruptionPolicy, $voicePolicy, $permissionPolicy, $qualityProfile, ($data['question_order_mode'] ?? 'ordered') === 'random' ? 'random' : 'ordered', max(0, (int) ($data['question_display_limit'] ?? 0)), $type === 'assessment' ? max(1, (int) ($data['max_attempts'] ?? 1)) : 1, $maxScore, $type === 'assessment' && ($data['passing_score'] ?? '') !== '' ? max(0, min($maxScore, (float) $data['passing_score'])) : null, isset($data['show_result_to_user']) || $type === 'survey' ? 1 : 0, $type === 'assessment' && ($data['result_display_mode'] ?? 'best_only') === 'collapsible_attempts' ? 'collapsible_attempts' : 'best_only', $type === 'assessment' && isset($data['show_correction_to_user']) ? 1 : 0, isset($data['is_required']) ? 1 : 0, max(0, (int) ($data['sort_order'] ?? 100))];
        $companyId = $this->currentCompanyId();
        if ($formId > 0) {
            $this->db->execute('UPDATE evaluation_survey_forms SET form_type=?,title=?,description=?,instructions=?,status=?,duration_minutes=?,control_mode=?,audio_visual_upload_failure_policy=?,audio_visual_interruption_policy=?,audio_visual_voice_policy=?,audio_visual_permission_policy=?,audio_visual_quality_profile=?,question_order_mode=?,question_display_limit=?,max_attempts=?,max_score=?,passing_score=?,show_result_to_user=?,result_display_mode=?,show_correction_to_user=?,is_required=?,sort_order=? WHERE id=?' . $this->companyScopeSql(), array_merge($params, [$formId], $this->companyScopeParams()));
            return $formId;
        }
        return $this->db->insert('INSERT INTO evaluation_survey_forms (company_id,form_type,title,description,instructions,status,duration_minutes,control_mode,audio_visual_upload_failure_policy,audio_visual_interruption_policy,audio_visual_voice_policy,audio_visual_permission_policy,audio_visual_quality_profile,question_order_mode,question_display_limit,max_attempts,max_score,passing_score,show_result_to_user,result_display_mode,show_correction_to_user,is_required,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge([$companyId ?: null], $params, [$userId]));
    }

    public function deleteForm(int $id): bool { return $this->db->execute('DELETE FROM evaluation_survey_forms WHERE id=?' . $this->companyScopeSql(), array_merge([$id], $this->companyScopeParams())) > 0; }

    public function duplicateForm(int $id, ?int $userId): int
    {
        $form = $this->findForm($id); if (!$form) throw new InvalidArgumentException('Formulario no encontrado.');
        return (int) $this->db->transaction(function (Database $db) use ($form, $id, $userId): int {
            $newId = $db->insert('INSERT INTO evaluation_survey_forms (company_id,form_type,title,description,instructions,status,duration_minutes,control_mode,audio_visual_upload_failure_policy,audio_visual_interruption_policy,audio_visual_voice_policy,audio_visual_permission_policy,audio_visual_quality_profile,question_order_mode,question_display_limit,max_attempts,max_score,passing_score,show_result_to_user,result_display_mode,show_correction_to_user,is_required,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$form['company_id'] ?? null, $form['form_type'], '[DUPLICADO] ' . mb_substr((string) $form['title'], 0, 165), $form['description'], $form['instructions'], 'draft', $form['duration_minutes'], $form['control_mode'] ?? 'off', $form['audio_visual_upload_failure_policy'] ?? 'continue', $form['audio_visual_interruption_policy'] ?? 'pause', $form['audio_visual_voice_policy'] ?? 'warn', $form['audio_visual_permission_policy'] ?? 'pause', $form['audio_visual_quality_profile'] ?? 'economical', $form['question_order_mode'], $form['question_display_limit'], $form['max_attempts'], $form['max_score'], $form['passing_score'], $form['show_result_to_user'], $form['result_display_mode'], $form['show_correction_to_user'], $form['is_required'], $form['sort_order'], $userId]);
            foreach ($this->questionsForForm($id) as $question) { $newQuestion = $db->insert('INSERT INTO evaluation_survey_questions (form_id,question_text,question_type,is_required,points,sort_order,is_active) VALUES (?,?,?,?,?,?,?)', [$newId, $question['question_text'], $question['question_type'], $question['is_required'], $question['points'], $question['sort_order'], $question['is_active']]); foreach (($question['options'] ?? []) as $option) $db->execute('INSERT INTO evaluation_survey_question_options (question_id,option_label,option_value,score_value,sort_order,is_active) VALUES (?,?,?,?,?,?)', [$newQuestion, $option['option_label'], $option['option_value'], $option['score_value'], $option['sort_order'], $option['is_active']]); }
            return $newId;
        });
    }

    public function questionsForForm(int $id, bool $activeOnly = false): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM evaluation_survey_questions WHERE form_id=? ' . ($activeOnly ? 'AND is_active=1 ' : '') . 'ORDER BY sort_order,id', [$id]);
        if (!$rows) {
            return [];
        }
        $questionIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $optionsByQuestion = [];
        foreach ($this->db->fetchAll(
            'SELECT * FROM evaluation_survey_question_options WHERE question_id IN (' . $placeholders . ') AND is_active=1 ORDER BY question_id,sort_order,id',
            $questionIds
        ) as $option) {
            $optionsByQuestion[(int) $option['question_id']][] = $option;
        }
        $mediaByQuestion = [];
        if ($this->db->fetch('SHOW TABLES LIKE "evaluation_survey_question_media"')) foreach ($this->db->fetchAll('SELECT * FROM evaluation_survey_question_media WHERE question_id IN (' . $placeholders . ') ORDER BY question_id,sort_order,id', $questionIds) as $media) $mediaByQuestion[(int) $media['question_id']][] = $media;
        foreach ($rows as &$row) {
            $row['options'] = $optionsByQuestion[(int) $row['id']] ?? [];
            $row['media'] = $mediaByQuestion[(int) $row['id']] ?? [];
            if ((string) ($row['question_type'] ?? '') === 'true_false' && !$row['options']) {
                $row['options'] = $this->defaultTrueFalseOptions((int) $row['id']);
            }
        }
        unset($row);
        return $rows;
    }

    public function findQuestion(int $id, ?int $formId = null): ?array
    {
        $formScope = $this->companyScopeSql('f');
        $formCondition = $formId && $formId > 0 ? ' AND q.form_id = ?' : '';
        $params = [$id];
        if ($formId && $formId > 0) $params[] = $formId;
        $params = array_merge($params, $this->companyScopeParams());
        $question = $this->db->fetch(
            'SELECT q.*, f.form_type, f.title AS form_title
             FROM evaluation_survey_questions q
             JOIN evaluation_survey_forms f ON f.id = q.form_id
             WHERE q.id = ?' . $formCondition . $formScope . ' LIMIT 1',
            $params
        );
        if ($question) {
            $question['options'] = $this->db->fetchAll('SELECT * FROM evaluation_survey_question_options WHERE question_id = ? AND is_active = 1 ORDER BY sort_order, id', [$id]);
            $question['media'] = $this->db->fetchAll('SELECT * FROM evaluation_survey_question_media WHERE question_id = ? ORDER BY sort_order, id', [$id]);
            if ((string) ($question['question_type'] ?? '') === 'true_false' && !$question['options']) {
                $question['options'] = $this->defaultTrueFalseOptions((int) $question['id']);
            }
        }
        return $question;
    }

    public function saveQuestion(int $formId, int $questionId, array $data): int
    {
        $form = $this->findForm($formId); $text = trim((string) ($data['question_text'] ?? '')); $type = (string) ($data['question_type'] ?? '');
        if (!$form || $text === '' || !isset(self::QUESTION_TYPES[$type])) throw new InvalidArgumentException('Formulario, enunciado y tipo de pregunta son obligatorios.');
        $points = in_array($type, self::NON_SCORED, true) || $form['form_type'] === 'survey' ? 0 : max(0, (float) ($data['points'] ?? 1));
        $sourcePages = json_encode(array_values(array_filter(array_map('intval', (array) ($data['source_pages'] ?? [])), static fn(int $page): bool => $page > 0)), JSON_UNESCAPED_UNICODE) ?: null;
        $evidence = mb_substr(trim((string) ($data['evidence'] ?? '')), 0, 500) ?: null;
        $needsReview = array_key_exists('needs_review', $data) ? (!empty($data['needs_review']) ? 1 : 0) : 1;
        $params = [$formId, $text, $type, isset($data['is_required']) ? 1 : 0, $points, max(0, (int) ($data['sort_order'] ?? 100)), isset($data['is_active']) ? 1 : 0, $sourcePages, $evidence, $needsReview];
        if ($questionId > 0) {
            if (!$this->findQuestion($questionId, $formId)) throw new InvalidArgumentException('La pregunta no pertenece al formulario seleccionado.');
            $this->db->execute('UPDATE evaluation_survey_questions SET question_text=?,question_type=?,is_required=?,points=?,sort_order=?,is_active=?,source_pages=?,evidence=?,needs_review=? WHERE id=? AND form_id=?' . $this->companyScopeSqlForForm(), array_merge(array_slice($params, 1), [$questionId, $formId], $this->companyScopeFormParams($formId)));
            $saved = $questionId;
        } else {
            $saved = $this->db->insert('INSERT INTO evaluation_survey_questions (form_id,question_text,question_type,is_required,points,sort_order,is_active,source_pages,evidence,needs_review) VALUES (?,?,?,?,?,?,?,?,?,?)', $params);
        }
        $this->db->execute('DELETE FROM evaluation_survey_question_options WHERE question_id=?', [$saved]);
        $labels = is_array($data['option_label'] ?? null) ? $data['option_label'] : []; $values = is_array($data['option_value'] ?? null) ? $data['option_value'] : []; $single = (string) ($data['option_correct_single'] ?? ''); $multiple = array_map('strval', is_array($data['option_correct'] ?? null) ? $data['option_correct'] : []); $true = (string) ($data['true_false_correct'] ?? 'true');
        if ($type === 'true_false') {
            $labels = [0 => 'Verdadero', 1 => 'Falso'];
            $values = [0 => 'true', 1 => 'false'];
        }
        foreach ($labels as $index => $label) { $label = trim((string) $label); if ($label === '') continue; $value = trim((string) ($values[$index] ?? $label)); $score = 0; if ($form['form_type'] === 'assessment' && !in_array($type, self::NON_SCORED, true)) { if ($type === 'single_choice' && (string) $index === $single) $score = $points; if ($type === 'multiple_choice' && in_array((string) $index, $multiple, true)) $score = $points; if ($type === 'true_false' && strtolower($value) === strtolower($true)) $score = $points; } $this->db->execute('INSERT INTO evaluation_survey_question_options (question_id,option_label,option_value,score_value,sort_order,is_active) VALUES (?,?,?,?,?,1)', [$saved, $label, $value, $score, ((int) $index + 1) * 10]); }
        return $saved;
    }

    private function defaultTrueFalseOptions(int $questionId): array
    {
        return [
            ['question_id' => $questionId, 'option_label' => 'Verdadero', 'option_value' => 'true', 'score_value' => 0, 'sort_order' => 10, 'is_active' => 1],
            ['question_id' => $questionId, 'option_label' => 'Falso', 'option_value' => 'false', 'score_value' => 0, 'sort_order' => 20, 'is_active' => 1],
        ];
    }

    public function deleteQuestion(int $id, ?int $formId = null): bool
    {
        $question = $this->findQuestion($id, $formId);
        if (!$question) return false;
        $formId = (int) $question['form_id'];
        $scopeSql = $this->companyScopeSqlForForm();
        return $this->db->execute(
            'DELETE FROM evaluation_survey_questions WHERE id = ? AND form_id = ?' . $scopeSql,
            array_merge([$id, $formId], $this->companyScopeFormParams($formId))
        ) > 0;
    }
    public function reorderQuestions(int $formId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        if (!$ids) return;
        $cases = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            array_push($params, $id, ($index + 1) * 10);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->execute(
            'UPDATE evaluation_survey_questions SET sort_order = CASE id ' . implode(' ', $cases) . ' ELSE sort_order END WHERE form_id = ? AND id IN (' . $placeholders . ')',
            array_merge($params, [$formId], $ids)
        );
    }

    private function currentCompanyId(): int
    {
        if (is_company_admin_user()) {
            return max(0, (int) (current_user()['company_id'] ?? 0));
        }
        if (!has_permission('manage_company_users') || has_permission('manage_users')) {
            return 0;
        }
        return max(0, (int) (current_user()['company_id'] ?? 0));
    }

    private function companyScopeSqlForForm(): string
    {
        if (is_company_admin_user() && $this->currentCompanyId() <= 0) return ' AND 1 = 0';
        return $this->currentCompanyId() > 0 ? ' AND form_id IN (SELECT f.id FROM evaluation_survey_forms f WHERE f.id = ? AND (f.company_id IS NULL OR f.company_id = ?))' : '';
    }

    private function companyScopeFormParams(int $formId): array
    {
        return $this->currentCompanyId() > 0 ? array_merge([$formId], $this->companyScopeParams()) : [];
    }

    private function companyScopeSql(string $alias = ''): string
    {
        if (is_company_admin_user() && $this->currentCompanyId() <= 0) return ' AND 1 = 0';
        return $this->currentCompanyId() > 0 ? ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'company_id = ?' : '';
    }

    private function companyScopeParams(): array
    {
        return $this->currentCompanyId() > 0 ? [$this->currentCompanyId()] : [];
    }
}
