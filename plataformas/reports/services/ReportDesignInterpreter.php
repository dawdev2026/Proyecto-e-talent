<?php

declare(strict_types=1);

/**
 * Interpreta el contrato Markdown de diseño de informes.
 * Solo permite metadatos declarativos; no ejecuta código ni plantillas arbitrarias.
 */
final class ReportDesignInterpreter
{
    public function parse(string $markdown, bool $strict = true): array
    {
        $result = [
            'present' => trim($markdown) !== '',
            'valid' => false,
            'errors' => [],
            'design_key' => null,
            'design_version' => null,
            'template_key' => null,
            'renderer' => null,
            'metadata' => [],
            'source' => $markdown,
        ];

        try {
            $metadata = $this->parseFrontMatter($markdown);
            $errors = $this->validate($metadata);
            $result['metadata'] = $metadata;
            $result['errors'] = $errors;
            $result['design_key'] = $metadata['design_key'] ?? null;
            $result['design_version'] = $metadata['design_version'] ?? null;
            $result['template_key'] = $metadata['template_key'] ?? null;
            $result['renderer'] = $metadata['renderer'] ?? null;
            $result['valid'] = $errors === [];
        } catch (InvalidArgumentException $exception) {
            $result['errors'] = [$exception->getMessage()];
        }

        if ($strict && !$result['valid']) {
            throw new InvalidArgumentException(implode(' ', $result['errors'] ?: ['Diseño de informe inválido.']));
        }

        return $result;
    }

    private function parseFrontMatter(string $markdown): array
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        $start = 0;
        while ($start < count($lines) && trim((string) $lines[$start]) === '') {
            $start++;
        }

        if (($lines[$start] ?? null) !== '---') {
            throw new InvalidArgumentException('El diseño debe comenzar con un bloque YAML delimitado por ---.' );
        }

        $end = null;
        for ($index = $start + 1; $index < count($lines); $index++) {
            if (trim((string) $lines[$index]) === '---') {
                $end = $index;
                break;
            }
        }
        if ($end === null) {
            throw new InvalidArgumentException('El bloque YAML del diseño no tiene cierre ---.' );
        }

        return $this->parseYaml(array_slice($lines, $start + 1, $end - $start - 1));
    }

    private function validate(array $metadata): array
    {
        $errors = [];
        $designKey = (string) ($metadata['design_key'] ?? '');
        if ($designKey === '' || preg_match('/^[a-z0-9][a-z0-9._-]{2,100}$/', $designKey) !== 1) {
            $errors[] = 'design_key debe usar minúsculas, números, punto, guion o guion bajo.';
        }
        if (!is_int($metadata['design_version'] ?? null) || (int) $metadata['design_version'] < 1) {
            $errors[] = 'design_version debe ser un entero mayor o igual a 1.';
        }

        $templateKey = (string) ($metadata['template_key'] ?? '');
        if ($templateKey === '') {
            $errors[] = 'template_key es obligatorio.';
        } else {
            try {
                (new ReportTemplateCatalog())->resolve($templateKey);
            } catch (Throwable $exception) {
                $errors[] = 'template_key no corresponde a una plantilla permitida.';
            }
        }

        if (($metadata['renderer'] ?? null) !== 'pdf') {
            $errors[] = 'renderer debe ser pdf.';
        }
        if (($metadata['output']['format'] ?? null) !== 'pdf') {
            $errors[] = 'output.format debe ser pdf.';
        }
        if (($metadata['output']['disposition'] ?? null) !== 'attachment') {
            $errors[] = 'output.disposition debe ser attachment para forzar la descarga.';
        }
        if (($metadata['page']['size'] ?? null) !== 'A4') {
            $errors[] = 'page.size debe ser A4.';
        }
        if (($metadata['page']['orientation'] ?? null) !== 'portrait') {
            $errors[] = 'page.orientation debe ser portrait.';
        }

        return $errors;
    }

    private function parseYaml(array $lines): array
    {
        $root = [];
        $stack = [[-1, &$root]];

        foreach ($lines as $lineNumber => $line) {
            $raw = rtrim((string) $line);
            if (trim($raw) === '' || preg_match('/^\s*#/', $raw) === 1) {
                continue;
            }
            $indent = strlen($raw) - strlen(ltrim($raw, ' '));
            if ($indent % 2 !== 0) {
                throw new InvalidArgumentException('La indentación del diseño debe usar múltiplos de dos espacios (línea ' . ($lineNumber + 1) . ').');
            }
            $content = trim($raw);
            while (count($stack) > 1 && $indent <= $stack[count($stack) - 1][0]) {
                array_pop($stack);
            }
            $parent =& $stack[count($stack) - 1][1];

            if (substr($content, 0, 2) === '- ') {
                if (!is_array($parent)) {
                    throw new InvalidArgumentException('Lista inválida en el diseño (línea ' . ($lineNumber + 1) . ').');
                }
                $parent[] = $this->scalar(substr($content, 2));
                continue;
            }

            $separator = strpos($content, ':');
            if ($separator === false) {
                throw new InvalidArgumentException('Entrada YAML inválida en el diseño (línea ' . ($lineNumber + 1) . ').');
            }
            $key = trim(substr($content, 0, $separator));
            $value = trim(substr($content, $separator + 1));
            if ($key === '') {
                throw new InvalidArgumentException('Clave YAML vacía en el diseño (línea ' . ($lineNumber + 1) . ').');
            }
            if ($value === '') {
                $parent[$key] = [];
                $stack[] = [$indent, &$parent[$key]];
            } else {
                $parent[$key] = $this->scalar($value);
            }
            unset($parent);
        }

        return $root;
    }

    private function scalar(string $value)
    {
        $value = trim($value);
        if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
            return stripcslashes(substr($value, 1, -1));
        }
        if (($value[0] ?? '') === "'" && substr($value, -1) === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if ($value === 'true' || $value === 'false') return $value === 'true';
        if ($value === 'null') return null;
        if (preg_match('/^-?\d+$/', $value) === 1) return (int) $value;
        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) return (float) $value;
        return $value;
    }
}
