<?php
declare(strict_types=1);

final class VisualPresetAnalyzer
{
    public function analyzeLogin(string $imagePath, string $storedPath): array
    {
        $sample = $this->sample($imagePath);
        $accent = $this->accentColor($sample['colors']);
        $dark = $this->darkColor($sample['colors']);
        $assets = $this->extractLoginAssets($imagePath, $storedPath, $accent);

        return [
            'login_background_color' => $this->backgroundColor($sample, $accent),
            'login_form_background_color' => $accent,
            'login_background_path' => $assets['background_path'],
            'login_logo_path' => $assets['logo_path'],
            'login_form_position' => $assets['form_position'],
            'login_title_font_size' => '32',
            'login_subtitle_font_size' => '16',
            'login_title_color' => $dark,
            'login_subtitle_color' => $dark,
            'login_button_color' => $dark,
        ];
    }

    public function analyzeDesign(string $imagePath): array
    {
        $sample = $this->sample($imagePath);
        $accent = $this->accentColor($sample['colors']);
        $dark = $this->darkColor($sample['colors']);
        $light = $this->lightColor($sample['colors']);
        $background = $this->lightColor(array_merge($sample['zones']['center'] ?? [], $sample['zones']['bottom'] ?? []));
        $tableAlt = $this->mutedColor($sample['colors'], '#e9ebf6');

        return [
            'app_primary_color' => $accent,
            'portal_text_color' => $dark,
            'topbar_background_color' => $accent,
            'topbar_menu_background_color' => $accent,
            'topbar_menu_button_color' => $accent,
            'topbar_logo_position' => 'center',
            'layout_background_color' => $background,
            'card_header_background_color' => $accent,
            'card_content_background_color' => $light,
            'card_text_color' => $dark,
            'button_background_color' => $accent,
            'button_text_color' => $dark,
            'table_border_color' => $tableAlt,
            'table_header_background_color' => $accent,
            'table_header_text_color' => $dark,
            'table_body_background_color' => $light,
            'table_body_text_color' => $dark,
        ];
    }

    private function extractLoginAssets(string $imagePath, string $storedPath, string $accent): array
    {
        $image = $this->openImage($imagePath);
        $width = imagesx($image);
        $height = imagesy($image);
        $rect = $this->detectLoginPanel($image, $accent);
        $base = pathinfo($storedPath, PATHINFO_FILENAME);
        $dir = dirname($imagePath);
        $publicDir = trim(dirname($storedPath), '.');
        $publicDir = $publicDir === '' ? '' : rtrim($publicDir, '/') . '/';

        $backgroundFile = $base . '_background_clean.jpg';
        $logoFile = $base . '_logo_clean.png';
        $backgroundPath = $publicDir . $backgroundFile;
        $logoPath = $publicDir . $logoFile;

        $this->createCleanBackground($image, $rect, $dir . '/' . $backgroundFile);
        $this->createCleanLogo($image, $rect, $accent, $dir . '/' . $logoFile);
        imagedestroy($image);

        $centerX = $rect['x'] + ($rect['w'] / 2);
        if ($centerX < $width * .38) {
            $position = 'left';
        } elseif ($centerX > $width * .62) {
            $position = 'right';
        } else {
            $position = 'center';
        }

        return [
            'background_path' => $backgroundPath,
            'logo_path' => $logoPath,
            'form_position' => $position,
        ];
    }

