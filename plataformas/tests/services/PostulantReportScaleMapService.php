<?php
declare(strict_types=1);

final class PostulantReportScaleMapService
{
    public const INSTRUMENT_CODE = 'ipip_16pf';

    private const FACTORS = [
        'a' => [
            'code' => 'A',
            'source_name' => 'A - Afabilidad',
            'report_name' => 'Afectividad',
            'safe_report_name' => 'Afectividad / trato interpersonal',
            'prompt' => 'Que tan calido o reservado se muestra en el trato con otros.',
            'high_text' => 'Muestra disposicion al contacto interpersonal, cercania y colaboracion.',
            'low_text' => 'Tiende a ser mas reservado y selectivo en la expresion interpersonal.',
        ],
        'b' => [
            'code' => 'B',
            'source_name' => 'B - Razonamiento',
            'report_name' => 'Razonamiento',
            'safe_report_name' => 'Razonamiento',
            'prompt' => 'Como aborda problemas y procesa informacion.',
            'high_text' => 'Presenta facilidad para comprender informacion y resolver problemas.',
            'low_text' => 'Puede requerir instrucciones mas concretas o apoyo adicional ante tareas complejas.',
        ],
        'c' => [
            'code' => 'C',
            'source_name' => 'C - Estabilidad emocional',
            'report_name' => 'Estabilidad',
            'safe_report_name' => 'Estabilidad emocional',
            'prompt' => 'Que tan estable se mantiene ante presion o contratiempos.',
            'high_text' => 'Tiende a conservar la calma y regular sus respuestas ante exigencias.',
            'low_text' => 'Puede mostrar mayor reactividad emocional ante presion o cambios relevantes.',
        ],
        'e' => [
            'code' => 'E',
            'source_name' => 'E - Dominancia',
            'report_name' => 'Dominancia',
            'safe_report_name' => 'Dominancia / asertividad',
            'prompt' => 'Como expresa iniciativa, control o influencia en el grupo.',
            'high_text' => 'Expresa sus posturas con seguridad y puede asumir liderazgo en el grupo.',
            'low_text' => 'Tiende a adoptar una posicion mas cooperativa, prudente o de baja confrontacion.',
        ],
        'f' => [
            'code' => 'F',
            'source_name' => 'F - Animacion',
            'report_name' => 'Animacion',
            'safe_report_name' => 'Animacion / energia expresiva',
            'prompt' => 'Nivel de energia social, espontaneidad y expresividad.',
            'high_text' => 'Muestra energia, expresividad y disposicion a interactuar activamente.',
            'low_text' => 'Tiende a mostrarse mas serio, cauto o contenido en su expresion.',
            'note' => 'No usar el nombre Impulsividad para esta escala en informes, para evitar confusion con TICL/Barratt.',
        ],
        'g' => [
            'code' => 'G',
            'source_name' => 'G - Atencion a normas',
            'report_name' => 'Conformidad grupal',
            'safe_report_name' => 'Atencion a normas / responsabilidad',
            'prompt' => 'Grado de adhesion a reglas, deberes y estructura.',
            'high_text' => 'Muestra orientacion a normas, responsabilidad y cumplimiento de procedimientos.',
            'low_text' => 'Puede preferir mayor autonomia y flexibilidad frente a reglas o procedimientos.',
        ],
        'h' => [
            'code' => 'H',
            'source_name' => 'H - Atrevimiento',
            'report_name' => 'Atrevimiento',
            'safe_report_name' => 'Atrevimiento social',
            'prompt' => 'Seguridad para exponerse a situaciones nuevas o sociales.',
            'high_text' => 'Se muestra seguro ante situaciones nuevas y con disposicion a participar.',
            'low_text' => 'Puede actuar con mayor reserva o cautela ante contextos nuevos o exigentes.',
        ],
        'i' => [
            'code' => 'I',
            'source_name' => 'I - Sensibilidad',
            'report_name' => 'Sensibilidad',
            'safe_report_name' => 'Sensibilidad',
            'prompt' => 'Nivel de sensibilidad emocional y consideracion interpersonal.',
            'high_text' => 'Presenta sensibilidad ante el entorno y consideracion por los demas.',
            'low_text' => 'Tiende a privilegiar criterios practicos y directos en sus decisiones.',
        ],
        'l' => [
            'code' => 'L',
            'source_name' => 'L - Vigilancia',
            'report_name' => 'Suspicacia o Vigilancia',
            'safe_report_name' => 'Vigilancia interpersonal',
            'prompt' => 'Grado de cautela o confianza frente a otras personas.',
            'high_text' => 'Puede mostrarse alerta, cauteloso y atento a posibles riesgos interpersonales.',
            'low_text' => 'Tiende a confiar con mayor facilidad y a interpretar positivamente a otros.',
        ],
        'm' => [
            'code' => 'M',
            'source_name' => 'M - Abstraccion',
            'report_name' => 'Imaginacion',
            'safe_report_name' => 'Abstraccion / imaginacion',
            'prompt' => 'Orientacion hacia ideas, posibilidades o hechos concretos.',
            'high_text' => 'Muestra tendencia a pensar en posibilidades, ideas y escenarios alternativos.',
            'low_text' => 'Tiende a enfocarse en hechos concretos y necesidades practicas inmediatas.',
        ],
        'n' => [
            'code' => 'N',
            'source_name' => 'N - Privacidad',
            'report_name' => 'Privacidad',
            'safe_report_name' => 'Privacidad / reserva',
            'prompt' => 'Nivel de reserva en la expresion de informacion personal.',
            'high_text' => 'Tiende a mantener reserva y controlar cuanto comparte de si mismo.',
            'low_text' => 'Suele mostrarse mas abierto, directo y transparente en la comunicacion.',
        ],
        'o' => [
            'code' => 'O',
            'source_name' => 'O - Aprension',
            'report_name' => 'Aprension o Inseguridad',
            'safe_report_name' => 'Aprension / preocupacion',
            'prompt' => 'Tendencia a preocuparse, anticipar errores o dudar de si mismo.',
            'high_text' => 'Puede presentar mayor preocupacion, autocritica o sensibilidad a errores.',
            'low_text' => 'Tiende a mostrarse seguro, tranquilo y con menor preocupacion anticipatoria.',
        ],
        'q1' => [
            'code' => 'Q1',
            'source_name' => 'Q1 - Apertura al cambio',
            'report_name' => 'Apertura al cambio',
            'safe_report_name' => 'Apertura al cambio',
            'prompt' => 'Disposicion a cambios, nuevas ideas y formas distintas de actuar.',
            'high_text' => 'Muestra flexibilidad, apertura a nuevas ideas y adaptacion al cambio.',
            'low_text' => 'Prefiere rutinas, criterios conocidos y formas de trabajo estables.',
        ],
        'q2' => [
            'code' => 'Q2',
            'source_name' => 'Q2 - Autosuficiencia',
            'report_name' => 'Autosuficiencia',
            'safe_report_name' => 'Autosuficiencia',
            'prompt' => 'Preferencia por trabajar de manera autonoma o apoyarse en el grupo.',
            'high_text' => 'Tiende a actuar con independencia y a confiar en su propio criterio.',
            'low_text' => 'Prefiere apoyarse en el grupo y considerar opiniones antes de decidir.',
        ],
        'q3' => [
            'code' => 'Q3',
            'source_name' => 'Q3 - Perfeccionismo',
            'report_name' => 'Perfeccionismo',
            'safe_report_name' => 'Perfeccionismo / orden',
            'prompt' => 'Nivel de organizacion, control y cuidado por los detalles.',
            'high_text' => 'Muestra organizacion, cuidado por los detalles y orientacion al control.',
            'low_text' => 'Puede actuar con mayor flexibilidad y menor foco en detalles o estructura.',
        ],
        'q4' => [
            'code' => 'Q4',
            'source_name' => 'Q4 - Tension',
            'report_name' => 'Tension',
            'safe_report_name' => 'Tension',
            'prompt' => 'Nivel de tension, inquietud o impaciencia.',
            'high_text' => 'Puede mostrar tension, impaciencia o mayor activacion ante exigencias.',
            'low_text' => 'Tiende a mantenerse relajado y con menor activacion frente a presiones.',
        ],
    ];

