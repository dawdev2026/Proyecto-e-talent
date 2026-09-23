<?php
declare(strict_types=1);

final class ReportController extends Controller
{
    private ReportDefinitionModel $reports;
    private CompanyModel $companies;

    public function __construct(?Template $view = null, ?ReportDefinitionModel $reports = null, ?CompanyModel $companies = null)
    {
        parent::__construct($view);
        $this->reports = $reports ?: new ReportDefinitionModel();
        $this->companies = $companies ?: new CompanyModel();
    }

    public function generate(): void
    {
        $this->requireReportPermission('manage_reports', ['manage_tests']);

        $this->render('reports/generate', [
            'title' => 'Registrar Informes | e-talent',
            'currentPage' => 'reports.generate',
            'reports' => $this->reports->all(),
        ]);
    }

    public function form(): void
    {
        $this->requireReportPermission('manage_reports', ['manage_tests']);

        $id = request_secure_id('report');
        $report = $id ? $this->reports->find($id) : null;
        if ($id && !$report) {
            platform_error(404, 'Informe no encontrado.', ['chips' => ['Informes']]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id, $report);
            return;
        }

        $this->render('reports/form', [
            'title' => ($id ? 'Editar informe' : 'Nuevo informe') . ' | e-talent',
            'currentPage' => 'reports.generate',
            'id' => $id,
            'report' => $report ?: [
                'name' => '',
                'type_id' => null,
                'status' => 'draft',
                'source_filename' => '',
                'markdown_content' => '',
                'design_source_filename' => '',
                'design_markdown_content' => '',
                'design_content_sha256' => null,
                'design_interpreter_version' => '1.0',
                'version' => 1,
            ],
            'reportTypes' => array_map(function (array $type): array {
                $type['name'] = $this->repairUtf8Mojibake((string) ($type['name'] ?? ''));
                $type['description'] = $this->repairUtf8Mojibake((string) ($type['description'] ?? ''));
                return $type;
            }, $this->reports->types()),
            'reportFunctionalities' => $this->reports->functionalities(),
            'selectedFunctionalities' => $this->selectedFunctionalities($id, $report),
        ]);
    }

    public function companyAssignments(): void
    {
        $this->requireReportPermission('assign_reports_by_company', ['manage_tests']);

        $companyId = (int) ($_GET['company_id'] ?? 0);
        $companies = $this->companies->active();
        $selectedCompany = $companyId > 0 ? $this->companies->find($companyId) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $companyId = (int) ($_POST['company_id'] ?? 0);
            $selectedCompany = $companyId > 0 ? $this->companies->find($companyId) : null;
            if (!$selectedCompany) {
                flash('danger', 'Selecciona una empresa válida.');
                redirect(route_url('reports.company-assignments'));
            }

            $this->reports->syncCompanyAssignments($companyId, $_POST['report_ids'] ?? [], (int) current_user()['id']);
            flash('success', 'Asignaciones de informes actualizadas para la empresa.');
            redirect(route_url('reports.company-assignments') . '?company_id=' . $companyId);
        }