    private function detectLoginPanel($image, string $accent): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$ar, $ag, $ab] = $this->rgb($accent);
        $step = max(2, (int) floor(max($width, $height) / 520));
        $columnHits = [];
        $sampleRows = 0;
        for ($y = (int) round($height * .12); $y < $height * .88; $y += $step) {
            $sampleRows++;
        }

        for ($x = 0; $x < $width; $x += $step) {
            $hits = 0;
            for ($y = (int) round($height * .12); $y < $height * .88; $y += $step) {
                if ($this->isAccentPixel($image, $x, $y, $ar, $ag, $ab)) {
                    $hits++;
                }
            }
            $columnHits[$x] = $sampleRows > 0 ? $hits / $sampleRows : 0;
        }

        $segments = [];
        $open = null;
        foreach ($columnHits as $x => $ratio) {
            if ($ratio > .42 && $open === null) {
                $open = ['x1' => $x, 'x2' => $x];
            } elseif ($ratio > .42 && $open !== null) {
                $open['x2'] = $x;
            } elseif ($open !== null) {
                if (($open['x2'] - $open['x1']) > $width * .10) {
                    $segments[] = $open;
                }
                $open = null;
            }
        }
        if ($open !== null && ($open['x2'] - $open['x1']) > $width * .10) {
            $segments[] = $open;
        }

        usort($segments, static fn (array $a, array $b): int => ($b['x2'] - $b['x1']) <=> ($a['x2'] - $a['x1']));
        $segment = $segments[0] ?? null;

        if (!$segment) {
            return [
                'x' => (int) round($width * .62),
                'y' => (int) round($height * .16),
                'w' => (int) round($width * .28),
                'h' => (int) round($height * .66),
            ];
        }

        $rowHits = [];
        $sampleColumns = 0;
        for ($x = $segment['x1']; $x <= $segment['x2']; $x += $step) {
            $sampleColumns++;
        }

        for ($y = 0; $y < $height; $y += $step) {
            $hits = 0;
            for ($x = $segment['x1']; $x <= $segment['x2']; $x += $step) {
                if ($this->isAccentPixel($image, $x, $y, $ar, $ag, $ab)) {
                    $hits++;
                }
            }
            $rowHits[$y] = $sampleColumns > 0 ? $hits / $sampleColumns : 0;
        }

        $y1 = null;
        $y2 = null;
        foreach ($rowHits as $y => $ratio) {
            if ($ratio > .42 && $y1 === null) {
                $y1 = $y;
            }
            if ($ratio > .42) {
                $y2 = $y;
            }
        }

        $padX = (int) round($width * .01);
        $padY = (int) round($height * .01);
        $x1 = max(0, $segment['x1'] - $padX);
        $y1 = max(0, ($y1 ?? (int) round($height * .16)) - $padY);
        $x2 = min($width - 1, $segment['x2'] + $padX);
        $y2 = min($height - 1, ($y2 ?? (int) round($height * .82)) + $padY);

        return [
            'x' => $x1,
            'y' => $y1,
            'w' => max(1, $x2 - $x1 + 1),
            'h' => max(1, $y2 - $y1 + 1),
        ];
    }

    private function isAccentPixel($image, int $x, int $y, int $ar, int $ag, int $ab): bool
    {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 255;
        $g = ($rgb >> 8) & 255;
        $b = $rgb & 255;
        $distance = abs($r - $ar) + abs($g - $ag) + abs($b - $ab);

        return $distance < 95 || ($r > 185 && $g > 130 && $b < 95);
    }

    private function createCleanBackground($image, array $rect, string $outputPath): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $sourceWidth = max(1, min($width, $rect['x'] - 12));

        if ($sourceWidth < $width * .28) {
            $sourceWidth = $width;
        }

        $background = imagecreatetruecolor($width, $height);
        imagecopyresampled($background, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $height);
        imagejpeg($background, $outputPath, 90);
        imagedestroy($background);
    }

    private function createCleanLogo($image, array $rect, string $accent, string $outputPath): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$ar, $ag, $ab] = $this->rgb($accent);
        $scanX1 = max(0, $rect['x'] + (int) round($rect['w'] * .10));
        $scanX2 = min($width - 1, $rect['x'] + (int) round($rect['w'] * .90));
        $scanY1 = max(0, $rect['y'] + (int) round($rect['h'] * .06));
        $scanY2 = min($height - 1, $rect['y'] + (int) round($rect['h'] * .30));
        $rowActivity = [];

        for ($y = $scanY1; $y <= $scanY2; $y++) {
            $hits = 0;
            $total = 0;
            for ($x = $scanX1; $x <= $scanX2; $x += 2) {
                $total++;
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $distance = abs($r - $ar) + abs($g - $ag) + abs($b - $ab);
                if ($distance > 95 && ($r < 245 || $g < 245 || $b < 245)) {
                    $hits++;
                }
            }
            $rowActivity[$y] = $total > 0 ? $hits / $total : 0;
        }

        $segments = [];
        $open = null;
        $gap = 0;
        foreach ($rowActivity as $y => $ratio) {
            if ($ratio > .015 && $open === null) {
                $open = ['y1' => $y, 'y2' => $y];
                $gap = 0;
            } elseif ($ratio > .015 && $open !== null) {
                $open['y2'] = $y;
                $gap = 0;
            } elseif ($open !== null) {
                $gap++;
                if ($gap > 8) {
                    if (($open['y2'] - $open['y1']) > 18) {
                        $segments[] = $open;
                    }
                    $open = null;
                    $gap = 0;
                }
            }
        }
        if ($open !== null && ($open['y2'] - $open['y1']) > 18) {
            $segments[] = $open;
        }

        if ($segments) {
            $scanY1 = max($scanY1, $segments[0]['y1'] - 4);
            $scanY2 = min($scanY2, $segments[0]['y2'] + 4);
        }

        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;

        for ($y = $scanY1; $y <= $scanY2; $y++) {
            for ($x = $scanX1; $x <= $scanX2; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $distance = abs($r - $ar) + abs($g - $ag) + abs($b - $ab);
                if ($distance > 95 && ($r < 245 || $g < 245 || $b < 245)) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            $minX = $scanX1;
            $minY = $scanY1;
            $maxX = $scanX2;
            $maxY = $scanY2;
        }

        $padding = 12;
        $minX = max(0, $minX - $padding);
        $minY = max(0, $minY - $padding);
        $maxX = min($width - 1, $maxX + $padding);
        $maxY = min($height - 1, $maxY + $padding);
        $logoWidth = max(1, $maxX - $minX + 1);
        $logoHeight = max(1, $maxY - $minY + 1);
        $logo = imagecreatetruecolor($logoWidth, $logoHeight);
        imagesavealpha($logo, true);
        $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
        imagefill($logo, 0, 0, $transparent);

        for ($y = 0; $y < $logoHeight; $y++) {
            for ($x = 0; $x < $logoWidth; $x++) {
                $rgb = imagecolorat($image, $minX + $x, $minY + $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $distance = abs($r - $ar) + abs($g - $ag) + abs($b - $ab);
                if ($distance > 70) {
                    imagesetpixel($logo, $x, $y, imagecolorallocatealpha($logo, $r, $g, $b, 0));
                }
            }
        }

        imagepng($logo, $outputPath);
        imagedestroy($logo);
    }

    private function sample(string $path): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('La extension GD no esta disponible para analizar imagenes.');
        }

        $image = $this->openImage($path);

        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(8, (int) floor(max($width, $height) / 120));
        $colors = [];
        $zones = ['top' => [], 'center' => [], 'bottom' => [], 'right' => []];

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $color = $this->quantize(($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
                $colors[$color] = ($colors[$color] ?? 0) + 1;

                if ($y < $height * .18) {
                    $zones['top'][$color] = ($zones['top'][$color] ?? 0) + 1;
                } elseif ($y > $height * .82) {
                    $zones['bottom'][$color] = ($zones['bottom'][$color] ?? 0) + 1;
                } else {
                    $zones['center'][$color] = ($zones['center'][$color] ?? 0) + 1;
                }

                if ($x > $width * .58 && $y > $height * .14 && $y < $height * .86) {
                    $zones['right'][$color] = ($zones['right'][$color] ?? 0) + 1;
                }
            }
        }

        imagedestroy($image);
        arsort($colors);
        foreach ($zones as &$zone) {
            arsort($zone);
        }
        unset($zone);

        return ['colors' => $colors, 'zones' => $zones];
    }

    private function openImage(string $path)
    {
        $info = getimagesize($path);
        if (!$info) {
            throw new RuntimeException('No se pudo leer la imagen de referencia.');
        }

        $mime = $info['mime'] ?? '';
        if ($mime === 'image/jpeg') {
            $image = imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png') {
            $image = imagecreatefrompng($path);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = imagecreatefromwebp($path);
        } else {
            throw new RuntimeException('Formato de imagen no soportado para analisis.');
        }

        if (!$image) {
            throw new RuntimeException('No se pudo procesar la imagen de referencia.');
        }

        imagepalettetotruecolor($image);
        return $image;
    }

    private function backgroundColor(array $sample, string $accent): string
    {
        foreach ($sample['colors'] as $hex => $count) {
            if ($hex !== $accent && $this->luminance($hex) > 35 && $this->luminance($hex) < 235) {
                return $hex;
            }
        }

        return $this->zoneColor($sample, 'center', '#f8fafd');
    }

    private function quantize(int $red, int $green, int $blue): string
    {
        $red = min(255, (int) round($red / 16) * 16);
        $green = min(255, (int) round($green / 16) * 16);
        $blue = min(255, (int) round($blue / 16) * 16);

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    private function accentColor(array $colors): string
    {
        foreach ($colors as $hex => $count) {
            [$r, $g, $b] = $this->rgb($hex);
            if ($r > 180 && $g > 130 && $b < 80) {
                return $hex;
            }
        }

        foreach ($colors as $hex => $count) {
            [$r, $g, $b] = $this->rgb($hex);
            if (($r - $b) > 50 && ($g - $b) > 35 && $r > 140) {
                return $hex;
            }
        }

        return '#ffc000';
    }

    private function darkColor(array $colors): string
    {
        foreach ($colors as $hex => $count) {
            if ($this->luminance($hex) < 32) {
                return $hex;
            }
        }

        return '#000000';
    }

    private function lightColor(array $colors): string
    {
        foreach ($colors as $hex => $count) {
            if ($this->luminance($hex) > 230) {
                return $hex;
            }
        }

        return '#ffffff';
    }

    private function mutedColor(array $colors, string $fallback): string
    {
        foreach ($colors as $hex => $count) {
            [$r, $g, $b] = $this->rgb($hex);
            $lum = $this->luminance($hex);
            if ($lum > 190 && $lum < 245 && abs($r - $g) < 35 && abs($g - $b) < 55) {
                return $hex;
            }
        }

        return $fallback;
    }

    private function zoneColor(array $sample, string $zone, string $fallback): string
    {
        $colors = $sample['zones'][$zone] ?? [];
        if (!$colors) {
            return $fallback;
        }

        return (string) array_key_first($colors);
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);
        return .2126 * $r + .7152 * $g + .0722 * $b;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
