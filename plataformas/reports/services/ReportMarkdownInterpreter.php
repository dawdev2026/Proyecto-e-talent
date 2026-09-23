<?php
declare(strict_types=1);

/**
 * Interpreta la especificación Markdown de un informe sin ejecutar código
 * arbitrario. El documento funciona como un contrato declarativo: describe
 * metadatos, fuentes permitidas, transformaciones registradas y contenido.
 */
final class ReportMarkdownInterpreter
{
    private const ALLOWED_SOURCES = [
        'source.process_user' => [
            'provider' => 'TestProcessModel',
            'method' => 'processUser',
        ],
        'source.process' => [
            'provider' => 'TestProcessModel',
            'method' => 'find',
        ],
        'source.sessions' => [
            'provider' => 'TestProcessModel',
            'method' => 'processDashboardSessionsForUser',
        ],
        'source.ranking' => [
            'provider' => 'ProgressRankingSummaryService',
            'method' => 'build',
        ],
        'source.instrument_summaries' => [
            'provider' => 'TestSessionModel',
            'method' => 'summaryForSession',
        ],
    ];

    private const ALLOWED_FORMULAS = [
        'average',
        'inverse',
        'sten_to_percentage',
        'normalize_ticl',
        'clamp',
    ];

    public function parse(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            throw new InvalidArgumentException('La especificación Markdown está vacía.');
        }

        [$metadata, $body] = $this->extractFrontMatter($markdown);
        $templateKey = trim((string) ($metadata['template_key'] ?? ''));
        $metadata['template_key'] = $templateKey !== ''
            ? $templateKey
            : ReportTemplateCatalog::DEFAULT_TEMPLATE;
        $blocks = $this->parseBlocks($body);
        $spec = [
            'metadata' => $metadata,
            'sources' => [],
            'transformations' => [],
            'rules' => [],
            'sections' => [],
        ];

        foreach ($blocks as $block) {
            $heading = $block['heading'];
            if (strpos($heading, 'source.') === 0) {
                $spec['sources'][$heading] = $block['data'];
            } elseif (strpos($heading, 'transform.') === 0) {
                $spec['transformations'][$heading] = $block['data'];
            } elseif (strpos($heading, 'rules.') === 0) {
                $spec['rules'][$heading] = $block['data'];
            } else {
                $spec['sections'][] = [
                    'heading' => $heading,
                    'markdown' => $block['markdown'],
                ];
            }
        }

