<?php
declare(strict_types=1);

final class Template
{
    private array $viewsPaths;
    private string $layoutsPath;
    private array $globals;

    public function __construct($viewsPath, string $layoutsPath, array $globals = [])
    {
        $viewsPaths = is_array($viewsPath) ? $viewsPath : [$viewsPath];
        $this->viewsPaths = array_map(static function (string $path): string {
            return rtrim($path, '/');
        }, $viewsPaths);
        $this->layoutsPath = rtrim($layoutsPath, '/');
        $this->globals = $globals;
    }

    public function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $content = $this->renderFile($this->resolveViewFile($view), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->layoutsPath . '/' . $layout . '.php', array_merge($data, [
            'content' => $content,
        ]));
    }

    private function resolveViewFile(string $view): string
    {
        $relativePath = ltrim($view, '/') . '.php';

        foreach ($this->viewsPaths as $viewsPath) {
            $file = $viewsPath . '/' . $relativePath;
            if (is_file($file)) {
                return $file;
            }
        }

        throw new RuntimeException('Vista no encontrada: ' . $relativePath);
    }

    private function renderFile(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Vista no encontrada: ' . $file);
        }

        extract(array_merge($this->globals, $data), EXTR_SKIP);

        ob_start();
        require $file;

        return ob_get_clean();
    }
}
