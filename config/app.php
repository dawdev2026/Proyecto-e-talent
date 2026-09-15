<?php
declare(strict_types=1);

return [
    'name' => 'e-talent',
    'default_platform' => 'core',
    'timezone' => 'America/Santiago',
    // URL pública opcional para enlaces enviados por correo. Si queda vacía,
    // se utiliza el host de la solicitud web actual.
    'public_url' => '',
    'force_https' => 'auto',
    'tmp_path' => dirname(__DIR__) . '/tmp',
    'public_tmp_url' => 'tmp/',
    'max_upload_mb' => 5,
];
