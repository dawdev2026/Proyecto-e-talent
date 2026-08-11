<?php
declare(strict_types=1);

abstract class Controller
{
    protected Template $view;

    public function __construct(?Template $view = null)
    {
        $this->view = $view ?: template();
    }

    protected function render(string $view, array $data = [], ?string $layout = 'app'): void
    {
        echo $this->view->render($view, $data, $layout);
    }
}
