<?php
declare(strict_types=1);

function valid_image_uploads(array $files, int $maxSizeMb = 5): array
{
    $valid = [];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Una de las imagenes no se pudo cargar.');
        }

        if (!is_uploaded_file($files['tmp_name'][$i])) {
            throw new RuntimeException('La carga de imagen no es valida.');
        }

        if (($files['size'][$i] ?? 0) > $maxSizeMb * 1024 * 1024) {
            throw new RuntimeException('Cada imagen debe pesar maximo ' . $maxSizeMb . ' MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($files['tmp_name'][$i]);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Solo se permiten imagenes JPG, PNG o WEBP.');
        }

        if (@getimagesize($files['tmp_name'][$i]) === false) {
            throw new RuntimeException('Una imagen cargada no pudo validarse.');
        }

        $valid[] = [
            'tmp_name' => $files['tmp_name'][$i],
            'original_name' => preg_replace('/[^A-Za-z0-9._-]/', '_', basename($files['name'][$i])),
            'mime' => $mime,
            'extension' => $allowed[$mime],
            'size' => (int) $files['size'][$i],
        ];
    }

    return $valid;
}
