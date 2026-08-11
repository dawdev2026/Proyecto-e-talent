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

    const documentModal = document.querySelector('#interviewDocumentModal');
    if (documentModal) {
        const frame = documentModal.querySelector('[data-interview-document-frame]');
        const title = documentModal.querySelector('[data-interview-document-title]');
        const download = documentModal.querySelector('[data-interview-document-download]');
        const openTab = documentModal.querySelector('[data-interview-document-open-tab]');

        document.querySelectorAll('[data-interview-document-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const viewUrl = button.dataset.viewUrl || '';
                const downloadUrl = button.dataset.downloadUrl || viewUrl || '#';
                const label = button.dataset.title || 'Documento';

                if (title) title.textContent = label;
                if (download) download.href = downloadUrl;
                if (openTab) openTab.href = viewUrl || downloadUrl;
                if (frame) frame.src = viewUrl || downloadUrl;
            });
        });

        documentModal.addEventListener('hidden.bs.modal', () => {
            if (frame) frame.src = 'about:blank';
        });
    }

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
