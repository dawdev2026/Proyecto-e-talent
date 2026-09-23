<?php
declare(strict_types=1);

final class FacialRecognitionController extends Controller
{
    private FacialRecognitionModel $model;
    private FacialRecognitionService $service;
    private UserModel $users;
    private string $requestId;

    public function __construct(?string $action = null, ?Template $view = null, ?FacialRecognitionModel $model = null, ?FacialRecognitionService $service = null, ?UserModel $users = null)
    {
        parent::__construct($view);
        $this->model = $model ?: new FacialRecognitionModel();
        $this->service = $service ?: new FacialRecognitionService();
        $this->users = $users ?: new UserModel();
        $this->requestId = bin2hex(random_bytes(16));
    }

    public function enroll(): void
    {
        $currentUser = current_user() ?: [];
        $isSelfEnrollment = (string) ($currentUser['role'] ?? '') === 'usuario';
        if (!$isSelfEnrollment) require_permission('manage_facial_recognition');
        $companyId = $isSelfEnrollment
            ? (int) ($currentUser['company_id'] ?? 0)
            : (function_exists('current_company_context_id') ? current_company_context_id() : 0);
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $users = $isSelfEnrollment ? array_values(array_filter([$this->users->findAuthenticatedUserForSelfEnrollment($currentUserId)])) : $this->users->all();
        $selectedUser = $isSelfEnrollment ? ($users[0] ?? null) : null;
        $message = null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $userId = $isSelfEnrollment ? $currentUserId : (int) ($_POST['user_id'] ?? 0);
            $selectedUser = $isSelfEnrollment
                ? $this->users->findAuthenticatedUserForSelfEnrollment($userId)
                : $this->users->findUser($userId);
            $consent = isset($_POST['consent']) && $_POST['consent'] === '1';
            $file = $_FILES['face_image'] ?? [];
            if (!$this->service->isConfigured()) {
                $error = 'El servicio de reconocimiento facial está desactivado.';
            } elseif ($isSelfEnrollment && $companyId <= 0) {
                $error = 'Tu usuario no tiene una empresa asociada. Contacta al administrador de tu empresa.';
            } elseif (!$selectedUser || ($companyId > 0 && (int) ($selectedUser['company_id'] ?? 0) !== $companyId)) {
                $error = 'Selecciona un usuario válido dentro de tu empresa.';
            } elseif (!$consent) {
                $error = 'El consentimiento es obligatorio para enrolar una identidad facial.';
            } elseif (($uploadError = $this->validateFaceUpload($file)) !== null) {
                $error = $uploadError;
            } else {
                try {
                    $challenge = $this->service->consumeChallenge((string) ($_POST['face_challenge'] ?? ''), (string) ($_POST['face_start_key'] ?? ''), (int) current_user()['id'], $userId, 'enroll');
                    $embedding = $this->service->normalizeEmbedding((string) ($_POST['face_embedding'] ?? ''));
                    $liveness = (float) ($_POST['face_liveness'] ?? 0);
                    if ($liveness < $this->service->livenessThreshold()) throw new RuntimeException('La prueba de vida no fue aprobada.');
                    $enrollmentId = $this->model->enroll($userId, $companyId > 0 ? $companyId : null, $embedding, $this->service->modelVersion(), 'facial-v2', (int) current_user()['id']);
                    // La llave de término se valida y registra en el backend;
                    // nunca se expone en la interfaz del usuario.
                    $this->service->finishKey($challenge, true, 1.0, $liveness);
                    $message = 'Usuario enrolado correctamente.';
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                    $this->recordFailureAttempt([
                        'company_id' => $companyId > 0 ? $companyId : null,
                        'user_id' => $userId,
                        'context' => 'enroll',
                        'result' => 'error',
                        'reason' => $this->facialFailureReason($exception),
                    ]);
                }
            }
        }

