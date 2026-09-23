<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/sistema/bootstrap.php';

$output = $argv[1] ?? (BASE_PATH . '/tmp/evaluaciones_encuestas_export.json');
$tables = ['evaluation_survey_forms', 'evaluation_survey_questions', 'evaluation_survey_question_options', 'evaluation_survey_question_media', 'evaluation_survey_attempts', 'evaluation_survey_answers', 'evaluation_survey_settings'];
$db = database('evaluaciones_encuestas');
$payload = ['format' => 'evaluaciones-encuestas/e_talent-v1', 'exported_at' => date(DATE_ATOM), 'tables' => []];
foreach ($tables as $table) $payload['tables'][$table] = $db->fetchAll("SELECT * FROM `{$table}`");
if (file_put_contents($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) throw new RuntimeException('No se pudo escribir el export.');
echo "Exportados datos a {$output}\n";
