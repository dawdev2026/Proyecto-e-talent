<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
session_save_path(sys_get_temp_dir());
define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/sistema/bootstrap.php';
$xml = $argv[1] ?? (BASE_PATH . '/xml_test/preguntas-PRUEBAAULAS-26-top-20260921-1039.xml');
$companyId = isset($argv[2]) ? max(0, (int) $argv[2]) : null;
$result = (new MoodleXmlEvaluationImportService())->importDrafts($xml, null, $companyId);
foreach ($result as $quizId => $data) echo 'Moodle quiz ' . $quizId . ': form_id=' . $data['form_id'] . ', preguntas=' . $data['questions'] . ', medios=' . $data['media'] . PHP_EOL;
