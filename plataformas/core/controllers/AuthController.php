<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    private PlatformSettingsModel $settings;
    private PasswordResetModel $passwordResets;
    private CompanyMailSettingsModel $companyMail;
    private LoginVerificationModel $loginVerification;

    public function __construct(?Template $view = null, ?PlatformSettingsModel $settings = null, ?PasswordResetModel $passwordResets = null, ?CompanyMailSettingsModel $companyMail = null, ?LoginVerificationModel $loginVerification = null)
    {
        parent::__construct($view);
        $this->settings = $settings ?: new PlatformSettingsModel();
        $this->passwordResets = $passwordResets ?: new PasswordResetModel();
        $this->companyMail = $companyMail ?: new CompanyMailSettingsModel();
        $this->loginVerification = $loginVerification ?: new LoginVerificationModel();
    }

    public function login(): void
    {
        if (current_user()) {
            redirect(profile_home_url());
        }

        $error = null;
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $loginSettings = $this->settings->loginSettings($companyId > 0 ? $companyId : null);
        $identifierType = ($loginSettings['login_identifier'] ?? 'email') === 'rut' ? 'rut' : 'email';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if ($this->isTwoStepPending()) {
                if (isset($_POST['restart_two_step'])) {
                    unset($_SESSION['login_two_step']);
                    $this->renderLogin($loginSettings);
                    return;
                }
                if (array_key_exists('verification_code', $_POST)) {
                    $this->verifyTwoStep($loginSettings);
                    return;
                }
                unset($_SESSION['login_two_step']);
            }
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
                    'title' => 'Ingresar | e-talent',
                    'error' => $error,
                    'loginSettings' => $loginSettings,
                ], 'auth');
                return;
            }

            $user = !$error ? authenticate_user($identifier, $password, $identifierType) : null;
            if (!$error && $user && $this->twoStepEnabledForUser($user, $loginSettings)) {
                if ($this->startTwoStep($user, $loginSettings)) {
                    $this->renderLogin($loginSettings, null, true);
                    return;
                }
                $error = 'No fue posible enviar el código de verificación. Intenta nuevamente o informa al administrador.';
            } elseif (!$error && $user) {
                complete_login($user);
                clear_login_attempts($identifier);
                redirect(profile_home_url());
            }

            if (!$error) {
                register_login_attempt($identifier);
                $error = 'Credenciales incorrectas o usuario inactivo.';
            }
        }

        $this->renderLogin($loginSettings, $error);
    }

    private function renderLogin(array $loginSettings, ?string $error = null, bool $twoStepPending = false): void
    {
        $this->render('auth/login', [
            'title' => 'Ingresar | e-talent',
            'error' => $error,
            'loginSettings' => $loginSettings,
            'twoStepPending' => $twoStepPending,
        ], 'auth');
    }

    private function twoStepEnabled(array $settings): bool
    {
        return in_array((string) ($settings['login_two_step_enabled'] ?? '0'), ['1', 'true', 'on', 'yes'], true);
    }

    private function twoStepEnabledForUser(array $user, array $settings): bool
    {
        if (!$this->twoStepEnabled($settings)) {
            return false;
        }

        if (strtolower(trim((string) ($user['role'] ?? ''))) === 'usuario') {
            return true;
        }

        $profileId = (int) ($user['profile_id'] ?? 0);
        if ($profileId <= 0) {
            return false;
        }

        try {
            $profile = database()->fetch(
                'SELECT role_key FROM role_profiles WHERE id = ? LIMIT 1',
                [$profileId]
            );
            return strtolower(trim((string) ($profile['role_key'] ?? ''))) === 'usuario';
        } catch (Throwable $exception) {
            security_log('No se pudo resolver el perfil para aplicar 2FA: ' . $exception->getMessage());
            return false;
        }
    }

    private function isTwoStepPending(): bool
    {
        return !empty($_SESSION['login_two_step']['user_id']);
    }

    private function startTwoStep(array $user, array $settings): bool
    {
        try {
            $issued = $this->loginVerification->issue(
                (int) $user['id'],
                (int) ($settings['login_two_step_expiration_minutes'] ?? 5)
            );
            $_SESSION['login_two_step'] = [
                'user_id' => (int) $user['id'],
                'company_id' => (int) ($user['company_id'] ?? 0),
                'identifier' => (string) ($user['email'] ?? ''),
                'created_at' => time(),
            ];
            $this->sendTwoStepEmail($user, $issued, $settings);
            return true;
        } catch (Throwable $exception) {
            unset($_SESSION['login_two_step']);
            security_log('No se pudo iniciar verificación de dos pasos: ' . $exception->getMessage());
            return false;
        }
    }

    private function verifyTwoStep(array $settings): void
    {
        $pending = $_SESSION['login_two_step'] ?? [];
        $userId = (int) ($pending['user_id'] ?? 0);
        $companyId = (int) ($pending['company_id'] ?? 0);
        $settings = $this->settings->loginSettings($companyId > 0 ? $companyId : null);
        if (!$this->twoStepEnabled($settings)) {
            unset($_SESSION['login_two_step']);
            $this->renderLogin($settings, 'La verificación de dos pasos no está activa para esta empresa.', false);
            return;
        }
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        if ($code === '') {
            $this->renderLogin($settings, null, true);
            return;
        }
        $valid = $userId > 0 && $this->loginVerification->verifyLatest(
            $userId,
            $code,
            (int) ($settings['login_two_step_max_attempts'] ?? 5)
        );

        if (!$valid) {
            $this->renderLogin($settings, 'El código ingresado no es válido.', true);
            return;
        }

        try {
            $user = database()->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [$userId]);
            unset($_SESSION['login_two_step']);
            if (!$user) {
                $this->renderLogin($settings, 'El usuario ya no está disponible.', false);
                return;
            }
            complete_login($user);
            redirect(profile_home_url());
        } catch (Throwable $exception) {
            unset($_SESSION['login_two_step']);
            security_log('No se pudo completar el acceso después de verificar 2FA para usuario ' . $userId . ': ' . $exception->getMessage());
            $this->renderLogin($settings, 'El código fue validado, pero no se pudo completar el inicio de sesión. Intenta nuevamente.', false);
        }
    }

    private function sendTwoStepEmail(array $user, array $issued, array $settings): void
    {
        $companyId = (int) ($user['company_id'] ?? 0);
        $mail = $this->mailSettingsForCompany($companyId);
        $mail['encryption'] = $mail['encryption'] ?? 'starttls';
        $mail['smtp_auth'] = array_key_exists('smtp_auth', $mail) ? $mail['smtp_auth'] : !empty($mail['username']);
        $mail['timeout'] = $mail['timeout'] ?? 30;
        $minutes = (int) ($settings['login_two_step_expiration_minutes'] ?? 5);
        (new MailService())->send(
            $mail,
            (string) $user['email'],
            'Código de verificación de e-talent',
            '<p>Hola ' . e((string) ($user['name'] ?? '')) . ',</p><p>Tu código de verificación es:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . e($issued['code']) . '</p><p>Este código vence en ' . $minutes . ' minutos y solo el último código generado es válido.</p>'
        );
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
        $isDrawer = (string) ($_GET['drawer'] ?? '') === '1'
            || (string) ($_GET['partial'] ?? '') === 'drawer';
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $loginSettings = $this->settings->loginSettings($companyId > 0 ? $companyId : null);
        $message = null;
        $recoveryEnabled = $this->passwordRecoveryEnabled($loginSettings);
        if (!$recoveryEnabled) {
            $message = 'La recuperación de clave no está disponible para esta empresa.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            if ($recoveryEnabled && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $issued = $this->passwordResets->issue($email, 60, $companyId > 0 ? $companyId : null);
                if ($issued) {
                    $this->sendResetEmail($issued);
                }
            }
            $message = 'Si el correo existe y está activo, recibirás instrucciones para restablecer la clave.';
        }
        $this->render('auth/forgot', [
            'title' => 'Recuperar clave | e-talent',
            'message' => $message,
            'isDrawer' => $isDrawer,
            'recoveryEnabled' => $recoveryEnabled,
        ], $isDrawer ? null : 'auth');
    }

    public function resetPassword(): void
    {
        if (current_user()) {
            redirect(profile_home_url());
        }
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $loginSettings = $this->settings->loginSettings($companyId > 0 ? $companyId : null);
        $recoveryEnabled = $this->passwordRecoveryEnabled($loginSettings);
        $reset = $recoveryEnabled && $token !== ''
            ? $this->passwordResets->findValid($token, $companyId > 0 ? $companyId : null)
            : null;
        $error = null;
        if (!$recoveryEnabled) {
            $error = 'La recuperación de clave no está disponible para esta empresa.';
        }
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
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $this->render('auth/reset', [
            'title' => 'Restablecer clave | e-talent',
            'token' => $token,
            'error' => $error,
            'validToken' => (bool) $reset,
            'loginSettings' => $loginSettings,
        ], 'auth');
    }

    private function passwordRecoveryEnabled(array $settings): bool
    {
        return in_array((string) ($settings['login_password_recovery_enabled'] ?? '1'), ['1', 'true', 'on', 'yes'], true);
    }

    private function sendResetEmail(array $issued): void
    {
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $mail = $this->mailSettingsForCompany($companyId);
        $link = app_absolute_url('password/reset?token=' . rawurlencode((string) $issued['token']));
        $mail['encryption'] = $mail['encryption'] ?? 'starttls';
        $mail['smtp_auth'] = array_key_exists('smtp_auth', $mail) ? $mail['smtp_auth'] : !empty($mail['username']);
        $mail['timeout'] = $mail['timeout'] ?? 30;
        try {
            (new MailService())->send(
                $mail,
                (string) $issued['email'],
                'Restablecer clave de e-talent',
                '<p>Hola ' . e((string) $issued['name']) . ',</p><p>Restablece tu clave en este enlace:</p><p><a href="' . e($link) . '">' . e($link) . '</a></p><p>El enlace expira en 60 minutos.</p>'
            );
        } catch (Throwable $exception) {
            security_log('No se pudo enviar recuperación de clave por SMTP: ' . $exception->getMessage());
        }
    }

    private function mailSettingsForCompany(int $companyId): array
    {
        $integrations = load_config('integrations');
        $globalMail = is_array($integrations['mail'] ?? null) ? $integrations['mail'] : [];

        if ($companyId <= 0) {
            return $globalMail;
        }

        $companyMail = $this->companyMail->find($companyId);
        if ($this->mailSettingsAreUsable($companyMail)) {
            return $companyMail;
        }

        return $globalMail;
    }

    private function mailSettingsAreUsable(array $mail): bool
    {
        if (empty($mail['enabled']) || trim((string) ($mail['host'] ?? '')) === '') {
            return false;
        }

        if (!filter_var((string) ($mail['from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return empty($mail['smtp_auth'])
            || (trim((string) ($mail['username'] ?? '')) !== '' && trim((string) ($mail['password'] ?? '')) !== '');
    }
}
