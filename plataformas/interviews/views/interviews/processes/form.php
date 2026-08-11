<link href="<?= e(url('assets/css/interviews/interviews.css')) ?>" rel="stylesheet">

<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Entrevistas seleccion</p>
        <h1 class="fw-bold mb-1"><?= $id ? 'Editar proceso' : 'Nuevo proceso' ?></h1>
        <p class="text-muted mb-0">Define agenda, moderador y postulantes del dia.</p>
    </div>
</section>

<form method="post" class="content-panel">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="form-floating">
                <input id="name" class="form-control" name="name" value="<?= e($values['name']) ?>" maxlength="180" placeholder="Nombre" required>
                <label for="name">Nombre del proceso</label>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="form-floating">
                <input id="interview_date" class="form-control" type="date" name="interview_date" value="<?= e($values['interview_date']) ?>" required>
                <label for="interview_date">Fecha</label>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="form-floating">
                <input id="starts_at" class="form-control" type="time" name="starts_at" value="<?= e(substr((string) $values['starts_at'], 0, 5)) ?>" required>
                <label for="starts_at">Hora inicio</label>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-floating">
                <input id="slot_duration_minutes" class="form-control" type="number" min="5" max="240" name="slot_duration_minutes" value="<?= (int) $values['slot_duration_minutes'] ?>" required>
                <label for="slot_duration_minutes">Duracion entrevista</label>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-floating">
                <input id="break_minutes" class="form-control" type="number" min="0" max="120" name="break_minutes" value="<?= (int) $values['break_minutes'] ?>">
                <label for="break_minutes">Descanso entre entrevistas</label>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-floating">
                <select id="status" class="form-select" name="status">
                    <?php foreach (['draft' => 'Borrador', 'scheduled' => 'Agendado', 'in_progress' => 'En curso', 'closed' => 'Cerrado', 'cancelled' => 'Cancelado'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= (string) $values['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="status">Estado</label>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="form-floating">
                <select id="moderator_user_id" class="form-select" name="moderator_user_id" required>
                    <?php foreach ($moderators as $moderator): ?>
                        <option value="<?= (int) $moderator['id'] ?>" <?= (int) $values['moderator_user_id'] === (int) $moderator['id'] ? 'selected' : '' ?>><?= e($moderator['name']) ?><?= $moderator['profile_name'] ? ' - ' . e($moderator['profile_name']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="moderator_user_id">Moderador</label>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="form-floating">
                <select id="test_process_id" class="form-select" name="test_process_id">
                    <option value="0">Sin informe asociado</option>
                    <?php foreach ($testProcesses as $testProcess): ?>
                        <option value="<?= (int) $testProcess['id'] ?>" <?= (int) ($values['test_process_id'] ?? 0) === (int) $testProcess['id'] ? 'selected' : '' ?>><?= e($testProcess['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="test_process_id">Proceso de evaluacion asociado</label>
            </div>
        </div>
        <div class="col-12">
            <?php
            $candidateCompanies = [];
            foreach ($candidates as $candidate) {
                $company = trim((string) ($candidate['company_name'] ?? ''));
                if ($company !== '') {
                    $candidateCompanies[$company] = $company;
                }
            }
            natcasesort($candidateCompanies);
            ?>
            <div data-candidate-picker>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <label class="form-label fw-semibold mb-0">Postulantes</label>
                    <span class="small text-muted" data-candidate-count>0 seleccionados</span>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label small mb-1" for="candidate_search">Buscar participante</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <input id="candidate_search" class="form-control" type="search" placeholder="Nombre, RUT o correo" autocomplete="off" data-candidate-search>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label small mb-1" for="candidate_company_filter">Empresa o unidad</label>
                        <select id="candidate_company_filter" class="form-select" data-candidate-company-filter>
                            <option value="">Todas</option>
                            <?php foreach ($candidateCompanies as $company): ?>
                                <option value="<?= e($company) ?>"><?= e($company) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end gap-2">
                        <button class="btn btn-outline-primary flex-grow-1" type="button" data-candidate-select-visible><i class="bi bi-check2-all me-1"></i>Seleccionar visibles</button>
                        <button class="btn btn-outline-secondary" type="button" data-candidate-clear aria-label="Limpiar selección" title="Limpiar selección"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <div class="candidate-picker-results border rounded p-2" data-candidate-results role="listbox" aria-label="Participantes disponibles" aria-multiselectable="true"></div>
                        <div class="small text-muted py-3 text-center d-none" data-candidate-empty>No hay participantes que coincidan con los filtros.</div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="candidate-picker-selected border rounded p-2" data-candidate-selected-list>
                            <p class="small text-muted mb-0" data-candidate-selected-empty>Aún no has seleccionado participantes.</p>
                        </div>
                    </div>
                </div>
                <select id="candidate_user_ids" class="form-select" name="candidate_user_ids[]" multiple size="10" data-candidate-source>
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                        $candidateName = trim((string) ($candidate['name'] ?? ''));
                        $candidateRut = trim((string) ($candidate['rut'] ?? ''));
                        $candidateEmail = trim((string) ($candidate['email'] ?? ''));
                        $candidateCompany = trim((string) ($candidate['company_name'] ?? ''));
                        $candidateLabel = $candidateName
                            . ($candidateRut !== '' ? ' - ' . $candidateRut : '')
                            . ($candidateCompany !== '' ? ' - ' . $candidateCompany : '');
                        ?>
                        <option
                            value="<?= (int) $candidate['id'] ?>"
                            data-candidate-name="<?= e($candidateName) ?>"
                            data-candidate-rut="<?= e($candidateRut) ?>"
                            data-candidate-email="<?= e($candidateEmail) ?>"
                            data-candidate-company="<?= e($candidateCompany) ?>"
                            <?= in_array((int) $candidate['id'], $selectedCandidates, true) ? 'selected' : '' ?>
                        ><?= e($candidateLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2">Filtra por nombre, RUT, correo o empresa. La agenda automática conserva el orden de la lista seleccionada y valida topes por moderador y postulante.</div>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i> Guardar proceso</button>
        <a class="btn btn-outline-secondary" href="<?= e(route_url('interviews')) ?>">Cancelar</a>
    </div>
</form>
<script defer src="<?= e(url('assets/js/interviews/candidate-picker.js')) ?>"></script>
