<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/sistema/bootstrap.php';

$input = $argv[1] ?? null;
if (!$input || !is_file($input)) throw new InvalidArgumentException('Uso: php import-data.php export.json [mapping.json]');
$payload = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
if (!in_array((string) ($payload['format'] ?? ''), ['evaluaciones-encuestas/v1', 'evaluaciones-encuestas/e_talent-v1'], true)) throw new RuntimeException('Formato de exportación no compatible.');
$mapping = is_file($argv[2] ?? '') ? json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR) : [];
$db = database('evaluaciones_encuestas');
$tables = ['evaluation_survey_forms', 'evaluation_survey_questions', 'evaluation_survey_question_options', 'evaluation_survey_attempts', 'evaluation_survey_answers', 'evaluation_survey_settings'];
$idMaps = array_fill_keys($tables, []);
$map = static function (array $maps, string $type, $value) { $key = (string) $value; return $maps[$type][$key] ?? $value; };

$db->transaction(function (Database $transaction) use ($payload, $tables, &$idMaps, $mapping, $map): void {
    foreach ($tables as $table) {
        foreach (($payload['tables'][$table] ?? []) as $row) {
            $oldId = $row['id'] ?? $row['setting_key'] ?? null;
            if ($table === 'evaluation_survey_forms') $row['created_by'] = $map($mapping, 'users', $row['created_by'] ?? null);
            if ($table === 'evaluation_survey_attempts') $row['user_id'] = $map($mapping, 'users', $row['user_id']);
            if ($table === 'evaluation_survey_questions') $row['form_id'] = $map($idMaps, 'evaluation_survey_forms', $row['form_id']);
            if ($table === 'evaluation_survey_question_options') $row['question_id'] = $map($idMaps, 'evaluation_survey_questions', $row['question_id']);
            if ($table === 'evaluation_survey_answers') { $row['attempt_id'] = $map($idMaps, 'evaluation_survey_attempts', $row['attempt_id']); $row['question_id'] = $map($idMaps, 'evaluation_survey_questions', $row['question_id']); }
            if ($table === 'evaluation_survey_settings') { $transaction->execute('INSERT INTO evaluation_survey_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$row['setting_key'], $row['setting_value']]); continue; }
            unset($row['id']);
            $columns = array_keys($row); $quoted = implode(',', array_map(static fn($column) => '`' . $column . '`', $columns)); $marks = implode(',', array_fill(0, count($columns), '?'));
            $newId = $transaction->insert("INSERT INTO `{$table}` ({$quoted}) VALUES ({$marks})", array_values($row));
            if ($oldId !== null) $idMaps[$table][(string) $oldId] = $newId;
        }
    }
});
echo "Importación completada.\n";
