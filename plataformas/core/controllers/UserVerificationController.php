<?php
declare(strict_types=1);

final class UserVerificationController extends Controller
{
    private UserVerificationModel $verification;

    public function __construct(?Template $view = null, ?UserVerificationModel $verification = null)
    {
        parent::__construct($view);
        $this->verification = $verification ?: new UserVerificationModel();
    }

    public function index(): void
    {
        $company = current_company_url_context();
        if (!$company || (int) ($company['verification_enabled'] ?? 0) !== 1) {
            platform_error(404, 'La pantalla de verificación no está disponible.');
        }

        $email = '';
        $result = null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Ingresa un correo electrónico válido.';
            } else {
                $result = $this->verification->findForCompany($email, (int) $company['id']);
                if (!$result) {
                    $error = 'El correo no existe en esta empresa.';
                } elseif (!$result['active']) {
                    $error = 'El usuario existe, pero no está activo.';
                }
            }
        }

        $this->render('verification/index', [
            'title' => 'Verificar usuario | ' . (string) $company['name'],
            'company' => $company,
            'email' => $email,
            'result' => $result,
            'error' => $error,
        ], null);
    }
}