        $this->render('facial_recognition/enroll', [
            'title' => 'Enrolar identidad facial | e-talent',
            'currentPage' => 'facial-recognition.enroll',
            'users' => $users,
            'selectedUser' => $selectedUser,
            'isSelfEnrollment' => $isSelfEnrollment,
            'selfUserId' => $isSelfEnrollment ? $currentUserId : 0,
            'message' => $message,
            'error' => $error,
            'useSelect2' => true,
            'serviceSettings' => $this->service->settings(),
        ]);
    }

    public function enrolledUsers(): void
    {
        require_permission('view_company_client_portal');
        $user = current_user() ?: [];
        $companyId = (int) ($user['company_id'] ?? 0);
        if ((string) ($user['role'] ?? '') !== 'company_admin' || $companyId <= 0) {
            platform_error(403, 'Tu perfil no tiene una empresa asociada.');
        }
        $this->render('facial_recognition/enrolled', [
            'title' => 'Reconocimientos enrolados | e-talent',
            'currentPage' => 'facial-recognition.enrolled',
            'enrolledUsers' => $this->model->enrolledUsersForCompany($companyId),
        ]);
    }

    public function validateIdentity(): void
    {
        require_permission('validate_facial_identity');
        $companyId = function_exists('current_company_context_id') ? current_company_context_id() : 0;
        $users = $this->users->all();
        $result = null;
        $message = null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $userId = (int) ($_POST['user_id'] ?? 0);
            $user = $this->users->findUser($userId);
            $enrollment = $user ? $this->model->enrollmentForUser($userId, $companyId > 0 ? $companyId : null) : null;
            $file = $_FILES['face_image'] ?? [];
            if (!$this->service->isConfigured()) {
                $error = 'El servicio de reconocimiento facial está desactivado.';
            } elseif (!$user || !$enrollment || ($companyId > 0 && (int) ($user['company_id'] ?? 0) !== $companyId)) {
                $error = 'El usuario no tiene una identidad facial enrolada o no pertenece a la empresa.';
            } elseif (($uploadError = $this->validateFaceUpload($file)) !== null) {
                $error = $uploadError;
            } else {
                try {
                    $this->service->assertEnrollmentCompatible($enrollment);
                    $challenge = $this->service->consumeChallenge((string) ($_POST['face_challenge'] ?? ''), (string) ($_POST['face_start_key'] ?? ''), (int) current_user()['id'], $userId, 'validate');
                    $embedding = $this->service->normalizeEmbedding((string) ($_POST['face_embedding'] ?? ''));
                    $reference = json_decode((string) ($enrollment['face_embedding'] ?? ''), true);
                    if (!is_array($reference)) throw new RuntimeException('El usuario no tiene una huella facial válida.');
                    $similarity = $this->service->descriptorSimilarity($reference, $embedding);
                    $liveness = (float) ($_POST['face_liveness'] ?? 0);
                    $valid = $liveness >= $this->service->livenessThreshold() && $similarity >= $this->service->similarityThreshold();
                    $result = $valid;
                    $this->model->attempt([
                        'enrollment_id' => (int) $enrollment['id'],
                        'company_id' => $companyId > 0 ? $companyId : null,
                        'user_id' => $userId,
                        'result' => $valid ? 'verified' : 'not_verified',
                        'similarity' => $similarity,
                        'liveness' => $liveness,
                        'provider' => 'human',
                        'reason' => $valid ? 'threshold_passed' : 'threshold_not_reached',
                        'request_id' => $this->requestId,
                    ]);
                    // La llave se genera para la trazabilidad del backend, pero
                    // no se muestra ni se entrega al navegador.
                    $this->service->finishKey($challenge, $valid, $similarity, $liveness);
                    $message = $valid ? 'Identidad facial validada correctamente.' : ($liveness < $this->service->livenessThreshold()
                        ? 'No se pudo confirmar la prueba de vida. Mantén el rostro visible y sigue la instrucción de movimiento.'
                        : 'La captura no coincide con la referencia enrolada. Comprueba la iluminación y que el rostro esté despejado; si el problema persiste, solicita un nuevo enrolamiento.');
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                    $this->recordFailureAttempt([
                        'company_id' => $companyId > 0 ? $companyId : null,
                        'user_id' => $userId ?: null,
                        'result' => 'error',
                        'reason' => $this->facialFailureReason($exception),
                    ]);
                }
            }
        }

        $this->render('facial_recognition/validate', [
            'title' => 'Validar identidad | e-talent',
            'currentPage' => 'facial-recognition.validate',
            'users' => $users,
            'result' => $result,
            'message' => $message,
            'error' => $error,
            'useSelect2' => true,
            'serviceSettings' => $this->service->settings(),
        ]);
    }

    /** Participant-bound facial gate immediately before opening an assessment. */
    public function assessmentEntry(): void
    {
        require_auth();
        $jsonRequest = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $actor = current_user() ?: [];
        $submittedReturn = (string) ($_GET['return'] ?? $_POST['return_to'] ?? '');
        $returnParts = parse_url($submittedReturn);
        if (!empty($returnParts['host']) && strcasecmp((string) $returnParts['host'], (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
            platform_error(400, 'La actividad solicitada no es válida.');
        }
        $returnTo = (string) ($returnParts['path'] ?? '');
        if (isset($returnParts['query'])) $returnTo .= '?' . $returnParts['query'];
        if ($returnTo === '' || !$this->isAssessmentReturnPath($returnTo)) {
            platform_error(400, 'La actividad solicitada no es válida.');
        }
        $userId = (int) ($actor['id'] ?? 0);
        $companyId = (int) ($actor['company_id'] ?? 0);
        $enrollment = $userId > 0 && $companyId > 0 ? $this->model->enrollmentForUser($userId, $companyId) : null;
        $serviceSettings = $this->service->settings();
        $error = (!$enrollment || (string) ($enrollment['status'] ?? '') !== 'active')
            ? 'Primero debes enrolar tu identidad facial para poder responder.'
            : null;
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $file = $_FILES['face_image'] ?? [];
            if (empty($serviceSettings['enabled'])) {
                $error = 'El servicio de reconocimiento facial está desactivado.';
            } elseif (!$enrollment || (string) ($enrollment['status'] ?? '') !== 'active') {
                $error = 'Primero debes enrolar tu identidad facial desde Reconocimiento Facial.';
            } elseif (($uploadError = $this->validateFaceUpload($file)) !== null) {
                $error = $uploadError;
            } else {
                try {
                    $this->service->assertEnrollmentCompatible($enrollment);
                    $challenge = $this->service->consumeChallenge((string) ($_POST['face_challenge'] ?? ''), (string) ($_POST['face_start_key'] ?? ''), $userId, $userId, 'assessment_entry');
                    $embedding = $this->service->normalizeEmbedding((string) ($_POST['face_embedding'] ?? ''));
                    $reference = json_decode((string) ($enrollment['face_embedding'] ?? ''), true);
                    if (!is_array($reference)) throw new RuntimeException('Tu referencia facial no es válida. Contacta al administrador.');
                    $similarity = $this->service->descriptorSimilarity($reference, $embedding);
                    $liveness = (float) ($_POST['face_liveness'] ?? 0);
                    $verified = $liveness >= $this->service->livenessThreshold() && $similarity >= $this->service->similarityThreshold();
                    $this->model->attempt([
                        'enrollment_id' => (int) $enrollment['id'], 'company_id' => $companyId, 'user_id' => $userId,
                        'context' => 'assessment_entry', 'result' => $verified ? 'verified' : 'not_verified',
                        'similarity' => $similarity, 'liveness' => $liveness, 'provider' => 'human',
                        'reason' => $verified ? 'threshold_passed' : 'threshold_not_reached',
                        'request_id' => $this->requestId,
                    ]);
                    $this->service->finishKey($challenge, $verified, $similarity, $liveness);
                    $result = $verified;
                    if ($verified) {
                        $target = $this->assessmentTarget($returnTo);
                        if (!$target) throw new RuntimeException('No se pudo vincular la identidad a la actividad solicitada.');
                        $_SESSION['assessment_face_authorizations'][$target['key']] = [
                            'user_id' => $userId, 'company_id' => $companyId, 'issued_at' => time(),
                            'activity_type' => $target['type'], 'activity_id' => $target['id'], 'process_id' => $target['process_id'],
                        ];
                        if ($jsonRequest) {
                            $this->jsonResponse(['ok' => true, 'message' => 'Identidad verificada.']);
                            return;
                        }
                        redirect($returnTo);
                    }
                    $error = $liveness < $this->service->livenessThreshold()
                        ? 'No se pudo confirmar la prueba de vida. Mantén el rostro visible y sigue la instrucción de movimiento.'
                        : 'La captura no coincide con la referencia facial. Comprueba que el rostro esté despejado y haya iluminación frontal; si persiste, solicita un nuevo enrolamiento.';
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                    $this->recordFailureAttempt([
                        'company_id' => $companyId ?: null,
                        'user_id' => $userId ?: null,
                        'context' => 'assessment_entry',
                        'result' => 'error',
                        'reason' => $this->facialFailureReason($exception),
                    ]);
                }
            }
        }
        if ($jsonRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->jsonResponse(['ok' => false, 'message' => $error ?: 'No fue posible validar la identidad.'], 422);
            return;
        }
        $this->render('facial_recognition/validate', [
            'title' => 'Verificar identidad | e-talent', 'currentPage' => 'facial-recognition.validate',
            'users' => [], 'result' => $result, 'message' => null, 'error' => $error,
            'assessmentEntry' => true, 'assessmentReturnTo' => $returnTo, 'assessmentUser' => $actor, 'enrollment' => $enrollment,
            'useSelect2' => false, 'serviceSettings' => $serviceSettings,
        ]);
    }

    private function isAssessmentReturnPath(string $path): bool
    {
        $parts = parse_url($path);
        $pathOnly = (string) ($parts['path'] ?? '');
        if ($pathOnly === '' || $pathOnly[0] !== '/' || strpos($pathOnly, '//') === 0) return false;
        return preg_match('~/(?:tests/session/[A-Za-z0-9_-]+|evaluaciones-encuestas/formularios/[A-Za-z0-9_-]+/take)/?$~', $pathOnly) === 1;
    }

    /** Store only a safe category; exception text may contain implementation details. */
    private function facialFailureReason(Throwable $exception): string
    {
        $message = function_exists('mb_strtolower')
            ? mb_strtolower($exception->getMessage(), 'UTF-8')
            : strtolower($exception->getMessage());
        $categories = [
            'enrollment_version_mismatch' => ['método anterior', 'nuevo enrolamiento'],
            'challenge_expired_or_used' => ['expiró o ya fue utilizado'],
            'challenge_missing' => ['no existe o ya expiró'],
            'challenge_invalid' => ['llave de inicio', 'no corresponde a esta operación'],
            'embedding_invalid' => ['huella facial', 'embedding'],
            'liveness_failed' => ['prueba de vida no fue aprobada', 'prueba de vida no aprobada'],
            'face_engine_unavailable' => ['reconocimiento facial no está disponible', 'no se pudo inicializar el reconocimiento facial'],
        ];
        foreach ($categories as $category => $fragments) {
            foreach ($fragments as $fragment) {
                if (strpos($message, $fragment) !== false) return $category;
            }
        }
        return 'verification_error';
    }

    private function recordFailureAttempt(array $data): void
    {
        try {
            $this->model->attempt($data + ['request_id' => $this->requestId]);
        } catch (Throwable $loggingError) {
            // No biometría ni mensajes técnicos se envían al PHP error log.
            error_log('[facial-recognition] No fue posible registrar una categoría de error.');
        }
    }

    private function assessmentTarget(string $returnTo): ?array
    {
        $parts = parse_url($returnTo);
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('~/tests/session/([A-Za-z0-9_-]+)/?$~', $path, $match)) {
            $id = secure_url_id($match[1], 'test_session');
            return $id > 0 ? ['key' => 'test:' . $id, 'type' => 'test_session', 'id' => $id, 'process_id' => 0] : null;
        }
        if (preg_match('~/evaluaciones-encuestas/formularios/([A-Za-z0-9_-]+)/take/?$~', $path, $match)) {
            $id = secure_url_id($match[1], 'evaluation_survey_form');
            $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            $processId = max(0, (int) ($query['process_id'] ?? 0));
            return $id > 0 ? ['key' => 'evaluation:' . $id . ':' . $processId, 'type' => 'evaluation', 'id' => $id, 'process_id' => $processId] : null;
        }
        return null;
    }

    /**
     * Valida el archivo temporal antes de completar la operación.
     * La imagen se mantiene como respaldo visual; la decisión usa la huella
     * Human.js y la prueba de vida producidas en el navegador.
     */
    private function validateFaceUpload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Carga una imagen facial válida.';
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return 'La imagen no puede superar los 5 MB.';
        }
        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            return 'La imagen recibida no es válida.';
        }
        $imageInfo = @getimagesize($path);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!is_array($imageInfo) || !in_array((string) ($imageInfo['mime'] ?? ''), $allowedMimeTypes, true)) {
            return 'El formato debe ser JPG, PNG o WEBP.';
        }
        if ((int) ($imageInfo[0] ?? 0) < 640 || (int) ($imageInfo[1] ?? 0) < 480) {
            return 'La imagen debe tener al menos 640×480 píxeles.';
        }
        return null;
    }

    public function startChallenge(): void
    {
        $purpose = (string) ($_POST['purpose'] ?? 'validate');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { $this->jsonResponse(['ok' => false, 'message' => 'Método no permitido.'], 405); return; }
        $currentUser = current_user() ?: [];
        if (!$this->service->isConfigured()) { $this->jsonResponse(['ok' => false, 'message' => 'El servicio de reconocimiento facial está desactivado.'], 503); return; }
        $isSelfEnrollment = $purpose === 'enroll' && (string) ($currentUser['role'] ?? '') === 'usuario';
        $isAssessmentEntry = $purpose === 'assessment_entry' && in_array((string) ($currentUser['role'] ?? ''), ['usuario', 'company_admin'], true);
        if (!$isSelfEnrollment && !$isAssessmentEntry) require_permission($purpose === 'enroll' ? 'manage_facial_recognition' : 'validate_facial_identity');
        verify_csrf();
        $targetUserId = ($isSelfEnrollment || $isAssessmentEntry) ? (int) ($currentUser['id'] ?? 0) : (int) ($_POST['user_id'] ?? 0);
        if (!in_array($purpose, ['enroll', 'validate', 'assessment_entry'], true) || $targetUserId < 1) { $this->jsonResponse(['ok' => false, 'message' => 'Usuario u operación inválida.'], 422); return; }
        $companyId = $isAssessmentEntry ? (int) ($currentUser['company_id'] ?? 0) : (function_exists('current_company_context_id') ? current_company_context_id() : 0);
        $user = ($isSelfEnrollment || $isAssessmentEntry)
            ? $this->users->findAuthenticatedUserForSelfEnrollment($targetUserId)
            : $this->users->findUser($targetUserId);
        if ($isSelfEnrollment && $companyId <= 0) $companyId = (int) ($currentUser['company_id'] ?? 0);
        if (!$user || ($isSelfEnrollment && $companyId <= 0) || ($companyId > 0 && (int) ($user['company_id'] ?? 0) !== $companyId)) { $this->jsonResponse(['ok' => false, 'message' => 'Usuario fuera del contexto permitido o sin empresa asociada.'], 403); return; }
        $assessmentEnrollment = $isAssessmentEntry ? $this->model->enrollmentForUser($targetUserId, $companyId) : null;
        if ($isAssessmentEntry && (!$assessmentEnrollment || (string) ($assessmentEnrollment['status'] ?? '') !== 'active')) { $this->jsonResponse(['ok' => false, 'message' => 'Primero enrola tu identidad facial.'], 409); return; }
        $this->jsonResponse(['ok' => true] + $this->service->createChallenge((int) current_user()['id'], $targetUserId, $purpose));
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