        $this->validate($spec);
        return $spec;
    }

    /**
     * Obtiene el payload real que ya utiliza la plataforma para informes
     * psicométricos. La ejecución queda limitada al proceso y empresa dados.
     */
    public function execute(string $markdown, int $processId, int $userId, int $companyId): array
    {
        if ($processId <= 0 || $userId <= 0 || $companyId <= 0) {
            throw new InvalidArgumentException('El contexto del informe requiere proceso, usuario y empresa.');
        }

        $spec = $this->parse($markdown);
        $data = (new PostulantReportDataService())->payloadForProcessUser($processId, $userId);
        $candidateCompanyId = (int) ($data['candidate']['company_id'] ?? 0);
        $processCompanyId = (int) ($data['process']['company_id'] ?? 0);
        if (!$data['available'] || $candidateCompanyId !== $companyId || ($processCompanyId > 0 && $processCompanyId !== $companyId)) {
            return [
                'available' => false,
                'message' => 'El informe no está disponible para la empresa indicada.',
                'spec' => $spec,
            ];
        }

        return [
            'available' => true,
            'spec' => $spec,
            'payload' => $data,
            'variables' => $this->resolveTransformations($spec['transformations'], $data),
            'resolved_at' => date('c'),
        ];
    }

    private function validate(array $spec): void
    {
        $metadata = $spec['metadata'];
        foreach (['report_key', 'version', 'scope', 'entity', 'output'] as $required) {
            if (!isset($metadata[$required]) || trim((string) $metadata[$required]) === '') {
                throw new InvalidArgumentException('Falta el metadato obligatorio: ' . $required . '.');
            }
        }
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', (string) $metadata['report_key'])) {
            throw new InvalidArgumentException('report_key debe usar un identificador estable en minúsculas.');
        }
        if (!is_int($metadata['version']) || (int) $metadata['version'] < 1) {
            throw new InvalidArgumentException('version debe ser un entero positivo.');
        }
        if (isset($metadata['interpreter_version']) && !preg_match('/^[0-9]+(?:\.[0-9]+)?$/', (string) $metadata['interpreter_version'])) {
            throw new InvalidArgumentException('interpreter_version no tiene un formato válido.');
        }
        if ((string) $metadata['scope'] !== 'company' || (string) $metadata['entity'] !== 'process_user') {
            throw new InvalidArgumentException('El informe debe declarar scope: company y entity: process_user.');
        }
        if (!in_array((string) $metadata['output'], ['pdf', 'html', 'screen'], true)) {
            throw new InvalidArgumentException('El formato de salida declarado no está permitido.');
        }
        $template = (new ReportTemplateCatalog())->resolve((string) $metadata['template_key']);
        if (isset($metadata['template_version'])) {
            if (!is_int($metadata['template_version']) || (int) $metadata['template_version'] < 1) {
                throw new InvalidArgumentException('template_version debe ser un entero positivo.');
            }
            if ((int) $metadata['template_version'] !== (int) $template['version']) {
                throw new InvalidArgumentException('La versión de la plantilla visual no está soportada.');
            }
        }

        foreach ($spec['sources'] as $name => $source) {
            if (!isset(self::ALLOWED_SOURCES[$name])) {
                throw new InvalidArgumentException('Fuente no permitida: ' . $name . '.');
            }
            $allowed = self::ALLOWED_SOURCES[$name];
            if (($source['provider'] ?? '') !== $allowed['provider'] || ($source['method'] ?? '') !== $allowed['method']) {
                throw new InvalidArgumentException('Proveedor o método no autorizado para ' . $name . '.');
            }
        }

        foreach ($spec['transformations'] as $name => $transformation) {
            $formula = $this->formulaName((string) ($transformation['formula'] ?? ''));
            if ($formula === '' || !in_array($formula, self::ALLOWED_FORMULAS, true)) {
                throw new InvalidArgumentException('Fórmula no permitida en ' . $name . '.');
            }
        }

        if (!$spec['sources']) {
            throw new InvalidArgumentException('La especificación debe declarar al menos una fuente de datos.');
        }
        if (!$spec['sections']) {
            throw new InvalidArgumentException('La especificación debe contener al menos una sección de salida.');
        }
        $this->validateTemplateReferences($spec);
    }

    private function validateTemplateReferences(array $spec): void
    {
        $availablePaths = ['candidate', 'process', 'sessions', 'ranking', 'ipip', 'instrument_summaries', 'structured_summary', 'resolved_at'];
        foreach (array_keys($spec['transformations']) as $transformationName) {
            $availablePaths[] = preg_replace('/^transform\./', '', (string) $transformationName) ?: (string) $transformationName;
        }
        foreach ($spec['sections'] as $section) {
            preg_match_all('/\{\{\s*(?:#each\s+)?([a-z0-9_.-]+)/i', (string) $section['markdown'], $matches);
            foreach ($matches[1] ?? [] as $path) {
                $root = explode('.', (string) $path, 2)[0];
                if (!in_array($root, $availablePaths, true)) {
                    throw new InvalidArgumentException('Variable no permitida en la sección "' . $section['heading'] . '": ' . $path . '.');
                }
            }
        }
    }

    private function extractFrontMatter(string $markdown): array
    {
        if (substr($markdown, 0, 4) !== "---\n") {
            throw new InvalidArgumentException('El Markdown debe comenzar con un bloque frontmatter.');
        }
        $end = strpos($markdown, "\n---", 4);
        if ($end === false) {
            throw new InvalidArgumentException('El bloque frontmatter no está cerrado.');
        }

        $front = substr($markdown, 4, $end - 4);
        $body = ltrim(substr($markdown, $end + 4));
        return [$this->parseKeyValueLines($front), $body];
    }

    private function parseBlocks(string $body): array
    {
        $lines = explode("\n", $body);
        $blocks = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $matches)) {
                if ($current !== null) {
                    $blocks[] = $this->finalizeBlock($current);
                }
                $current = [
                    'heading' => trim($matches[1]),
                    'lines' => [],
                ];
                continue;
            }
            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }
        if ($current !== null) {
            $blocks[] = $this->finalizeBlock($current);
        }
        return $blocks;
    }

    private function finalizeBlock(array $block): array
    {
        $lines = $block['lines'];
        $markdown = trim(implode("\n", $lines));
        $data = $this->parseKeyValueLines($markdown);
        return [
            'heading' => $block['heading'],
            'data' => $data,
            'markdown' => $markdown,
        ];
    }

    /** Parses the deliberately small YAML-like subset used by the report DSL. */
    private function parseKeyValueLines(string $text): array
    {
        $result = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '- ') === 0) {
                continue;
            }
            if (!preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $key = $matches[1];
            $value = trim($matches[2]);
            $result[$key] = $this->normalizeScalar($value);
        }
        return $result;
    }

    private function normalizeScalar(string $value)
    {
        if ($value === '') {
            return '';
        }
        if (($value[0] ?? '') === '[' && substr($value, -1) === ']') {
            return array_values(array_filter(array_map('trim', explode(',', substr($value, 1, -1))), static fn(string $item): bool => $item !== ''));
        }
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        return trim($value, " \t\"'");
    }

    private function formulaName(string $formula): string
    {
        return preg_match('/^([a-z_][a-z0-9_]*)\s*\(/i', trim($formula), $matches) ? strtolower($matches[1]) : '';
    }

    private function resolveTransformations(array $transformations, array $payload): array
    {
        $variables = [];
        foreach ($transformations as $name => $definition) {
            $formula = trim((string) ($definition['formula'] ?? ''));
            $value = $this->evaluateFormula($formula, $payload);
            $variables[$name] = [
                'value' => $value,
                'formula' => $formula,
                'source' => $definition['source'] ?? null,
            ];
        }
        return $variables;
    }

    private function evaluateFormula(string $formula, array $payload): ?float
    {
        if (!preg_match('/^([a-z_][a-z0-9_]*)\s*\((.*)\)$/i', $formula, $matches)) {
            return null;
        }
        $name = strtolower($matches[1]);
        $arguments = $this->splitArguments($matches[2]);
        $values = [];
        foreach ($arguments as $argument) {
            if (preg_match('/^(min|max):\s*(-?[0-9]+(?:\.[0-9]+)?)$/i', trim($argument), $named)) {
                continue;
            }
            $value = $this->resolveValue(trim($argument), $payload);
            if ($value !== null) {
                $values[] = $value;
            }
        }
        if (!$values) {
            return null;
        }

        switch ($name) {
            case 'average':
                return round(array_sum($values) / count($values), 2);
            case 'inverse':
                return round(11.0 - $values[0], 2);
            case 'sten_to_percentage':
                return round(max(0.0, min(100.0, max(1.0, min(10.0, $values[0])) * 10.0)), 2);
            case 'normalize_ticl':
                return round(max(0.0, min(100.0, ((120.0 - $values[0]) / 90.0) * 100.0)), 2);
            case 'clamp':
                return round(max(0.0, min(100.0, $values[0])), 2);
            default:
                return null;
        }
    }

    private function resolveValue(string $path, array $payload): ?float
    {
        if (is_numeric($path)) {
            return (float) $path;
        }
        if (preg_match('/^ranking\.(.+)$/', $path, $matches)) {
            $value = $payload['ranking_row'][$matches[1]] ?? null;
            return is_numeric($value) ? (float) $value : null;
        }
        if (preg_match('/^ipip\.([A-Za-z0-9]+)$/', $path, $matches)) {
            foreach (($payload['ipip_factors'] ?? []) as $factor) {
                if (strcasecmp((string) ($factor['code'] ?? ''), $matches[1]) === 0 && is_numeric($factor['score'] ?? null)) {
                    return (float) $factor['score'];
                }
            }
        }
        return null;
    }

    private function splitArguments(string $arguments): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $arguments)), static fn(string $value): bool => $value !== ''));
    }
}
