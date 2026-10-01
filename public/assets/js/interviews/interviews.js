(function () {
    document.querySelectorAll('[data-api-key-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.apiKeyToggle);
        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(!showing));
            button.setAttribute('aria-label', showing ? 'Mostrar API Key IA' : 'Ocultar API Key IA');
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', showing);
                icon.classList.toggle('bi-eye-slash', !showing);
            }
        });
    });

    const postForm = async (form) => {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        return response.json();
    };

    document.querySelectorAll('[data-interview-notes]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const result = await postForm(form);
                if (window.AppNotify) {
                    (result.ok ? window.AppNotify.success : window.AppNotify.error)(result.message || 'Proceso finalizado.');
                }
            } catch (error) {
                if (window.AppNotify) window.AppNotify.error('No se pudieron guardar los apuntes.');
            }
        });
    });

    document.querySelectorAll('[data-interview-evaluation]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const result = await postForm(form);
                if (window.AppNotify) {
                    (result.ok ? window.AppNotify.success : window.AppNotify.error)(result.message || 'Evaluación guardada.');
                }
            } catch (error) {
                if (window.AppNotify) window.AppNotify.error('No se pudo guardar la evaluación.');
            }
        });
    });

    document.querySelectorAll('[data-interview-finish]').forEach((button) => {
        button.addEventListener('click', async () => {
            const body = new FormData();
            body.append('csrf_token', button.dataset.csrf || '');
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.url || '', {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                const result = await response.json();
                if (window.AppNotify) {
                    (result.ok ? window.AppNotify.success : window.AppNotify.error)(result.message || 'Proceso finalizado.');
                }
                if (result.ok) window.location.reload();
            } catch (error) {
                if (window.AppNotify) window.AppNotify.error('No se pudo finalizar la entrevista.');
            } finally {
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-interview-waiting]').forEach((button) => {
        button.addEventListener('click', () => {
            const message = button.dataset.message || 'La sala aun no esta disponible.';
            if (window.AppNotify) {
                window.AppNotify.warning(message);
            } else {
                window.alert(message);
            }
        });
    });

    const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    let documentDrawer = null;

    const closeDocumentDrawer = () => {
        if (!documentDrawer) return;
        document.body.classList.remove('interview-report-drawer-open');
        documentDrawer.remove();
        documentDrawer = null;
    };

    const openDocumentDrawer = (button) => {
        closeDocumentDrawer();
        const viewUrl = button.dataset.viewUrl || button.getAttribute('href') || '';
        const downloadUrl = button.dataset.downloadUrl || viewUrl || '#';
        const label = button.dataset.title || 'Documento';
        const iframeUrl = viewUrl + (viewUrl.indexOf('?') === -1 ? '?' : '&') + 'view=1';
        documentDrawer = document.createElement('aside');
        documentDrawer.className = 'interview-report-drawer is-open';
        documentDrawer.setAttribute('data-interview-report-drawer', '');
        documentDrawer.innerHTML = '<div class="interview-report-drawer-backdrop" data-report-close></div>'
            + '<section class="interview-report-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="interview-document-drawer-title">'
            + '<header class="interview-report-drawer-header"><div><p class="text-uppercase text-primary fw-bold small mb-1">Documentos</p><h2 class="h5 mb-0" id="interview-document-drawer-title">' + escapeHtml(label) + '</h2></div><button type="button" class="btn btn-outline-secondary btn-sm" data-report-close aria-label="Cerrar"><i class="bi bi-x-lg"></i></button></header>'
            + '<div class="interview-report-drawer-body"><iframe title="Visualizador de documento" src="' + escapeHtml(iframeUrl) + '"></iframe></div>'
            + '<footer class="interview-report-drawer-footer"><a class="btn btn-outline-primary" href="' + escapeHtml(downloadUrl) + '"><i class="bi bi-download me-1"></i>Descargar</a><a class="btn btn-outline-secondary" href="' + escapeHtml(viewUrl || downloadUrl) + '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir en pestaña</a></footer>'
            + '</section>';
        document.body.appendChild(documentDrawer);
        document.body.classList.add('interview-report-drawer-open');
        documentDrawer.querySelectorAll('[data-report-close]').forEach((close) => close.addEventListener('click', closeDocumentDrawer));
        documentDrawer.querySelector('[data-report-close]')?.focus();
    };

    document.querySelectorAll('[data-interview-document-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openDocumentDrawer(button);
        });
    });

    const documentDeleteForm = document.getElementById('interview-document-delete-form');
    document.querySelectorAll('[data-interview-document-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!documentDeleteForm || !button.dataset.deleteUrl) return;
            const documentName = button.dataset.documentName || 'este documento';
            if (!window.confirm('¿Eliminar "' + documentName + '"? Esta acción no se puede deshacer.')) return;
            documentDeleteForm.action = button.dataset.deleteUrl;
            documentDeleteForm.submit();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDocumentDrawer();
    });

    document.querySelectorAll('[data-ai-test-connection]').forEach((button) => {
        button.addEventListener('click', async () => {
            const form = button.closest('form');
            const resultBox = document.querySelector('[data-ai-test-result]');
            if (!form) return;

            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Probando';
            if (resultBox) {
                resultBox.hidden = true;
                resultBox.className = 'mt-3';
                resultBox.textContent = '';
            }

            try {
                const response = await fetch(button.dataset.url || '', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                const result = await response.json();
                const ok = Boolean(result.ok);
                const message = result.message || (ok ? 'Conexion IA validada correctamente.' : 'No se pudo validar la conexion IA.');

                if (resultBox) {
                    resultBox.hidden = false;
                    resultBox.className = 'mt-3 ai-test-result ' + (ok ? 'ai-test-result-ok' : 'ai-test-result-error');
                    resultBox.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-triangle') + ' me-1"></i>' + message;
                }
                if (window.AppNotify) {
                    (ok ? window.AppNotify.success : window.AppNotify.error)(message);
                }
            } catch (error) {
                const message = 'No se pudo ejecutar la prueba de conexion IA.';
                if (resultBox) {
                    resultBox.hidden = false;
                    resultBox.className = 'mt-3 ai-test-result ai-test-result-error';
                    resultBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + message;
                }
                if (window.AppNotify) window.AppNotify.error(message);
            } finally {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        });
    });
})();
