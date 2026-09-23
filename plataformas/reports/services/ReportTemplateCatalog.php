<?php
declare(strict_types=1);

/**
 * Catálogo de plantillas visuales del sub-sistema de informes.
 *
 * La plantilla no contiene datos ni reglas de negocio: solo determina cómo
 * presentar el resultado del intérprete. Se mantiene en código para evitar
 * consultas adicionales y permitir agregar nuevos diseños sin cambiar el
 * contrato de fuentes del Markdown.
 */
final class ReportTemplateCatalog
{
    public const DEFAULT_TEMPLATE = 'platform_postulant_v1';

    private const DEFINITIONS = [
        self::DEFAULT_TEMPLATE => [
            'key' => self::DEFAULT_TEMPLATE,
            'version' => 1,
            'renderer' => 'designed',
            'label' => 'Plantilla institucional de postulante',
        ],
        'platform_plain_v1' => [
            'key' => 'platform_plain_v1',
            'version' => 1,
            'renderer' => 'plain',
            'label' => 'Plantilla de texto heredada',
        ],
    ];

    public function resolve(?string $key): array
    {
        $key = trim((string) $key);
        if ($key === '') {
            $key = self::DEFAULT_TEMPLATE;
        }
        if (!isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException('Plantilla visual no permitida: ' . $key . '.');
        }
        return self::DEFINITIONS[$key];
    }

    public function all(): array
    {
        return array_values(self::DEFINITIONS);
    }
}