        $this->render('reports/company_assignments', [
            'title' => 'Asignar Informes por Empresas | e-talent',
            'currentPage' => 'reports.company-assignments',
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'reportAssignments' => $selectedCompany ? $this->reports->assignmentsForCompany((int) $selectedCompany['id']) : [],
        ]);
    }

    public function history(): void
    {
        $this->requireReportPermission('view_report_history', ['manage_reports']);
        $this->render('reports/history', [
            'title' => 'Historial de Informes | e-talent',
            'currentPage' => 'reports.history',
            'history' => (new ReportAuditModel())->history([
                'report_id' => (int) ($_GET['report_id'] ?? 0),
                'company_id' => (int) ($_GET['company_id'] ?? 0),
            ]),
        ]);
    }

    public function batches(): void
    {
        require_auth();
        require_permission('run_report_batches');
        $batchId = max(0, (int) ($_GET['batch_id'] ?? 0));
        $this->render('reports/batches', [
            'title' => 'Procesos de informes | e-talent',
            'currentPage' => 'reports.batches',
            'batchId' => $batchId,
            'statusUrl' => route_url('reports.batch-status'),
        ]);
    }

    public function batchRun(): void
    {
        require_auth();
        require_permission('run_report_batches');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(route_url('reports.generate'));
        }
        verify_csrf();

        $reportId = request_secure_id('report');
        $report = $reportId ? $this->reports->find($reportId) : null;
        $companyId = max(0, (int) ($_POST['company_id'] ?? 0));
        $processId = max(0, (int) ($_POST['process_id'] ?? 0));
        $userIds = is_array($_POST['user_ids'] ?? null) ? $_POST['user_ids'] : [];
        if (!$report || $companyId <= 0 || $processId <= 0 || !$userIds) {
            flash('danger', 'Selecciona empresa, proceso y al menos un postulante.');
            redirect(route_url('reports.run', $reportId));
        }

        try {
            $batchId = (new ReportBatchService())->queue($reportId, $companyId, $processId, 'pdf', (int) current_user()['id'], $userIds);
            flash('success', 'Lote enviado a procesamiento. Puedes revisar su avance y descargar el ZIP cuando finalice.');
            redirect(route_url('reports.batches') . '?batch_id=' . $batchId);
        } catch (Throwable $exception) {
            flash('danger', 'No se pudo crear el lote: ' . $exception->getMessage());
            redirect(route_url('reports.run', $reportId));
        }
    }

    public function batchStatus(): void
    {
        require_auth();
        require_permission('run_report_batches');
        $batchId = max(0, (int) ($_GET['batch_id'] ?? 0));
        $batch = $batchId > 0 ? (new ReportBatchService())->status($batchId) : null;
        if (!$batch) {
            $this->jsonResponse(['ok' => false, 'message' => 'Lote no encontrado.'], 404);
            return;
        }

        $this->jsonResponse([
            'ok' => true,
            'batch' => $batch,
            'download_url' => !empty($batch['download_token']) ? route_url('reports.batch-download') . '?token=' . rawurlencode((string) $batch['download_token']) : null,
            'message' => (string) ($batch['status'] ?? '') === 'completed' ? 'Proceso finalizado correctamente.' : 'El proceso aún está en ejecución.',
        ]);
    }

    public function batchDownload(): void
    {
        require_auth();
        require_permission('download_reports');
        $token = trim((string) ($_GET['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            platform_error(404, 'Descarga no válida o expirada.');
        }

        $batch = database('core')->fetch('SELECT id, requested_by, storage_key, zip_filename, expires_at FROM report_generation_batches WHERE download_token = ? AND status = "completed" AND expires_at > CURRENT_TIMESTAMP LIMIT 1', [$token]);
        if (!$batch || ((int) ($batch['requested_by'] ?? 0) !== (int) (current_user()['id'] ?? 0) && !has_permission('manage_reports'))) {
            platform_error(404, 'El ZIP no está disponible o ya expiró.');
        }

        $relative = (string) ($batch['storage_key'] ?? '');
        if ($relative === '' || strpos($relative, 'report_batches/') !== 0 || strpos($relative, '..') !== false) {
            platform_error(404, 'Ruta de descarga no válida.');
        }
        $path = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            platform_error(404, 'El ZIP temporal ya fue eliminado.');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename((string) ($batch['zip_filename'] ?? 'reportes.zip')) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        readfile($path);
    }

    public function run(): void
    {
        require_auth();
        $processesModel = new TestProcessModel();
        $contextProcessId = (int) ($_GET['process_id'] ?? 0);
        $canRunFromProcessRanking = $contextProcessId > 0
            && $this->processBelongsToCurrentUser($processesModel, $contextProcessId);
        if (!$canRunFromProcessRanking) {
            $this->requireReportPermission('generate_reports', ['manage_reports', 'manage_tests']);
        }
        $id = request_secure_id('report');
        $report = $id ? $this->reports->find($id) : null;
        if (!$report || (string) ($report['status'] ?? '') !== 'active' || (string) ($report['approval_status'] ?? 'published') !== 'published') {
            platform_error(404, 'Informe activo no encontrado.', ['chips' => ['Informes']]);
        }

        $companies = $this->companies->active();
        $companyId = (int) ($_GET['company_id'] ?? 0);
        $processId = (int) ($_GET['process_id'] ?? 0);
        $userId = (int) ($_GET['user_id'] ?? 0);
        $format = (string) ($_GET['format'] ?? '');
        $processes = array_values(array_filter($processesModel->allForUser(current_user()), static function (array $process) use ($companyId): bool {
            return $companyId <= 0 || (int) ($process['company_id'] ?? 0) === $companyId;
        }));
        $users = $processId > 0 ? $processesModel->processUsers($processId) : [];

        if ($format !== '' && in_array($format, ['html', 'pdf'], true)) {
            if ($companyId <= 0 || $processId <= 0 || $userId <= 0 || !$this->reports->isAssignedToCompany($id, $companyId)) {
                platform_error(422, 'Selecciona una empresa, proceso y usuario con el informe asignado.');
            }

            if ($canRunFromProcessRanking) {
                $process = $processesModel->find($processId);
                $processCompanyId = (int) ($process['company_id'] ?? 0);
                $allowedProcess = $process
                    && $this->processBelongsToCurrentUser($processesModel, $processId)
                    && $processCompanyId === $companyId;
                if (!$allowedProcess) {
                    platform_error(403, 'No tienes acceso a este proceso o empresa.');
                }

                $processUserIds = array_map(
                    static fn(array $user): int => (int) ($user['user_id'] ?? $user['id'] ?? 0),
                    $processesModel->processUsers($processId)
                );
                if (!in_array($userId, $processUserIds, true)) {
                    platform_error(403, 'El usuario no pertenece al proceso indicado.');
                }
            }

            try {
                $execution = (new ReportMarkdownInterpreter())->execute(
                    (string) $report['markdown_content'],
                    $processId,
                    $userId,
                    $companyId
                );
            if (empty($execution['available'])) {
                platform_error(422, (string) ($execution['message'] ?? 'El informe no está disponible.'));
            }

            $designContent = trim((string) ($report['design_markdown_content'] ?? ''));
            if ($designContent !== '') {
                try {
                    $design = (new ReportDesignInterpreter())->parse($designContent, false);
                    if (!empty($design['valid'])) {
                        $execution['design'] = $design;
                    } else {
                        error_log('Report design validation warnings: ' . json_encode($design['errors'] ?? [], JSON_UNESCAPED_UNICODE));
                    }
                } catch (Throwable $designException) {
                    error_log('Report design load error: ' . $designException->getMessage());
                }
            }

            $audit = new ReportAuditModel();
                $startedAt = microtime(true);
                $executionId = $audit->start([
                    'report_id' => $id,
                    'report_version_id' => ($this->reports->currentVersion($id)['id'] ?? null),
                    'company_id' => $companyId,
                    'process_id' => $processId,
                    'user_id' => $userId,
                    'executed_by' => (int) current_user()['id'],
                    'format' => $format,
                    'request_hash' => hash('sha256', $id . '|' . $companyId . '|' . $processId . '|' . $userId . '|' . $format),
                ]);
                $renderer = new ReportDocumentRenderer();
                if ($format === 'html') {
                    header('Content-Type: text/html; charset=UTF-8');
                    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
                    $output = $renderer->renderHtml($execution);
                    $audit->complete($executionId, $startedAt, true);
                    echo $output;
                    return;
                }

                $pdf = $renderer->renderPdf($execution);
                $filename = ReportDefinitionModel::slugify((string) $report['name']) . '_' . date('Ymd_His') . '.pdf';
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($pdf));
                header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
                $audit->complete($executionId, $startedAt, true);
                echo $pdf;
                return;
            } catch (InvalidArgumentException $exception) {
                platform_error(422, 'La configuración del informe no es válida: ' . $exception->getMessage());
            } catch (Throwable $exception) {
                error_log('Report generation error: ' . $exception->getMessage());
                platform_error(500, 'No se pudo generar el informe.');
            }
        }

        $this->render('reports/run', [
            'title' => 'Generar informe | e-talent',
            'currentPage' => 'reports.generate',
            'report' => $report,
            'companies' => $companies,
            'processes' => $processes,
            'users' => $users,
            'selectedCompanyId' => $companyId,
            'selectedProcessId' => $processId,
            'selectedUserId' => $userId,
        ]);
    }

    private function processBelongsToCurrentUser(TestProcessModel $processesModel, int $processId): bool
    {
        return $processesModel->can(current_user() ?: [], $processId, 'view_process_ranking');
    }

    public function approve(): void
    {
        $this->requireReportPermission('approve_reports');
        verify_csrf();
        $id = request_secure_id('report');
        $report = $id ? $this->reports->find($id) : null;
        if (!$report) {
            platform_error(404, 'Informe no encontrado.');
        }
        database('core')->execute(
            "UPDATE report_definitions SET approval_status = 'published', status = 'active', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ?",
            [(int) current_user()['id'], (int) current_user()['id'], $id]
        );
        flash('success', 'Informe aprobado y publicado.');
        redirect(route_url('reports.generate'));
    }

    public function delete(): void
    {
        $this->requireReportPermission('manage_reports', ['manage_tests']);
        verify_csrf();

        $id = request_secure_id('report');
        $report = $id ? $this->reports->find($id) : null;
        if (!$report) {
            platform_error(404, 'Informe no encontrado.');
        }

        try {
            $this->reports->softDelete($id, (int) current_user()['id']);
            flash('success', 'Informe eliminado correctamente. Se conservaron sus versiones e historial.');
        } catch (PDOException $exception) {
            error_log('Report definition delete error: ' . $exception->getMessage());
            flash('danger', 'No se pudo eliminar el informe.');
        }

        redirect(route_url('reports.generate'));
    }

    private function save(?int $id, ?array $report): void
    {
        verify_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        // Los informes nuevos quedan publicados al superar la validación.
        // El estado manual se conserva únicamente para ediciones y borradores avanzados.
        $status = $id ? (string) ($_POST['status'] ?? ($report['status'] ?? 'active')) : 'active';
        if ($name === '' || mb_strlen($name) > 160) {
            flash('danger', 'El nombre del informe es obligatorio y no puede superar 160 caracteres.');
            return;
        }
        $typeId = (int) ($_POST['type_id'] ?? ($report['type_id'] ?? 0));
        if ($typeId <= 0) {
            flash('danger', 'Debes seleccionar el tipo de informe.');
            return;
        }
        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            $status = 'draft';
        }

        $markdown = null;
        $filename = (string) ($report['source_filename'] ?? '');
        $file = $_FILES['markdown_file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $originalName = (string) ($file['name'] ?? '');
            if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'md') {
                flash('danger', 'La configuración debe ser un archivo con extensión .md.');
                return;
            }
            if (!is_uploaded_file((string) ($file['tmp_name'] ?? '')) || (int) ($file['size'] ?? 0) > 1024 * 1024) {
                flash('danger', 'El archivo Markdown no es válido o supera el límite de 1 MB.');
                return;
            }
            $markdown = file_get_contents((string) $file['tmp_name']);
            if (!is_string($markdown) || $markdown === '' || !mb_check_encoding($markdown, 'UTF-8')) {
                flash('danger', 'El archivo Markdown debe contener texto UTF-8 válido.');
                return;
            }
            $filename = mb_substr(basename($originalName), 0, 255);
        } elseif (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            flash('danger', 'No se pudo recibir el archivo Markdown.');
            return;
        } elseif (!$id) {
            flash('danger', 'Debes cargar un archivo Markdown para crear el informe.');
            return;
        } else {
            $markdown = (string) ($report['markdown_content'] ?? '');
        }

        $designMarkdown = null;
        $designFilename = (string) ($report['design_source_filename'] ?? '');
        $designFile = $_FILES['design_markdown_file'] ?? [];
        if (($designFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $originalDesignName = (string) ($designFile['name'] ?? '');
            if (strtolower(pathinfo($originalDesignName, PATHINFO_EXTENSION)) !== 'md') {
                flash('danger', 'El MD gráfico debe ser un archivo con extensión .md.');
                return;
            }
            if (!is_uploaded_file((string) ($designFile['tmp_name'] ?? '')) || (int) ($designFile['size'] ?? 0) > 1024 * 1024) {
                flash('danger', 'El MD gráfico no es válido o supera el límite de 1 MB.');
                return;
            }
            $designMarkdown = file_get_contents((string) $designFile['tmp_name']);
            if (!is_string($designMarkdown) || $designMarkdown === '' || !mb_check_encoding($designMarkdown, 'UTF-8')) {
                flash('danger', 'El MD gráfico debe contener texto UTF-8 válido.');
                return;
            }
            $designFilename = mb_substr(basename($originalDesignName), 0, 255);
        } elseif (($designFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            flash('danger', 'No se pudo recibir el MD gráfico.');
            return;
        } elseif ($id) {
            $designMarkdown = (string) ($report['design_markdown_content'] ?? '');
        }

        try {
            $functionalSpec = (new ReportMarkdownInterpreter())->parse($markdown);
            if (trim((string) $designMarkdown) !== '') {
                (new ReportDesignInterpreter())->parse((string) $designMarkdown);
            }
        } catch (InvalidArgumentException $exception) {
            flash('danger', 'La configuración Markdown no es válida: ' . $exception->getMessage());
            return;
        }

        $functionalities = array_values(array_unique(array_filter(array_map(
            'strval',
            is_array($_POST['functionalities'] ?? null) ? $_POST['functionalities'] : []
        ), static fn(string $key): bool => $key !== '')));
        if (!$functionalities) {
            $declared = $functionalSpec['metadata']['functionalities'] ?? [];
            $functionalities = is_array($declared) ? array_values(array_filter(array_map('strval', $declared))) : [];
        }
        $availableFunctionalityKeys = array_map(
            static fn(array $functionality): string => (string) $functionality['functionality_key'],
            $this->reports->functionalities()
        );
        if (!$functionalities || array_diff($functionalities, $availableFunctionalityKeys)) {
            flash('danger', 'Selecciona al menos una funcionalidad válida para el informe.');
            return;
        }

        $slug = ReportDefinitionModel::slugify($name);
        $slugCandidate = $slug;
        $suffix = 2;
        while ($duplicate = $this->reports->findBySlug($slugCandidate, $id)) {
            $slugCandidate = $slug . '-' . $suffix++;
        }

        $data = [
            'name' => $name,
            'type_id' => $typeId,
            'slug' => $slugCandidate,
            'markdown_content' => $markdown,
            'source_filename' => $filename ?: 'configuracion.md',
            'content_sha256' => hash('sha256', $markdown),
            'design_markdown_content' => trim((string) $designMarkdown) !== '' ? $designMarkdown : null,
            'design_source_filename' => $designFilename ?: null,
            'design_content_sha256' => trim((string) $designMarkdown) !== '' ? hash('sha256', (string) $designMarkdown) : null,
            'design_interpreter_version' => trim((string) $designMarkdown) !== '' ? '1.0' : null,
            'status' => $status,
            'approval_status' => $status === 'active' ? 'published' : 'draft',
            'interpreter_version' => '1.0',
            'user_id' => (int) current_user()['id'],
        ];

        try {
            if ($id) {
                $this->reports->update($id, $data);
                $versionId = $this->reports->createVersion($id, $data, ((int) ($report['version'] ?? 0)) + 1, 'Actualización de configuración Markdown');
                $this->reports->syncFunctionalities($id, $functionalities, (int) current_user()['id'], $versionId);
                flash('success', 'Informe actualizado correctamente.');
            } else {
                $newId = $this->reports->create($data);
                $versionId = $this->reports->createVersion($newId, $data, 1, 'Versión inicial');
                $this->reports->syncFunctionalities($newId, $functionalities, (int) current_user()['id'], $versionId);
                flash('success', 'Informe creado y publicado correctamente. Ahora puedes asignarlo a las empresas.');
            }
            redirect(route_url('reports.generate'));
        } catch (PDOException $exception) {
            error_log('Report definition save error: ' . $exception->getMessage());
            flash('danger', 'No se pudo guardar el informe. Revisa los datos e inténtalo nuevamente.');
        }
    }

    private function requireReportPermission(string $permission, array $alternatives = []): void
    {
        require_auth();
        $allowed = has_permission($permission);
        foreach ($alternatives as $alternative) {
            $allowed = $allowed || has_permission($alternative);
        }
        if (!$allowed) {
            platform_error(403, 'No tienes permisos para acceder a la sub-plataforma Informes.', [
                'detailRows' => ['Permiso requerido' => $permission],
            ]);
        }
    }

    private function selectedFunctionalities(?int $id, ?array $report): array
    {
        if ($id && $this->reports->functionalityKeysForReport($id)) {
            return $this->reports->functionalityKeysForReport($id);
        }
        if (!$report || trim((string) ($report['markdown_content'] ?? '')) === '') {
            return [];
        }
        try {
            $spec = (new ReportMarkdownInterpreter())->parse((string) $report['markdown_content']);
            $declared = $spec['metadata']['functionalities'] ?? [];
            return is_array($declared) ? array_values(array_filter(array_map('strval', $declared))) : [];
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * Corrige registros antiguos cargados como UTF-8 interpretado como Latin-1.
     * Solo se aplica cuando aparecen marcadores inequívocos de mojibake, para
     * no alterar texto UTF-8 válido.
     */
    private function repairUtf8Mojibake(string $value): string
    {
        if ($value === '' || !preg_match('/(?:Ã.|Â.|â€)/u', $value)) {
            return $value;
        }

        $repaired = iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
        return $repaired === false ? $value : $repaired;
    }
}
