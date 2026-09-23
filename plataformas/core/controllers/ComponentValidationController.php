<?php
declare(strict_types=1);

final class ComponentValidationController extends Controller
{
    private ComponentValidationModel $model;

    public function __construct(?Template $view = null, ?ComponentValidationModel $model = null)
    {
        parent::__construct($view);
        $this->model = $model ?: new ComponentValidationModel();
    }

    public function review(): void
    {
        $user = $this->requireParticipant();
        $message = null;
        $messageType = 'success';
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $encoded = (string) ($_POST['validation'] ?? '');
            $raw = strlen($encoded) <= 8192 ? json_decode($encoded, true) : null;
            if (!is_array($raw)) {
                $error = 'No se recibieron resultados válidos de la revisión.';
            } else {
                try {
                    $recordId = $this->model->record((int) $user['id'], (int) $user['company_id'], $raw);
                    if ($recordId > 0) {
                        $message = 'Revisión guardada.';
                    } else {
                        $error = 'No se pudo registrar esta revisión. Inténtalo nuevamente.';
                    }
                } catch (Throwable $exception) {
                    $error = 'No se pudo guardar la revisión. Inténtalo nuevamente.';
                }
            }
        }
        $latestValidation = $this->model->latestForUser((int) $user['id'], (int) $user['company_id']);
        if ($message !== null) {
            $outcome = (string) ($latestValidation['outcome'] ?? 'failed');
            if ($outcome === 'passed') {
                $message = 'Revisión guardada. Todos los componentes están disponibles.';
                $messageType = 'success';
            } elseif ($outcome === 'partial') {
                $message = 'Revisión guardada con advertencias. Revisa los componentes marcados.';
                $messageType = 'warning';
            } else {
                $message = 'Revisión guardada, pero uno o más componentes no están disponibles. Revisa los elementos marcados.';
                $messageType = 'warning';
            }
        }
        $this->render('component_validation/review', [
            'title' => 'Revisión Componentes | e-talent',
            'currentPage' => 'component-validation.review',
            'message' => $message,
            'messageType' => $messageType,
            'error' => $error,
            'latestValidation' => $latestValidation,
            'csrfToken' => csrf_token(),
        ]);
    }

    private function requireParticipant(): array
    {
        require_auth();
        $user = current_user() ?: [];
        if ((string) ($user['role'] ?? '') !== 'usuario' || (int) ($user['company_id'] ?? 0) <= 0) {
            platform_error(403, 'La revisión de componentes está disponible para usuarios asociados a una empresa.');
        }
        header('Cache-Control: private, no-store');
        return $user;
    }
}