    public function factors(): array
    {
        return self::FACTORS;
    }

    public function factorByScaleKey(string $scaleKey): ?array
    {
        $key = $this->normalizeScaleKey($scaleKey);
        return self::FACTORS[$key] ?? null;
    }

    public function factorByCode(string $code): ?array
    {
        return $this->factorByScaleKey($code);
    }

    public function mappedSummary(array $summary): array
    {
        $rows = [];
        foreach ($summary as $row) {
            $factor = $this->factorFromSummaryRow($row);
            if ($factor === null) {
                continue;
            }

            $score = $this->numericSummaryValue($row);
            $rows[] = $factor + [
                'score' => $score,
                'level' => $score !== null ? $this->levelForScore($score) : 'sin_dato',
            ];
        }

        return $rows;
    }

    public function levelForScore(float $score): string
    {
        if ($score >= 7.0) {
            return 'favorable';
        }

        if ($score >= 5.0) {
            return 'observacion';
        }

        return 'bajo';
    }

    private function factorFromSummaryRow(array $row): ?array
    {
        foreach (['scale', 'scale_key', 'code', 'name'] as $field) {
            $value = (string) ($row[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $factor = $this->factorByScaleKey($value);
            if ($factor !== null) {
                return $factor;
            }
        }

        return null;
    }

    private function normalizeScaleKey(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^(q[1-4]|[a-z])\\b/', $value, $matches)) {
            return $matches[1];
        }

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    private function numericSummaryValue(array $row): ?float
    {
        foreach (['transformed_score', 'adjusted_score', 'raw_score', 'score'] as $field) {
            if (isset($row[$field]) && is_numeric($row[$field])) {
                return (float) $row[$field];
            }
        }

        return null;
    }
}
