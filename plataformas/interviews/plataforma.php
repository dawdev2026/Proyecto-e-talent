<?php
declare(strict_types=1);

return [
    'key' => 'interviews',
    'name' => 'Entrevistas seleccion',
    'description' => 'Procesos de entrevistas 1 a 1 con Daily, transcripcion, apuntes y reportes finales.',
    'route' => 'interviews',
    'database' => 'metricatest_interviews',
    'permissions' => [
        'manage_interview_processes',
        'conduct_selection_interviews',
        'view_interview_reports',
        'manage_interview_settings',
    ],
];
