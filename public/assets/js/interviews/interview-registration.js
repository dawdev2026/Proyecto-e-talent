(function () {
    'use strict';

    function init() {
        var form = document.querySelector('.interview-registration-form');
        if (!form) return;
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-interview-step]'));
        var indicators = Array.prototype.slice.call(form.querySelectorAll('[data-step-indicator]'));
        var current = 1;
        var candidateSearch = form.querySelector('[data-candidate-search]');
        var candidateSelect = form.querySelector('[data-candidate-select]');
        var assessmentPanel = form.querySelector('[data-assessment-processes]');
        var error = form.querySelector('[data-interview-step-error]');
        var preparationStatus = form.querySelector('[data-preparation-status]');
        var preparationResult = form.querySelector('[data-preparation-result]');
        var preparationApproval = form.querySelector('[data-preparation-approval]');
        var preparationCheck = form.querySelector('[data-preparation-approval-check]');
        var preparationApproved = form.querySelector('[data-preparation-approved]');
        var forcePreparation = form.querySelector('[data-force-preparation]');
        var preparationButton = form.querySelector('[data-generate-preparation]');
        var assessmentUrl = form.getAttribute('data-assessment-preview-url') || '';
        var profileUrl = form.getAttribute('data-job-profile-parse-url') || '';
        var preparationUrl = form.getAttribute('data-preparation-preview-url') || '';
        var profileTimer = null;
        var loadedCandidate = '';
        var preparationLoaded = false;
        var existingPreparation = null;
        try { existingPreparation = JSON.parse(form.getAttribute('data-existing-preparation') || 'null'); } catch (exception) { existingPreparation = null; }

        function normalize(value) { return String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
        function escapeHtml(value) { return String(value || '').replace(/[&<>'"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[c]; }); }
        function setError(message) { error.textContent = message || ''; error.hidden = !message; }
        function csrf() { var field = form.querySelector('input[name="csrf_token"]'); return field ? field.value : ''; }

        function filterCandidates() {
            if (!candidateSearch || !candidateSelect) return;
            var query = normalize(candidateSearch.value), visible = 0, only = '';
            Array.prototype.forEach.call(candidateSelect.options, function (option, index) {
                if (index === 0) return;
                var match = !query || normalize(option.getAttribute('data-search-text') || option.textContent).indexOf(query) !== -1;
                option.hidden = !match;
                if (match) { visible += 1; only = option.value; }
            });
            if (query && visible === 1 && !candidateSelect.value) candidateSelect.value = only;
            var status = form.querySelector('[data-candidate-search-status]');
            if (status) status.textContent = visible + ' postulante(s) coinciden con la búsqueda.';
        }

        function renderAssessment(data) {
            if (!assessmentPanel) return;
            var processes = Array.isArray(data.processes) ? data.processes : [];
            if (!processes.length) { assessmentPanel.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>No se encontraron evaluaciones o tests finalizados para este postulante.'; return; }
            var recommended = Number(data.recommended_process_id || 0);
            assessmentPanel.innerHTML = '<div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-file-earmark-bar-graph text-success"></i><strong>Antecedentes encontrados</strong></div>' + processes.map(function (item) {
                var selected = Number(item.id) === recommended;
                return '<div class="interview-assessment-item ' + (selected ? 'is-recommended' : '') + '"><div><strong>' + escapeHtml(item.name || 'Evaluación') + '</strong><small class="d-block text-muted">' + Number(item.completed_count || 0) + ' resultado(s) finalizado(s)' + (item.last_completed_at ? ' · ' + escapeHtml(item.last_completed_at) : '') + '</small></div>' + (selected ? '<span class="badge text-bg-primary">Recomendado</span>' : '') + '</div>';
            }).join('') + '<small class="d-block mt-2 text-muted">Se asociará automáticamente el proceso finalizado más reciente y su informe.</small>';
            form.querySelector('[data-test-process-id]').value = String(recommended || processes[0].id || 0);
        }

        function renderReport(data) {
            if (!assessmentPanel || !data || !data.available) return;
            var summary = data.structured_summary || {};
            var classification = summary.classification || {};
            var text = classification.label || classification.final_score !== undefined ? '<div class="small mt-2">' + (classification.label ? 'Clasificación: <strong>' + escapeHtml(classification.label) + '</strong>' : '') + (classification.final_score !== undefined ? ' · Puntaje: <strong>' + escapeHtml(classification.final_score) + '</strong>' : '') + '</div>' : '';
            var report = '<div class="border rounded p-3 mt-3 bg-white"><div class="d-flex align-items-start gap-2"><i class="bi bi-file-earmark-check text-success fs-5"></i><div><strong>Informe disponible</strong><div class="small text-muted">Se utilizará como antecedente contextual, no como decisión automática.</div>' + text + (data.report_url ? '<button type="button" class="btn btn-sm btn-outline-primary mt-2" data-report-url="' + escapeHtml(data.report_url) + '"><i class="bi bi-file-earmark-pdf me-1"></i>Ver informe</button>' : '') + '</div></div></div>';
            assessmentPanel.insertAdjacentHTML('beforeend', report);
            var button = assessmentPanel.querySelector('[data-report-url]');
            if (button) button.addEventListener('click', function () {
                var url = button.getAttribute('data-report-url') || '';
                var panel = document.createElement('aside');
                panel.className = 'interview-report-drawer is-open'; panel.setAttribute('data-interview-report-drawer', '');
                panel.innerHTML = '<div class="interview-report-drawer-backdrop" data-report-close></div><section class="interview-report-drawer-panel" role="dialog" aria-modal="true"><header class="interview-report-drawer-header"><div><p class="text-uppercase text-primary fw-bold small mb-1">Informe del postulante</p><h2 class="h5 mb-0">Previsualización PDF</h2></div><button type="button" class="btn btn-outline-secondary btn-sm" data-report-close aria-label="Cerrar"><i class="bi bi-x-lg"></i></button></header><div class="interview-report-drawer-body"><iframe title="Informe PDF del postulante" src="' + escapeHtml(url + (url.indexOf('?') === -1 ? '?' : '&') + 'view=1') + '"></iframe></div></section>';
                document.body.appendChild(panel); panel.querySelectorAll('[data-report-close]').forEach(function (close) { close.addEventListener('click', function () { panel.remove(); }); });
            });
        }

        function loadAssessment() {
            var candidateId = candidateSelect ? String(candidateSelect.value || '') : '';
            if (!candidateId || !assessmentUrl) { if (assessmentPanel) assessmentPanel.innerHTML = '<i class="bi bi-info-circle me-2"></i>Selecciona un postulante para consultar sus evaluaciones e informes disponibles.'; return Promise.resolve(); }
            if (loadedCandidate === candidateId) return Promise.resolve();
            loadedCandidate = candidateId;
            if (assessmentPanel) assessmentPanel.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Consultando resultados del postulante...';
            return fetch(assessmentUrl + '?candidate_user_id=' + encodeURIComponent(candidateId) + '&test_process_id=0', {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'No se pudo consultar los antecedentes.'); return data; }); })
                .then(function (data) {
                    renderAssessment(data);
                    var recommended = Number(data.recommended_process_id || 0);
                    if (!recommended) return;
                    return fetch(assessmentUrl + '?candidate_user_id=' + encodeURIComponent(candidateId) + '&test_process_id=' + recommended, {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                        .then(function (response) { return response.json(); })
                        .then(renderReport);
                })
                .catch(function (exception) { loadedCandidate = ''; if (assessmentPanel) assessmentPanel.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>' + escapeHtml(exception.message || 'No se pudo consultar los antecedentes.'); });
        }

        function applyProfile(data) {
            var fields = {'#job_profile_title': data.title, '#job_profile_description': data.description, '#technical_requirements': data.technical_requirements, '#behavioral_requirements': data.behavioral_requirements};
            Object.keys(fields).forEach(function (selector) { var field = form.querySelector(selector); if (field && fields[selector]) field.value = fields[selector]; });
            (Array.isArray(data.evaluation_criteria) ? data.evaluation_criteria : []).slice(0, 6).forEach(function (criterion, index) {
                if (!criterion || typeof criterion !== 'object') return;
                var name = form.querySelector('[name="evaluation_criteria[' + index + '][name]"]');
                var type = form.querySelector('[name="evaluation_criteria[' + index + '][type]"]');
                var weight = form.querySelector('[name="evaluation_criteria[' + index + '][weight]"]');
                if (name && criterion.name) name.value = criterion.name;
                if (type && criterion.type) type.value = criterion.type;
                if (weight && criterion.weight !== undefined) weight.value = criterion.weight;
            });
        }

        function parseProfile() {
            var file = form.querySelector('#document_job_profile'), text = form.querySelector('#document_job_profile_text');
            if (!file || !text || (!file.files.length && !text.value.trim()) || !profileUrl) return;
            var body = new FormData(); body.append('csrf_token', csrf()); body.append('job_profile_text', text.value); if (file.files.length) body.append('job_profile_file', file.files[0]);
            var status = form.querySelector('[data-job-profile-status]');
            if (status) status.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Analizando el perfil de cargo...';
            fetch(profileUrl, {method: 'POST', body: body, credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'No se pudo analizar el perfil.'); return data; }); })
                .then(function (result) { applyProfile(result.data || {}); if (status) status.innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>' + escapeHtml(result.message || 'Perfil leído y datos completados.'); })
                .catch(function (exception) { if (status) status.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-2"></i>' + escapeHtml(exception.message); });
        }

        function preparationBody() {
            var body = new FormData(); body.append('csrf_token', csrf()); body.append('candidate_user_id', candidateSelect.value); body.append('test_process_id', form.querySelector('[data-test-process-id]').value || '0');
            ['job_profile_title', 'job_profile_description', 'technical_requirements', 'behavioral_requirements'].forEach(function (name) { var field = form.querySelector('[name="' + name + '"]'); body.append(name, field ? field.value : ''); });
            form.querySelectorAll('[name^="evaluation_criteria["], [name^="interview_document_text["]').forEach(function (field) { body.append(field.name, field.value); });
            form.querySelectorAll('input[type="file"][name^="interview_documents["]').forEach(function (field) { if (field.files.length) body.append(field.name, field.files[0]); });
            return body;
        }

        function renderPreparation(result) {
            var brief = result.brief || {};
            form.querySelector('[data-preparation-summary]').textContent = brief.summary || 'No se generó un resumen adicional.';
            form.querySelector('[data-preparation-competencies]').innerHTML = (brief.competencies || []).map(function (item) { return '<div class="border rounded p-2 mb-2"><strong>' + escapeHtml(item.name) + '</strong><small class="d-block text-muted">' + escapeHtml(item.relevance || item.evidence_to_validate || 'Validar durante la entrevista') + '</small></div>'; }).join('') || '<span class="text-muted">No se detectaron competencias específicas.</span>';
            form.querySelector('[data-preparation-questions]').innerHTML = (brief.questions || []).map(function (item) { return '<li class="mb-3"><strong>' + escapeHtml(item.question) + '</strong>' + (item.expected_evidence ? '<small class="d-block text-muted mt-1">Evidencia esperada: ' + escapeHtml(item.expected_evidence) + '</small>' : '') + '</li>'; }).join('') || '<li class="text-muted">No se generaron preguntas.</li>';
            preparationResult.hidden = false; preparationApproval.hidden = false; preparationLoaded = true;
            if (preparationStatus) preparationStatus.innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>' + escapeHtml(result.ai_used ? 'Preparación generada por IA. Revisa y aprueba para continuar.' : 'Preparación base generada. Revisa y aprueba para continuar.');
        }

        function generatePreparation() {
            if (!candidateSelect.value) { setError('Selecciona un postulante antes de preparar la entrevista.'); return; }
            preparationButton.disabled = true; preparationButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Analizando antecedentes...';
            fetch(preparationUrl, {method: 'POST', body: preparationBody(), credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'No se pudo preparar la entrevista.'); return data; }); })
                .then(renderPreparation)
                .catch(function (exception) { if (preparationStatus) preparationStatus.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-2"></i>' + escapeHtml(exception.message); })
                .finally(function () { preparationButton.disabled = false; preparationButton.innerHTML = '<i class="bi bi-stars me-1"></i> Regenerar preparación'; });
        }

        function validateStep() {
            var invalid = null;
            Array.prototype.forEach.call(steps[current - 1].querySelectorAll('input,select,textarea'), function (field) { if (!field.disabled && field.type !== 'hidden' && !field.checkValidity() && !invalid) invalid = field; });
            if (invalid) { invalid.reportValidity(); return false; }
            if (current === 1 && !candidateSelect.value) { setError('Selecciona un postulante para continuar.'); return false; }
            if (current === 2 && !form.querySelector('#job_profile_title').value.trim() && !form.querySelector('#document_job_profile_text').value.trim() && !form.querySelector('#document_job_profile').files.length) { setError('Carga o pega el perfil del cargo para que la IA pueda preparar la entrevista.'); return false; }
            if (current === 3 && (!preparationLoaded || !preparationCheck.checked)) { setError('Genera la preparación y apruébala antes de pasar a la agenda.'); return false; }
            setError(''); return true;
        }

        function render() {
            steps.forEach(function (step, index) { step.hidden = index + 1 !== current; });
            indicators.forEach(function (indicator, index) { indicator.classList.toggle('active', index + 1 === current); indicator.classList.toggle('completed', index + 1 < current); });
            form.querySelector('[data-interview-prev]').hidden = current === 1; form.querySelector('[data-interview-next]').hidden = current === steps.length; form.querySelector('[data-interview-submit]').hidden = current !== steps.length;
            window.scrollTo({top: form.offsetTop - 24, behavior: 'smooth'});
            if (current === 2) loadAssessment();
            if (current === 3 && !preparationLoaded) generatePreparation();
        }

        if (existingPreparation && (existingPreparation.summary || (existingPreparation.competencies || []).length || (existingPreparation.questions || []).length)) {
            renderPreparation({brief: existingPreparation, ai_used: false});
            if (preparationStatus) preparationStatus.innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>Preparación existente cargada. Se reutilizará mientras no cambien los antecedentes.';
        }

        candidateSearch && candidateSearch.addEventListener('input', filterCandidates);
        candidateSelect && candidateSelect.addEventListener('change', function () { loadedCandidate = ''; loadAssessment(); });
        var profileFile = form.querySelector('#document_job_profile'), profileText = form.querySelector('#document_job_profile_text');
        profileFile && profileFile.addEventListener('change', parseProfile);
        profileText && profileText.addEventListener('input', function () { window.clearTimeout(profileTimer); profileTimer = window.setTimeout(parseProfile, 700); });
        preparationButton && preparationButton.addEventListener('click', function () {
            if (forcePreparation) forcePreparation.value = '1';
            generatePreparation();
        });
        preparationCheck && preparationCheck.addEventListener('change', function () { preparationApproved.value = preparationCheck.checked ? '1' : '0'; });
        form.querySelector('[data-interview-next]').addEventListener('click', function () { if (validateStep() && current < steps.length) { current += 1; render(); } });
        form.querySelector('[data-interview-prev]').addEventListener('click', function () { if (current > 1) { current -= 1; setError(''); render(); } });
        form.addEventListener('submit', function (event) { if (!validateStep() || preparationApproved.value !== '1') event.preventDefault(); });
        filterCandidates(); loadAssessment(); render();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
