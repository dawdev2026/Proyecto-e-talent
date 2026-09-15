<?php
declare(strict_types=1);

return [
    'key' => 'reports',
    'name' => 'Informes',
    'description' => 'Sub-plataforma para definir, generar, asignar y consultar informes por empresa.',
    'route' => 'reports.generate',
    'permissions' => [
        'manage_reports',
        'assign_reports_by_company',
    ],
    'status' => 'active',
];
