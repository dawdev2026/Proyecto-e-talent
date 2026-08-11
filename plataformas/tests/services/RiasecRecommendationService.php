<?php
declare(strict_types=1);

final class RiasecRecommendationService
{
    public const INSTRUMENT_CODE = 'riasec';

    private const SCALE_LABELS = [
        'realistic' => ['code' => 'R', 'name' => 'Realista'],
        'investigative' => ['code' => 'I', 'name' => 'Investigativo'],
        'artistic' => ['code' => 'A', 'name' => 'Artistico'],
        'social' => ['code' => 'S', 'name' => 'Social'],
        'enterprising' => ['code' => 'E', 'name' => 'Emprendedor'],
        'conventional' => ['code' => 'C', 'name' => 'Convencional'],
    ];

    private Database $db;
    private ?bool $hasRecommendationTable = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: database('tests');
    }

    public function buildForSession(array $session, array $summary): ?array
    {
        if (($session['instrument_code'] ?? '') !== self::INSTRUMENT_CODE || !$summary) {
            return null;
        }

        $scales = $this->rankedScales($summary);
        if (!$scales) {
            return null;
        }

        $code = implode('', array_map(static fn(array $scale): string => $scale['code'], array_slice($scales, 0, 3)));
        $dominantCode = $scales[0]['code'] ?? '';

        return [
            'code' => $code,
            'dominant_code' => $dominantCode,
            'scales' => $scales,
            'recommendations' => $this->recommendationsForCode($dominantCode, $code),
            'disclaimer' => 'Resultado orientativo de intereses vocacionales. No reemplaza entrevista, criterio profesional ni validacion segun cargo o contexto.',
        ];
    }

    private function rankedScales(array $summary): array
    {
        $rows = [];
        foreach ($summary as $row) {
            $scaleKey = (string) ($row['scale'] ?? '');
            if (!isset(self::SCALE_LABELS[$scaleKey])) {
                continue;
            }

            $score = (float) ($row['raw_score'] ?? $row['score'] ?? 0);
            $answered = (int) ($row['answered'] ?? 0);
            $maxScore = max(7, $answered, (int) ceil($score));
            $meta = self::SCALE_LABELS[$scaleKey];
            $rows[] = [
                'scale' => $scaleKey,
                'code' => $meta['code'],
                'name' => $meta['name'],
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $scoreComparison = $right['score'] <=> $left['score'];
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcmp($left['code'], $right['code']);
        });

        return $rows;
    }

    private function recommendationsForCode(string $dominantCode, string $profileCode): array
    {
        if ($dominantCode === '' || !$this->hasRecommendationTable()) {
            return [];
        }

        return $this->db->fetchAll('
            SELECT category_code, title, description, pathways
            FROM riasec_career_recommendations
            WHERE category_code = ?
               OR profile_code = ?
            ORDER BY CASE WHEN profile_code = ? THEN 0 ELSE 1 END, sort_order ASC, title ASC
            LIMIT 6
        ', [$dominantCode, $profileCode, $profileCode]);
    }

    private function hasRecommendationTable(): bool
    {
        if ($this->hasRecommendationTable !== null) {
            return $this->hasRecommendationTable;
        }

        $row = $this->db->fetch("
            SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'riasec_career_recommendations'
        ");

        $this->hasRecommendationTable = (int) ($row['total'] ?? 0) > 0;
        return $this->hasRecommendationTable;
    }
}
