<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    private PlatformSettingsModel $settings;
    private PasswordResetModel $passwordResets;

    public function __construct(?Template $view = null, ?PlatformSettingsModel $settings = null, ?PasswordResetModel $passwordResets = null)
    {
        parent::__construct($view);
        $this->settings = $settings ?: new PlatformSettingsModel();
        $this->passwordResets = $passwordResets ?: new PasswordResetModel();
    }

    public function login(): void
    {
        if (current_user()) {
            redirect(profile_home_url());
        }

        $error = null;
        $loginSettings = $this->settings->loginSettings();
        $identifierType = ($loginSettings['login_identifier'] ?? 'email') === 'rut' ? 'rut' : 'email';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $identifier = trim($_POST['identifier'] ?? ($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($identifierType === 'rut') {
                $identifier = UserModel::formatRut($identifier);
                if (!UserModel::isValidRut($identifier)) {
                    $error = 'Ingresa un RUT valido.';
                }
            } else {
                $identifier = mb_strtolower($identifier);
                if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Ingresa un correo valido.';
                }
            }

            if (!$error && login_rate_limited($identifier)) {
                $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentar.';
                security_log('Login bloqueado temporalmente para ' . $identifier);
                $this->render('auth/login', [
                    'title' => 'Ingresar | Metricatest',
                    'error' => $error,
                    'loginSettings' => $loginSettings,
                ]);
                return;
            }

            if (!$error && login($identifier, $password, $identifierType)) {
                clear_login_attempts($identifier);
                redirect(profile_home_url());
            }

            if (!$error) {
                register_login_attempt($identifier);
                $error = 'Credenciales incorrectas o usuario inactivo.';
            }
        }

        $this->render('auth/login', [
            'title' => 'Ingresar | Metricatest',
            'error' => $error,
            'loginSettings' => $loginSettings,
        ]);
    }

    public function logout(): void
    {
        logout();
        redirect(route_url('login'));
    }

    public function forgotPassword(): void
    {
        if (current_user()) {
            redirect(profile_home_url());
        }
        $message = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $issued = $this->passwordResets->issue($email);
                if ($issued) {
                    $this->sendResetEmail($issued);
                }
            }
            $message = 'Si el correo existe y está activo, recibirás instrucciones para restablecer la clave.';
        }
        $this->render('auth/forgot', ['title' => 'Recuperar clave | Metricatest', 'message' => $message], null);
    }

    public function resetPassword(): void
    {
        if (current_user()) {
            redirect(profile_home_url());
        }
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $reset = $token !== '' ? $this->passwordResets->findValid($token) : null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if (!$reset) {
                $error = 'El enlace de recuperación no es válido o ya expiró.';
            } elseif (strlen($password) < 8 || !hash_equals($password, $confirmation)) {
                $error = 'La clave debe tener al menos 8 caracteres y coincidir con su confirmación.';
            } else {
                $this->passwordResets->consume((int) $reset['token_id'], (int) $reset['user_id'], $password);
                flash('success', 'Clave actualizada correctamente.');
                redirect(route_url('login'));
            }
        }
        $this->render('auth/reset', ['title' => 'Restablecer clave | Metricatest', 'token' => $token, 'error' => $error, 'validToken' => (bool) $reset], null);
    }

    private function sendResetEmail(array $issued): void
    {
        $integrations = load_config('integrations');
        $mail = is_array($integrations['mail'] ?? null) ? $integrations['mail'] : [];
        if (empty($mail['enabled'])) {
            security_log('Password reset requested with mail disabled for ' . (string) $issued['email']);
            return;
        }
        $link = app_url('password/reset?token=' . rawurlencode((string) $issued['token']));
        $headers = 'From: ' . ($mail['from_name'] ?? 'Metricatest') . ' <' . ($mail['from_email'] ?? 'no-reply@localhost') . "\r\n";
        @mail((string) $issued['email'], 'Restablecer clave de Metricatest', "Hola " . $issued['name'] . ",\n\nRestablece tu clave en este enlace:\n" . $link . "\n\nEl enlace expira en 60 minutos.", $headers);
    }
}
