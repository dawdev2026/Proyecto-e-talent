$(function () {
    function updateAppViewportHeight() {
        var viewport = window.visualViewport;
        var height = viewport && viewport.height ? viewport.height : window.innerHeight;
        if (height && document.documentElement) {
            document.documentElement.style.setProperty('--app-viewport-height', height + 'px');
        }
    }

    updateAppViewportHeight();
    $(window).on('resize orientationchange', updateAppViewportHeight);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateAppViewportHeight);
        window.visualViewport.addEventListener('scroll', updateAppViewportHeight);
    }

    function applyTheme(theme) {
        $('html').attr('data-theme', theme);
        $('html').attr('data-coreui-theme', theme);
        $('.theme-toggle i').attr('class', theme === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun');
        $('.theme-toggle-label').text(theme === 'dark' ? 'Claro' : 'Oscuro');
    }

    var savedTheme = localStorage.getItem('corePlatformTheme');
    var preferredTheme = 'light';
    applyTheme(savedTheme || preferredTheme);

    $(document).on('click', '.theme-toggle', function () {
        var nextTheme = $('html').attr('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('corePlatformTheme', nextTheme);
        applyTheme(nextTheme);
    });

    // Bootstrap posiciona los dropdowns dentro de la tabla. En tablas
    // responsivas ese ancestro puede recortar el menu; lo elevamos al body
    // mientras esta abierto y lo devolvemos a su lugar al cerrarlo.
    $(document).on('show.bs.dropdown', '.app-table [data-bs-toggle="dropdown"]', function () {
        var toggle = this;
        var menu = toggle.parentElement ? toggle.parentElement.querySelector('.dropdown-menu') : null;
        if (!menu || menu.classList.contains('app-table-dropdown-floating')) {
            return;
        }

        menu.dataset.appDropdownParent = menu.parentElement ? '1' : '';
        menu._appDropdownParent = menu.parentElement;
        menu._appDropdownNextSibling = menu.nextSibling;
        menu.classList.add('app-table-dropdown-floating');
        document.body.appendChild(menu);
    });

    $(document).on('shown.bs.dropdown', '.app-table [data-bs-toggle="dropdown"]', function () {
        var toggle = this;
        var menu = document.querySelector('.app-table-dropdown-floating.show');
        if (!menu) {
            return;
        }

        var rect = toggle.getBoundingClientRect();
        var viewportPadding = 12;
        var menuWidth = Math.min(menu.offsetWidth || 288, window.innerWidth - (viewportPadding * 2));
        var left = Math.max(viewportPadding, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - viewportPadding));
        var top = rect.bottom + 4;
        var menuHeight = menu.offsetHeight || 160;
        if (top + menuHeight > window.innerHeight - viewportPadding && rect.top - menuHeight - 4 >= viewportPadding) {
            top = rect.top - menuHeight - 4;
        }
        menu.style.left = left + 'px';
        menu.style.top = Math.max(viewportPadding, top) + 'px';
    });

    $(document).on('hidden.bs.dropdown', '.app-table [data-bs-toggle="dropdown"]', function () {
        var menu = document.querySelector('.app-table-dropdown-floating');
        if (!menu) {
            return;
        }

        var parent = menu._appDropdownParent;
        var nextSibling = menu._appDropdownNextSibling;
        menu.classList.remove('app-table-dropdown-floating');
        menu.style.left = '';
        menu.style.top = '';
        if (parent) {
            if (nextSibling && nextSibling.parentNode === parent) {
                parent.insertBefore(menu, nextSibling);
            } else {
                parent.appendChild(menu);
            }
        }
        delete menu._appDropdownParent;
        delete menu._appDropdownNextSibling;
    });

    $(window).on('resize scroll', function () {
        var menu = document.querySelector('.app-table-dropdown-floating.show');
        if (menu) {
            var toggle = document.querySelector('.app-table [data-bs-toggle="dropdown"][aria-expanded="true"]');
            if (toggle) {
                $(toggle).trigger('shown.bs.dropdown');
            }
        }
    });

    $(document).on('click', '[data-password-toggle]', function () {
        var button = $(this);
        var input = document.getElementById(button.data('password-toggle'));

        if (!input) {
            return;
        }

        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.attr('aria-pressed', String(!showing));
        button.attr('aria-label', showing ? 'Mostrar clave' : 'Ocultar clave');
        button.find('i').toggleClass('bi-eye', showing).toggleClass('bi-eye-slash', !showing);
    });

    var appNotifier = window.Notyf ? new Notyf({
        duration: 4300,
        ripple: false,
        dismissible: true,
        position: {
            x: 'right',
            y: 'top'
        },
        types: [
            {
                type: 'success',
                background: '#188038',
                icon: {
                    className: 'bi bi-check-circle-fill',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'error',
                background: '#d93025',
                icon: {
                    className: 'bi bi-exclamation-octagon-fill',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'warning',
                background: '#b06000',
                icon: {
                    className: 'bi bi-exclamation-triangle-fill',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'info',
                background: '#1a73e8',
                icon: {
                    className: 'bi bi-info-circle-fill',
                    tagName: 'i',
                    color: '#fff'
                }
            }
        ]
    }) : null;

    function currentFullscreenElement() {
        return document.fullscreenElement
            || document.webkitFullscreenElement
            || document.mozFullScreenElement
            || document.msFullscreenElement
            || null;
    }

    function messageTargetElement() {
        var fullscreenElement = currentFullscreenElement();

        if (fullscreenElement && document.documentElement.contains(fullscreenElement)) {
            return fullscreenElement;
        }

        return document.body;
    }

    function swalBaseOptions() {
        return {
            target: messageTargetElement(),
            background: cssVar('--card-content-bg', '#ffffff'),
            color: cssVar('--app-text', '#111827'),
            iconColor: cssVar('--button-bg', '#C3A80B'),
            customClass: {
                popup: 'app-swal-popup',
                title: 'app-swal-title',
                htmlContainer: 'app-swal-text',
                actions: 'app-swal-actions',
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-outline-secondary'
            }
        };
    }

    function normalizeNotificationType(type) {
        var cleanType = String(type || 'info').replace('alert-', '');
        var map = {
            danger: 'error',
            error: 'error',
            success: 'success',
            warning: 'warning',
            warn: 'warning',
            info: 'info',
            primary: 'info',
            secondary: 'info'
        };

        return map[cleanType] || 'info';
    }

    function showNotification(type, message) {
        var text = String(message || '').replace(/\s+/g, ' ').trim();
        if (!text) {
            return;
        }

        if (String(type || '').toLowerCase() === 'pdf_import_error' && window.Swal) {
            Swal.fire($.extend({}, swalBaseOptions(), {
                title: 'PDF no procesable',
                text: text,
                icon: 'error',
                confirmButtonText: 'Entendido',
                buttonsStyling: false
            }));
            return;
        }

        var normalizedType = normalizeNotificationType(type);

        if (appNotifier && messageTargetElement() === document.body) {
            appNotifier.open({
                type: normalizedType,
                message: text
            });
            return;
        }

        var $fallback = $('<div/>', {
            class: 'alert alert-' + (normalizedType === 'error' ? 'danger' : normalizedType) + ' app-notification-fallback',
            text: text
        });
        $(messageTargetElement()).append($fallback);
        window.setTimeout(function () {
            $fallback.fadeOut(180, function () {
                $fallback.remove();
            });
        }, 4300);
    }

    function processingOverlay() {
        return $('[data-app-processing-overlay]').first();
    }

    function setAppProcessingProgress(percent) {
        var $overlay = processingOverlay();
        if (!$overlay.length) {
            return;
        }

        var $progress = $overlay.find('[data-app-processing-progress]');
        var $percent = $overlay.find('[data-app-processing-percent]');
        var normalized = typeof percent === 'number' && isFinite(percent)
            ? Math.max(0, Math.min(100, Math.round(percent)))
            : null;

        if (normalized === null) {
            $overlay.removeClass('has-progress');
            $overlay.find('.app-processing-progress')
                .removeAttr('aria-valuenow')
                .attr('aria-valuetext', 'Procesando');
            $progress.css('width', '');
            $percent.prop('hidden', true).text('');
            return;
        }

        $overlay.addClass('has-progress');
        $overlay.find('.app-processing-progress')
            .attr('aria-valuenow', normalized)
            .attr('aria-valuetext', normalized + '%');
        $progress.css('width', normalized + '%');
        $percent.prop('hidden', false).text(normalized + '%');
    }

    function showAppProcessing(message, detail) {
        var $overlay = processingOverlay();
        if (!$overlay.length) {
            return;
        }

        var $detail = $overlay.find('[data-app-processing-detail]');
        $overlay.find('strong').text(message || 'Procesando Informacion...');
        if (detail) {
            $detail.text(detail).prop('hidden', false);
        } else {
            $detail.text('').prop('hidden', true);
        }
        setAppProcessingProgress(null);
        $overlay.prop('hidden', false).addClass('is-visible');
        $('body').addClass('app-processing-active');
    }

    function hideAppProcessing() {
        var $overlay = processingOverlay();
        if (!$overlay.length) {
            return;
        }

        setAppProcessingProgress(null);
        $overlay.find('[data-app-processing-detail]').text('').prop('hidden', true);
        $overlay.prop('hidden', true).removeClass('is-visible');
        $('body').removeClass('app-processing-active');
    }

    function preserveSubmitterValue($form, submitter) {
        if (!submitter || !submitter.name) {
            return;
        }

        $form.find('input[data-app-processing-submitter="' + submitter.name.replace(/"/g, '\\"') + '"]').remove();
        $('<input>', {
            type: 'hidden',
            name: submitter.name,
            value: submitter.value || '',
            'data-app-processing-submitter': submitter.name
        }).appendTo($form);
    }

    function markFormProcessing($form, submitter) {
        if (!$form.length) {
            return false;
        }

        if ($form.data('app-processing')) {
            return true;
        }

        $form.data('app-processing', true);
        preserveSubmitterValue($form, submitter);
        $form.find('button[type="submit"], input[type="submit"]').prop('disabled', true);
        showAppProcessing($form.attr('data-processing-message') || 'Procesando Informacion...');
        return false;
    }

    function filenameFromContentDisposition(headerValue) {
        var header = String(headerValue || '');
        var utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i);
        if (utfMatch && utfMatch[1]) {
            try {
                return decodeURIComponent(utfMatch[1].replace(/["']/g, ''));
            } catch (error) {
                return utfMatch[1].replace(/["']/g, '');
            }
        }

        var match = header.match(/filename="?([^";]+)"?/i);
        return match && match[1] ? match[1] : '';
    }

    function downloadBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename || 'informes.zip';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
            link.remove();
        }, 1000);
    }

    function downloadWithProcessing(link) {
        var $link = $(link);
        if ($link.data('app-download-processing')) {
            return;
        }

        $link.data('app-download-processing', true).addClass('disabled').attr('aria-disabled', 'true');
        showAppProcessing(
            $link.attr('data-processing-message') || 'Generando descarga...',
            $link.attr('data-processing-detail') || ''
        );

        fetch(link.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('No se pudo generar la descarga.');
            }

            var contentType = String(response.headers.get('Content-Type') || '').toLowerCase();
            if (contentType.indexOf('application/json') !== -1) {
                return response.json().then(function (result) {
                    if (!result || !result.ok || !result.download_url) {
                        throw new Error(result && result.message ? result.message : 'No se pudo preparar el archivo ZIP.');
                    }
                    var downloadButton = document.createElement('a');
                    downloadButton.href = result.download_url;
                    downloadButton.className = 'btn btn-success ms-2';
                    downloadButton.innerHTML = '<i class="bi bi-download me-1"></i> Descargar ZIP';
                    downloadButton.setAttribute('data-download-processing', '1');
                    downloadButton.setAttribute('data-processing-message', 'Descargando ZIP...');
                    downloadButton.setAttribute('data-processing-detail', 'Los archivos temporales se eliminarán automáticamente a medianoche.');
                    $(link).after(downloadButton);
                    showNotification('success', (result.message || 'Proceso finalizado correctamente.') + ' ' + (result.detail || ''));
                    hideAppProcessing();
                    return null;
                });
            }
            if (contentType && contentType.indexOf('application/zip') === -1 && contentType.indexOf('application/octet-stream') === -1) {
                throw new Error('No se recibio un archivo ZIP valido. Vuelve a iniciar sesion e intenta nuevamente.');
            }

            var filename = filenameFromContentDisposition(response.headers.get('Content-Disposition'));
            var length = parseInt(response.headers.get('Content-Length') || '0', 10);

            if (!response.body || !window.ReadableStream || !length) {
                return response.blob().then(function (blob) {
                    setAppProcessingProgress(100);
                    return {blob: blob, filename: filename};
                });
            }

            var reader = response.body.getReader();
            var chunks = [];
            var received = 0;

            function readChunk() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        setAppProcessingProgress(100);
                        return {blob: new Blob(chunks), filename: filename};
                    }

                    chunks.push(result.value);
                    received += result.value.length;
                    setAppProcessingProgress((received / length) * 100);
                    return readChunk();
                });
            }

            return readChunk();
        }).then(function (download) {
            if (!download) {
                return;
            }
            downloadBlob(download.blob, download.filename);
            window.setTimeout(hideAppProcessing, 500);
        }).catch(function (error) {
            hideAppProcessing();
            showNotification('error', error && error.message ? error.message : 'No se pudo generar la descarga.');
        }).finally(function () {
            $link.data('app-download-processing', false).removeClass('disabled').removeAttr('aria-disabled');
        });
    }

    function clearFormProcessing($form) {
        if ($form && $form.length) {
            $form.data('app-processing', false);
            $form.find('button[type="submit"], input[type="submit"]').prop('disabled', false);
            $form.find('input[data-app-processing-submitter]').remove();
        }

        hideAppProcessing();
    }

    window.AppProcessing = {
        show: showAppProcessing,
        hide: hideAppProcessing,
        progress: setAppProcessingProgress,
        markForm: markFormProcessing,
        clearForm: clearFormProcessing
    };

    function initNotifications(scope) {
        var $scope = scope ? $(scope) : $(document);

        $scope.find('[data-app-message]').each(function () {
            var $message = $(this);
            if ($message.data('app-message-processed')) {
                return;
            }

            $message.data('app-message-processed', true);
            showNotification($message.data('type'), $message.data('message') || $message.text());
            $message.remove();
        });

        // Los alertas ocultos y los mensajes internos de un componente no son
        // notificaciones globales. En particular, el gate audiovisual mantiene
        // estados pendientes/ocultos que deben mostrarse solo cuando corresponda.
        $scope.find('.alert').not('[data-app-message-processed], [data-inline-alert], .d-none, [hidden]').each(function () {
            var $alert = $(this);
            var type = 'info';
            var classes = String($alert.attr('class') || '').split(/\s+/);

            classes.forEach(function (className) {
                if (className.indexOf('alert-') === 0) {
                    type = className.replace('alert-', '');
                }
            });

            var text = $alert.clone().find('.btn-close').remove().end().text();
            $alert.attr('data-app-message-processed', '1');
            showNotification(type, text);
            $alert.remove();
        });
    }

    function initBootstrapPopovers(scope) {
        if (!window.bootstrap || !window.bootstrap.Popover) {
            return;
        }

        var $scope = scope ? $(scope) : $(document);
        $scope.find('[data-bs-toggle="popover"]').each(function () {
            if (!window.bootstrap.Popover.getInstance(this)) {
                new window.bootstrap.Popover(this);
            }
        });
    }

    window.AppNotify = {
        show: showNotification,
        success: function (message) {
            showNotification('success', message);
        },
        error: function (message) {
            showNotification('error', message);
        },
        warning: function (message) {
            showNotification('warning', message);
        },
        info: function (message) {
            showNotification('info', message);
        }
    };

    initNotifications(document);
    initBootstrapPopovers(document);

    function initRutInputs(scope) {
        if (!$.fn.rut) {
            return;
        }

        var $scope = scope ? $(scope) : $(document);
        $scope.find('[data-rut-input]').rut();
    }

    initRutInputs(document);

    function syncUserProfileOptions(scope) {
        var $scope = scope ? $(scope) : $(document);
        $scope.find('select[name="profile_id"]').each(function () {
            var $profile = $(this);
            var $form = $profile.closest('form');
            var $role = $form.find('select[name="role"]');
            var $company = $form.find('select[name="company_id"]');
            var role = $profile.find('option:selected').data('base-role') || $role.val() || 'usuario';

            if ($role.length && role) {
                $role.val(role);
            }

            if ($company.length) {
                $company.prop('required', role === 'usuario');
            }
        });
    }

    syncUserProfileOptions(document);

    $(document).on('change', 'select[name="profile_id"]', function () {
        syncUserProfileOptions($(this).closest('form'));
    });

    function slugifyEvaluationCode(value) {
        var text = String(value || '');
        if (text.normalize) {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        return text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_')
            .slice(0, 60);
    }

    $('[data-test-instrument-form] input[name="code"]').each(function () {
        $(this).data('code-manual-edit', $.trim($(this).val()) !== '');
    });

    $(document).on('input', '[data-test-instrument-form] input[name="code"]', function () {
        $(this).data('code-manual-edit', $.trim($(this).val()) !== '');
    });

    $(document).on('input', '[data-test-instrument-form] input[name="name"]', function () {
        var $form = $(this).closest('form');
        var $code = $form.find('input[name="code"]');

        if (!$code.length || $code.data('code-manual-edit')) {
            return;
        }

        $code.val(slugifyEvaluationCode($(this).val()));
    });

    var controlModeHelp = {
        off: {
            title: 'Sin registro',
            content: 'No se almacenan eventos de actividad de la evaluacion. Se mantiene solo el control tecnico de presencia necesario para el funcionamiento de la sesion.'
        },
        activity: {
            title: 'Registro de actividad',
            content: 'Registra apertura, inicio, reapertura, dispositivo, respuestas guardadas o modificadas, borradores, bloques, pausas, envio, expiracion, pestana visible u oculta, perdida o recuperacion de foco e inactividad.'
        },
        supervised: {
            title: 'Rendicion supervisada',
            content: 'Incluye todo el registro de actividad y agrega pantalla completa, entradas y salidas de pantalla completa, intentos de copiar, cortar, pegar, imprimir, abrir el menu contextual, arrastrar contenido y senales de riesgo. Si pantalla completa no funciona, la persona puede continuar con la advertencia registrada.'
        },
        supervised_audio_visual: {
            title: 'Rendicion supervisada + control audiovisual',
            content: 'Incluye lo anterior y solicita camara, microfono y captura de pantalla o evidencia Canvas para la rendicion. Registra interrupciones, posibles multiples voces y fallas de carga. La accion ante cada incidencia usa la configuracion vigente de la evaluacion.'
        }
    };

    $(document).on('change', '[data-control-mode-select]', function () {
        var mode = controlModeHelp[this.value] || controlModeHelp.off;
        var $container = $(this).closest('[data-test-instrument-form], form');
        var $help = $container.find('[data-control-mode-help]').first();
        $container.find('[data-audio-visual-rules]').toggleClass('d-none', this.value !== 'supervised_audio_visual');
        if (!$help.length) {
            return;
        }

        $help.attr('data-bs-title', mode.title).attr('data-bs-content', mode.content);
        if (window.bootstrap && window.bootstrap.Popover) {
            var popover = window.bootstrap.Popover.getInstance($help[0]);
            if (popover) {
                popover.dispose();
            }
            new window.bootstrap.Popover($help[0]);
        }
    });

    $('[data-control-mode-select]').each(function () {
        $(this).trigger('change');
    });

    function ageFromBirthDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) {
            return '';
        }

        var parts = value.split('-').map(function (part) {
            return parseInt(part, 10);
        });
        var birth = new Date(parts[0], parts[1] - 1, parts[2]);
        if (birth.getFullYear() !== parts[0] || birth.getMonth() !== parts[1] - 1 || birth.getDate() !== parts[2]) {
            return '';
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (birth > today) {
            return '';
        }

        var age = today.getFullYear() - birth.getFullYear();
        var monthDelta = today.getMonth() - birth.getMonth();
        if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        return String(age);
    }

    function syncAgeFromBirthDate(input) {
        var $birthDate = $(input);
        var name = String($birthDate.attr('name') || '');
        var ageName = name.indexOf('[birth_date]') !== -1 ? name.replace('[birth_date]', '[age]') : 'age';
        var $scope = $birthDate.closest('form').length ? $birthDate.closest('form') : $(document);
        var $age = $scope.find('[name="' + ageName.replace(/"/g, '\\"') + '"]');
        var age = ageFromBirthDate($birthDate.val());

        if ($age.length && age !== '') {
            $age.val(age).trigger('change');
        }
    }

    $(document).on('change input', 'input[name="birth_date"], input[name$="[birth_date]"]', function () {
        syncAgeFromBirthDate(this);
    });

    $(document).on('submit', '.needs-validation', function (event) {
        if (window.tinymce && typeof tinymce.triggerSave === 'function') {
            tinymce.triggerSave();
        }

        var submitter = event.originalEvent && event.originalEvent.submitter;
        if (submitter && submitter.name === 'test_action' && submitter.value === 'save_exit') {
            return;
        }

        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();

            var $form = $(this);
            if ($form.closest('.test-taking-shell').length) {
                showNotification('warning', 'Debes responder todas las preguntas requeridas antes de continuar.');

                var $firstInvalidQuestion = $form.find('.test-question-card').filter(function () {
                    return $(this).find('input, textarea, select').filter(function () {
                        return !this.checkValidity();
                    }).length > 0;
                }).first();

                if ($firstInvalidQuestion.length) {
                    $firstInvalidQuestion.addClass('is-incomplete');
                    $('html, body').animate({
                        scrollTop: Math.max(0, $firstInvalidQuestion.offset().top - 120)
                    }, 220);
                }
            }
        }

        $(this).addClass('was-validated');
    });

    $(document).on('submit', 'form', function (event) {
        var form = this;
        var $form = $(form);
        var method = String($form.attr('method') || 'get').toLowerCase();
        var submitter = event.originalEvent && event.originalEvent.submitter;

        if (event.isDefaultPrevented() || method === 'get' || $form.is('[data-no-processing]')) {
            return;
        }

        if ($form.is('[data-confirm-submit]') && !$form.data('confirm-submitted')) {
            return;
        }

        if ($form.attr('data-block-ajax') === '1') {
            return;
        }

        if ($form.hasClass('needs-validation') && !(submitter && submitter.name === 'test_action' && submitter.value === 'save_exit') && !form.checkValidity()) {
            return;
        }

        if (markFormProcessing($form, submitter)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });

    $(document).on('click', 'a[data-download-processing]', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || this.target === '_blank') {
            return;
        }

        event.preventDefault();
        downloadWithProcessing(this);
    });

    $('select[name="status"], select[name="priority"], select[name="category"]').on('change', function () {
        var $form = $(this).closest('form');
        if ($form.attr('method') === 'get') {
            $form.trigger('submit');
        }
    });

    function addPageBackButtons() {
        if (!window.AppBackUrl) {
            return;
        }

        $('.page-header').each(function () {
            var $header = $(this);
            if ($header.find('[data-page-back]').length) {
                return;
            }
            var backUrl = $header.data('page-back-url') || window.AppBackUrl;

            var $button = $('<a/>', {
                class: 'btn btn-sm btn-back page-back-button',
                href: backUrl,
                'data-page-back': '1',
                html: '<i class="bi bi-arrow-left me-1"></i> Volver'
            });

            var $last = $header.children().last();
            if ($last.hasClass('page-header-actions') || $last.hasClass('d-flex')) {
                $last.prepend($button);
            } else if ($last.is('a, button')) {
                var $actions = $('<div class="d-flex flex-wrap gap-2 page-header-actions"></div>');
                $last.before($actions);
                $actions.append($button).append($last);
            } else {
                $header.append($('<div class="d-flex flex-wrap gap-2 page-header-actions"></div>').append($button));
            }
        });
    }

    addPageBackButtons();

    function initDataTables(scope) {
        if (!$.fn.DataTable) {
            return;
        }

        var $scope = scope ? $(scope) : $(document);

        $scope.find('.app-data-table').each(function () {
            var $table = $(this);
            if ($.fn.DataTable.isDataTable(this)) {
                return;
            }

            var exportTitle = $table.data('export-title') || document.title || 'Reporte';
            var pageLength = parseInt($table.data('page-length'), 10) || 10;
            var excelUrl = $table.data('excel-url') || '';
            var serverUrl = $table.data('server-url') || '';
            var scrollX = $table.data('scroll-x') === true || $table.data('scroll-x') === 'true';
            var paging = !($table.data('paging') === false || $table.data('paging') === 'false');
            var searching = !($table.data('searching') === false || $table.data('searching') === 'false');
            var buttons = [];

            if (!serverUrl && excelUrl) {
                buttons.push({
                    text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
                    className: 'btn btn-sm btn-success',
                    action: function () {
                        window.location.href = excelUrl;
                    }
                });
            } else if (!serverUrl && $table.data('export-excel') !== false) {
                buttons.push({
                    extend: 'excelHtml5',
                    text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
                    className: 'btn btn-sm btn-success',
                    title: exportTitle,
                    exportOptions: {
                        columns: ':visible:not(.no-export)',
                        format: {
                            body: function (data) {
                                return $('<div>').html(data).text().replace(/\s+/g, ' ').trim();
                            }
                        }
                    }
                });
            }

            if (!serverUrl && $table.data('export-pdf') !== false) {
                buttons.push({
                    extend: 'pdfHtml5',
                    text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                    className: 'btn btn-sm btn-danger',
                    title: exportTitle,
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':visible:not(.no-export)',
                        format: {
                            body: function (data) {
                                return $('<div>').html(data).text().replace(/\s+/g, ' ').trim();
                            }
                        }
                    }
                });
            }

            var options = {
                autoWidth: false,
                deferRender: true,
                order: [],
                pageLength: pageLength,
                lengthMenu: serverUrl
                    ? [[10, 25, 50, 100], [10, 25, 50, 100]]
                    : [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Todos']],
                dom: "<'data-table-toolbar'<'data-table-actions'B><'data-table-search'f>>" +
                    "<'table-responsive'tr>" +
                    "<'data-table-footer'<'data-table-info'i><'data-table-length'l><'data-table-pagination'p>>",
                buttons: buttons,
                scrollX: scrollX,
                paging: paging,
                searching: searching,
                info: paging,
                lengthChange: paging,
                columnDefs: [
                    {
                        targets: 'no-sort',
                        orderable: false
                    }
                ],
                language: {
                    decimal: ',',
                    thousands: '.',
                    emptyTable: 'No hay informacion para mostrar.',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 registros',
                    infoFiltered: '(filtrado de _MAX_ registros totales)',
                    lengthMenu: 'Mostrar _MENU_',
                    loadingRecords: 'Cargando...',
                    processing: 'Procesando...',
                    search: 'Buscar:',
                    zeroRecords: 'No se encontraron registros.',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    },
                    buttons: {
                        excel: 'Excel',
                        pdf: 'PDF'
                    }
                }
            };

            if (serverUrl) {
                options.processing = true;
                options.serverSide = true;
                options.ajax = {
                    url: serverUrl,
                    type: 'GET'
                };
            }

            $table.DataTable(options);
        });
    }

    initDataTables(document);

    $(document).on('shown.bs.tab', '[data-bs-toggle="tab"]', function (event) {
        if (!$.fn.DataTable) {
            return;
        }

        var targetSelector = $(event.target).attr('data-bs-target') || $(event.target).attr('href') || '';
        var $target = targetSelector ? $(targetSelector) : $();
        $target.find('.app-data-table').each(function () {
            if (!$.fn.DataTable.isDataTable(this)) {
                initDataTables($target);
                return;
            }

            $(this).DataTable().columns.adjust();
        });
    });

    $(document).on('click', '[data-process-permission-bulk]', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var $button = $(this);
        var permission = String($button.data('process-permission-bulk') || '');
        var checked = $button.data('checked') === true || $button.data('checked') === 'true';
        var $wrapper = $button.closest('.dataTables_wrapper');
        var $table = $wrapper.length
            ? $wrapper.find('.dataTables_scrollBody table.app-data-table').first()
            : $button.closest('table.app-data-table');
        var $inputs;

        if (!permission || !$table.length) {
            return;
        }

        if ($.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
            $inputs = $($table.DataTable().rows().nodes()).find('input[type="checkbox"][value="' + permission.replace(/"/g, '\\"') + '"]');
        } else {
            $inputs = $table.find('input[type="checkbox"][value="' + permission.replace(/"/g, '\\"') + '"]');
        }

        $inputs.prop('checked', checked).trigger('change');
    });

    $(document).on('submit', 'form', function () {
        var $form = $(this);
        var tables = $form.find('table.app-data-table[data-preserve-form-inputs="true"]');

        $form.find('input[data-dt-preserved-input="true"]').remove();

        tables.each(function () {
            var table = this;
            var $checkedInputs;

            if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
                $checkedInputs = $($(table).DataTable().rows().nodes()).find('input[type="checkbox"]:checked[name]');
            } else {
                $checkedInputs = $(table).find('input[type="checkbox"]:checked[name]');
            }

            $checkedInputs.each(function () {
                $('<input>', {
                    type: 'hidden',
                    name: this.name,
                    value: this.value,
                    'data-dt-preserved-input': 'true'
                }).appendTo($form);
            });
        });
    });

    function syncProfileScopePermissions() {
        if (!$('[data-permission-group]').length) {
            return;
        }

        $('[data-permission-group]').each(function () {
            var $group = $(this);
            var enabled = $group.find('.permission-scope-toggle').is(':checked');
            $group.toggleClass('is-disabled', !enabled);
            $group.find('[data-permission-scope] input[type="checkbox"]').prop('disabled', !enabled);
            if (!enabled) {
                $group.find('[data-permission-scope] input[type="checkbox"]').prop('checked', false);
            }
        });

        syncProfileHomeRouteOptions();
    }

    function syncProfileHomeRouteOptions() {
        var $homeRoute = $('[data-profile-home-route]');
        if (!$homeRoute.length) {
            return;
        }

        var permissions = {};
        $('[data-permission-group]').each(function () {
            var $group = $(this);
            if (!$group.find('.permission-scope-toggle').is(':checked')) {
                return;
            }

            $group.find('[data-permission-scope] input[type="checkbox"]:checked').each(function () {
                permissions[$(this).val()] = true;
            });
        });

        var current = $homeRoute.val();
        var currentAllowed = false;
        $homeRoute.find('option').each(function () {
            var option = this;
            var required = [];
            try {
                required = JSON.parse(option.dataset.requiredPermissions || '[]');
            } catch (error) {
                required = [];
            }

            var match = option.dataset.permissionMatch || 'all';
            var allowed = required.length === 0;
            if (required.length && match === 'any') {
                allowed = required.some(function (permission) {
                    return permissions[permission];
                });
            } else if (required.length) {
                allowed = required.every(function (permission) {
                    return permissions[permission];
                });
            }

            option.hidden = !allowed;
            option.disabled = !allowed;
            if (option.value === current && allowed) {
                currentAllowed = true;
            }
        });

        if (!currentAllowed) {
            $homeRoute.val('dashboard');
        }
    }

    $(document).on('change', '.permission-scope-toggle', syncProfileScopePermissions);
    $(document).on('change', '[data-permission-scope] input[type="checkbox"]', syncProfileHomeRouteOptions);
    syncProfileScopePermissions();

    function filterProfilePermissions() {
        var $scope = $('#scope');
        if (!$scope.length) {
            return;
        }

        $('[data-permission-scope]').each(function () {
            var $option = $(this);
            var visible = true;
            $option.toggle(visible);
            if (!visible) {
                $option.find('input[type="checkbox"]').prop('checked', false);
            }
        });
    }

    $(document).on('change', '#scope', filterProfilePermissions);
    filterProfilePermissions();

    function parseLines(value) {
        return String(value || '').split(/\r?\n/).map(function (line) {
            return line.trim();
        }).filter(Boolean);
    }

    function escapeHtml(value) {
        return $('<div/>').text(value || '').html();
    }

    function itemTypeOptions(types, selected) {
        return Object.keys(types).map(function (key) {
            return '<option value="' + escapeHtml(key) + '"' + (key === selected ? ' selected' : '') + '>' + escapeHtml(types[key]) + '</option>';
        }).join('');
    }

    function scaleOptions($form, selected) {
        var options = '<option value="">Sin escala</option>';
        $form.find('[data-scale-row]').each(function () {
            var key = $(this).find('[data-scale-key]').val();
            var name = $(this).find('[data-scale-name]').val();
            if (key) {
                options += '<option value="' + escapeHtml(key) + '"' + (key === selected ? ' selected' : '') + '>' + escapeHtml(name || key) + '</option>';
            }
        });
        return options;
    }

    function parsePairs(value) {
        var pairs = {};
        String(value || '').split(';').map(function (part) {
            return part.trim();
        }).filter(Boolean).forEach(function (part) {
            var separator = part.indexOf('=');
            var key = separator >= 0 ? part.slice(0, separator).trim() : part;
            var label = separator >= 0 ? part.slice(separator + 1).trim() : part;
            if (key) {
                pairs[key] = label;
            }
        });
        return pairs;
    }

    function choiceRowsFromItem(data) {
        var labels = parsePairs(data.options);
        var scores = parsePairs(data.scoring);
        var keys = [];

        Object.keys(labels).forEach(function (key) {
            if (keys.indexOf(key) === -1) {
                keys.push(key);
            }
        });

        Object.keys(scores).forEach(function (key) {
            if (keys.indexOf(key) === -1) {
                keys.push(key);
            }
        });

        return keys.map(function (key) {
            return {
                value: key,
                label: labels[key] || '',
                score: scores[key] || ''
            };
        });
    }

    function renderChoiceRow(data) {
        return $(
            '<div class="test-option-row" data-item-choice-row>' +
                '<div><label class="form-label">Valor</label><input class="form-control" data-item-choice-value value="' + escapeHtml(data.value) + '" placeholder="1"></div>' +
                '<div><label class="form-label">Texto visible</label><input class="form-control" data-item-choice-label value="' + escapeHtml(data.label) + '" placeholder="Muy bajo"></div>' +
                '<div><label class="form-label">Puntaje</label><input class="form-control" data-item-choice-score value="' + escapeHtml(data.score) + '" placeholder="1"></div>' +
                '<div class="test-option-row-action"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-option-row><i class="bi bi-trash"></i></button></div>' +
            '</div>'
        );
    }

    var promptBlocksPrefix = '__blocks64__:';
    var promptHtmlPrefix = '__html64__:';

    function encodePromptHtml(html) {
        return promptHtmlPrefix + btoa(unescape(encodeURIComponent(html || '')));
    }

    function decodePromptHtml(value, legacyImageUrl) {
        var raw = String(value || '');
        var html = '';

        if (raw.indexOf(promptHtmlPrefix) === 0) {
            try {
                return decodeURIComponent(escape(atob(raw.slice(promptHtmlPrefix.length))));
            } catch (error) {
                return '';
            }
        }

        if (raw.indexOf(promptBlocksPrefix) === 0) {
            try {
                var blocks = JSON.parse(decodeURIComponent(escape(atob(raw.slice(promptBlocksPrefix.length)))));
                if (Array.isArray(blocks)) {
                    blocks.forEach(function (block) {
                        if (!block) {
                            return;
                        }
                        if (block.type === 'image' && $.trim(block.url)) {
                            html += '<p><img src="' + escapeHtml($.trim(block.url)) + '" alt=""></p>';
                        } else if (block.type === 'text' && $.trim(block.text)) {
                            html += '<p>' + escapeHtml($.trim(block.text)).replace(/\n/g, '<br>') + '</p>';
                        }
                    });
                }
            } catch (error) {
                html = '';
            }
            return html;
        }

        if ($.trim(raw)) {
            html += '<p>' + escapeHtml(raw).replace(/\n/g, '<br>') + '</p>';
        }
        if ($.trim(legacyImageUrl)) {
            html += '<p><img src="' + escapeHtml($.trim(legacyImageUrl)) + '" alt=""></p>';
        }

        return html;
    }

    function isPromptHtmlEmpty(html) {
        var $scratch = $('<div/>').html(html || '');
        return !$.trim($scratch.text()) && !$scratch.find('img[src]').length;
    }

    function cleanPromptHtml(html) {
        var $scratch = $('<div/>').html(html || '');
        $scratch.find('*').not('p,br,img,strong,b,em,i,u,ul,ol,li').each(function () {
            $(this).replaceWith($(this).contents());
        });
        $scratch.find('strong,b,em,i,u,ul,ol,li').removeAttr('style class id');
        $scratch.find('p,li').each(function () {
            var $element = $(this);
            var safeStyles = [];
            var style = String($element.attr('style') || '');
            var align = style.match(/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)/i);
            var margin = style.match(/(?:^|;)\s*margin-left\s*:\s*([0-9]+)px/i);

            if (align) {
                safeStyles.push('text-align: ' + align[1].toLowerCase());
            }
            if (margin) {
                var marginLeft = Math.min(parseInt(margin[1], 10) || 0, 240);
                if (marginLeft > 0) {
                    safeStyles.push('margin-left: ' + marginLeft + 'px');
                }
            }

            $element.removeAttr('style class id');
            if (safeStyles.length) {
                $element.attr('style', safeStyles.join('; '));
            }
        });
        $scratch.find('img').each(function () {
            var $image = $(this);
            var src = $.trim($(this).attr('src') || '');
            if (!src) {
                $(this).remove();
                return;
            }

            var width = String($image.attr('width') || '').replace(/[^0-9]/g, '');
            var height = String($image.attr('height') || '').replace(/[^0-9]/g, '');
            var style = String($image.attr('style') || '');
            var styleWidth = style.match(/(?:^|;)\s*width\s*:\s*([0-9]+)px/i);
            var styleHeight = style.match(/(?:^|;)\s*height\s*:\s*([0-9]+)px/i);
            width = width || (styleWidth ? styleWidth[1] : '');
            height = height || (styleHeight ? styleHeight[1] : '');

            $image.attr({
                src: src,
                alt: $.trim($image.attr('alt') || '')
            }).removeAttr('style class id width height title loading srcset sizes');

            if (width && parseInt(width, 10) > 0 && parseInt(width, 10) <= 2000) {
                $image.attr('width', width);
            }
            if (height && parseInt(height, 10) > 0 && parseInt(height, 10) <= 2000) {
                $image.attr('height', height);
            }
        });
        $scratch.find('br').removeAttr('style class id');
        return $.trim($scratch.html());
    }

    var itemRenderBatchSize = 20;
    var promptEditorSequence = 0;

    function savePromptEditor($editor) {
        if (!window.tinymce) {
            return;
        }

        var id = $editor.attr('id');
        var editor = id ? tinymce.get(id) : null;
        if (editor) {
            editor.save();
        }
    }

    function syncItemPromptField($row) {
        savePromptEditor($row.find('[data-item-prompt-editor]'));

        var html = cleanPromptHtml($row.find('[data-item-prompt-editor]').val());
        $row.find('[data-item-prompt]').val(isPromptHtmlEmpty(html) ? '' : encodePromptHtml(html));
    }

    function syncAllItemPromptFields($form) {
        $form.find('[data-item-row]').each(function () {
            syncItemPromptField($(this));
        });
    }

    function syncItemChoiceFields($row) {
        var optionParts = [];
        var scoringParts = [];

        $row.find('[data-item-choice-row]').each(function () {
            var $choice = $(this);
            var value = $.trim($choice.find('[data-item-choice-value]').val());
            var label = $.trim($choice.find('[data-item-choice-label]').val());
            var score = $.trim($choice.find('[data-item-choice-score]').val());

            if (value && label) {
                optionParts.push(value + '=' + label);
            }

            if (value && score) {
                scoringParts.push(value + '=' + score);
            }
        });

        $row.find('[data-item-options]').val(optionParts.join(';'));
        $row.find('[data-item-scoring]').val(scoringParts.join(';'));
    }

    function itemDataFromRow($row) {
        syncItemPromptField($row);
        syncItemChoiceFields($row);

        return {
            key: $.trim($row.find('[data-item-key]').val()),
            scale: $.trim($row.find('[data-item-scale]').val()),
            type: $.trim($row.find('[data-item-type]').val()),
            reverse: $row.find('[data-item-reverse]').is(':checked') ? '1' : '0',
            prompt: $.trim($row.find('[data-item-prompt]').val()),
            options: $.trim($row.find('[data-item-options]').val()),
            scoring: $.trim($row.find('[data-item-scoring]').val()),
            imageUrl: ''
        };
    }

    function parseItemLine(line) {
        var parts = line.split('|');
        return {
            key: parts[0] || '',
            scale: parts[1] || '',
            type: parts[2] || 'likert',
            reverse: parts[3] || '0',
            prompt: parts[4] || '',
            options: parts[5] || '',
            scoring: parts[6] || '',
            imageUrl: parts.slice(7).join('|') || ''
        };
    }

    function itemLineFromData(item) {
        if (!item || item._deleted) {
            return '';
        }

        var key = $.trim(item.key || '');
        var type = $.trim(item.type || '');
        var prompt = $.trim(item.prompt || '');
        if (!key || !type || !prompt) {
            return '';
        }

        return [
            key,
            $.trim(item.scale || ''),
            type,
            item.reverse === '1' ? '1' : '0',
            prompt,
            $.trim(item.options || ''),
            $.trim(item.scoring || ''),
            ''
        ].join('|');
    }

    function itemStore($form) {
        return $form.data('test-item-store') || null;
    }

    function updateRenderedItemStore($form) {
        var store = itemStore($form);
        if (!store) {
            return;
        }

        $form.find('[data-item-row]').each(function () {
            var $row = $(this);
            var index = parseInt($row.attr('data-item-index'), 10);
            if (!isNaN(index) && store[index] && !store[index]._deleted) {
                store[index] = itemDataFromRow($row);
            }
        });
    }

    function syncAllItemChoiceFields($form) {
        $form.find('[data-item-row]').each(function () {
            syncItemChoiceFields($(this));
        });
    }

    function syncScaleLines($form) {
        var lines = [];
        $form.find('[data-scale-row]').each(function () {
            var $row = $(this);
            var key = $.trim($row.find('[data-scale-key]').val());
            var name = $.trim($row.find('[data-scale-name]').val());
            var description = $.trim($row.find('[data-scale-description]').val());
            if (key && name) {
                lines.push([key, name, description].join('|').replace(/\|$/, ''));
            }
        });
        $('#scale_lines').val(lines.join('\n'));
    }

    function syncItemLines($form) {
        var lines = [];
        var store = itemStore($form);

        if (store) {
            updateRenderedItemStore($form);
            store.forEach(function (item) {
                var line = itemLineFromData(item);
                if (line) {
                    lines.push(line);
                }
            });
        } else {
            syncAllItemPromptFields($form);
            syncAllItemChoiceFields($form);
            $form.find('[data-item-row]').each(function () {
                var line = itemLineFromData(itemDataFromRow($(this)));
                if (line) {
                    lines.push(line);
                }
            });
        }

        $('#item_lines').val(lines.join('\n'));
    }

    function refreshItemScaleSelects($form) {
        $form.find('[data-item-scale]').each(function () {
            var $select = $(this);
            var selected = $select.val();
            $select.html(scaleOptions($form, selected));
        });
    }

    function renderScaleRow(data) {
        return $(
            '<article class="test-builder-row" data-scale-row>' +
                '<div class="test-builder-row-head">' +
                    '<span class="test-builder-row-icon"><i class="bi bi-diagram-3"></i></span>' +
                    '<strong>Escala</strong>' +
                    '<button class="btn btn-sm btn-outline-danger ms-auto" type="button" data-remove-builder-row><i class="bi bi-trash"></i></button>' +
                '</div>' +
                '<div class="row g-3">' +
                    '<div class="col-12 col-lg-3"><label class="form-label">Clave</label><input class="form-control" data-scale-key value="' + escapeHtml(data.key) + '" placeholder="apertura"></div>' +
                    '<div class="col-12 col-lg-4"><label class="form-label">Nombre</label><input class="form-control" data-scale-name value="' + escapeHtml(data.name) + '" placeholder="Apertura"></div>' +
                    '<div class="col-12 col-lg-5"><label class="form-label">Descripcion</label><input class="form-control" data-scale-description value="' + escapeHtml(data.description) + '" placeholder="Descripcion opcional"></div>' +
                '</div>' +
            '</article>'
        );
    }

    function renderItemRow($form, data, index) {
        var types = $form.find('[data-test-builder="items"]').data('item-types') || {};
        var $row = $(
            '<article class="test-builder-row" data-item-row data-item-index="' + (typeof index === 'number' ? index : '') + '">' +
                '<div class="test-builder-row-head">' +
                    '<span class="test-builder-row-icon"><i class="bi bi-question-square"></i></span>' +
                    '<strong>Item</strong>' +
                    '<button class="btn btn-sm btn-outline-danger ms-auto" type="button" data-remove-builder-row><i class="bi bi-trash"></i></button>' +
                '</div>' +
                '<div class="row g-3">' +
                    '<div class="col-12 col-lg-3"><label class="form-label">Clave</label><input class="form-control" data-item-key value="' + escapeHtml(data.key) + '" placeholder="item_01"></div>' +
                    '<div class="col-12 col-lg-3"><label class="form-label">Escala</label><select class="form-select" data-item-scale>' + scaleOptions($form, data.scale) + '</select></div>' +
                    '<div class="col-12 col-lg-3"><label class="form-label">Tipo</label><select class="form-select" data-item-type>' + itemTypeOptions(types, data.type || 'likert') + '</select></div>' +
                    '<div class="col-12 col-lg-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-item-reverse ' + (data.reverse === '1' ? 'checked' : '') + '><label class="form-check-label">Inverso</label></div></div>' +
                    '<div class="col-12">' +
                        '<div class="test-prompt-builder">' +
                            '<div class="test-prompt-builder-head">' +
                                '<div><label class="form-label mb-1">Enunciado</label><div class="form-text mb-0">Escribe texto e inserta imagenes dentro del enunciado.</div></div>' +
                            '</div>' +
                            '<textarea class="form-control test-prompt-editor" data-item-prompt-editor rows="5">' + escapeHtml(decodePromptHtml(data.prompt, data.imageUrl)) + '</textarea>' +
                            '<input type="hidden" data-item-prompt value="' + escapeHtml(data.prompt) + '">' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-12">' +
                        '<div class="test-option-builder">' +
                            '<div class="test-option-builder-head">' +
                                '<div><label class="form-label mb-1">Opciones y scoring</label><div class="form-text mb-0">Define cada alternativa y el puntaje demo asociado.</div></div>' +
                                '<button class="btn btn-sm btn-outline-primary" type="button" data-add-option-row><i class="bi bi-plus-lg me-1"></i> Agregar opcion</button>' +
                            '</div>' +
                            '<div class="test-option-list" data-item-choice-list></div>' +
                            '<input type="hidden" data-item-options value="' + escapeHtml(data.options) + '">' +
                            '<input type="hidden" data-item-scoring value="' + escapeHtml(data.scoring) + '">' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</article>'
        );

        var choiceRows = choiceRowsFromItem(data);
        if (!choiceRows.length) {
            choiceRows = [{ value: '', label: '', score: '' }];
        }

        choiceRows.forEach(function (choice) {
            $row.find('[data-item-choice-list]').append(renderChoiceRow(choice));
        });

        syncItemChoiceFields($row);
        return $row;
    }

    function activeItemCount(store) {
        var count = 0;
        (store || []).forEach(function (item) {
            if (item && !item._deleted) {
                count++;
            }
        });
        return count;
    }

    function renderedItemCount($form) {
        return $form.find('[data-item-row]').length;
    }

    function updateItemRenderStatus($form) {
        var store = itemStore($form);
        var $status = $form.find('[data-item-render-status]');
        var $button = $form.find('[data-load-more-items]');
        if (!store || !$status.length) {
            return;
        }

        var rendered = renderedItemCount($form);
        var total = activeItemCount(store);
        $status.text('Mostrando ' + Math.min(rendered, total) + ' de ' + total + ' items');
        $button.toggle(rendered < total);
    }

    function renderNextItems($form, count) {
        var store = itemStore($form);
        var $itemList = $form.find('[data-item-list]');
        if (!store || !$itemList.length) {
            return;
        }

        var rendered = 0;
        for (var index = 0; index < store.length && rendered < count; index++) {
            if (!store[index] || store[index]._deleted || $itemList.find('[data-item-index="' + index + '"]').length) {
                continue;
            }
            $itemList.append(renderItemRow($form, store[index], index));
            rendered++;
        }

        initPromptEditors($itemList);
        refreshItemScaleSelects($form);
        updateItemRenderStatus($form);
        syncItemLines($form);
    }

    function initTestBuilder() {
        var $scaleList = $('[data-scale-list]');
        var $itemList = $('[data-item-list]');
        if (!$scaleList.length || !$itemList.length) {
            return;
        }

        var $form = $scaleList.closest('form');
        parseLines($('#scale_lines').val()).forEach(function (line) {
            var parts = line.split('|');
            $scaleList.append(renderScaleRow({
                key: parts[0] || '',
                name: parts[1] || '',
                description: parts.slice(2).join('|') || ''
            }));
        });

        var initialItems = parseLines($('#item_lines').val()).map(parseItemLine);
        if (!initialItems.length) {
            initialItems = [{ key: '', scale: '', type: 'likert', reverse: '0', prompt: '', options: '', scoring: '', imageUrl: '' }];
        }
        $form.data('test-item-store', initialItems);

        $itemList.after(
            '<div class="test-builder-pager" data-item-render-pager>' +
                '<span class="text-muted small" data-item-render-status></span>' +
                '<button class="btn btn-sm btn-outline-secondary" type="button" data-load-more-items>Mostrar mas items</button>' +
            '</div>'
        );

        if (!$scaleList.children().length) {
            $scaleList.append(renderScaleRow({ key: '', name: '', description: '' }));
        }

        renderNextItems($form, itemRenderBatchSize);
        refreshItemScaleSelects($form);
        syncScaleLines($form);
        syncItemLines($form);
    }

    function initPromptEditors(scope) {
        if (!window.tinymce || !$.fn.tinymce) {
            return;
        }

        var $scope = scope ? $(scope) : $(document);
        $scope.find('[data-item-prompt-editor], [data-evaluation-richtext-editor]').each(function () {
            var $editor = $(this);
            if ($editor.data('prompt-editor-ready')) {
                return;
            }

            if (!$editor.attr('id')) {
                promptEditorSequence++;
                $editor.attr('id', 'test_prompt_editor_' + promptEditorSequence);
            }

            $editor.data('prompt-editor-ready', true);
            $editor.tinymce({
                base_url: 'https://cdn.jsdelivr.net/npm/tinymce@7',
                suffix: '.min',
                menubar: false,
                branding: false,
                promotion: false,
                statusbar: false,
                resize: true,
                min_height: 180,
                plugins: 'image lists',
                toolbar: 'bold italic underline | alignleft aligncenter alignright alignjustify | bullist | outdent indent',
                contextmenu: false,
                paste_as_text: false,
                forced_root_block: 'p',
                valid_elements: 'p[style],br,strong/b,em/i,u,ul,ol,li[style],img[src|alt|width|height|style]',
                extended_valid_elements: 'img[src|alt|width|height|style]',
                image_description: false,
                image_dimensions: true,
                image_title: false,
                automatic_uploads: true,
                paste_data_images: true,
                object_resizing: 'img',
                images_upload_handler: function (blobInfo) {
                    return new Promise(function (resolve) {
                        var reader = new FileReader();
                        reader.onload = function () {
                            resolve(String(reader.result || ''));
                        };
                        reader.readAsDataURL(blobInfo.blob());
                    });
                },
                content_style: 'body{font-family:Inter,Roboto,system-ui,sans-serif;font-size:16px;line-height:1.45;margin:12px;} img{max-width:100%;height:auto;display:block;margin:10px 0;} p{margin:0 0 10px;}',
                setup: function (editor) {
                    editor.on('change keyup undo redo setcontent', function () {
                        if ($editor.is('[data-item-prompt-editor]')) {
                            syncItemLines($editor.closest('form'));
                        }
                    });
                }
            });
        });
    }

    $(document).on('click', '[data-add-scale]', function () {
        var $form = $(this).closest('form');
        $form.find('[data-scale-list]').append(renderScaleRow({ key: '', name: '', description: '' }));
        refreshItemScaleSelects($form);
        syncScaleLines($form);
    });

    $(document).on('click', '[data-add-item]', function () {
        var $form = $(this).closest('form');
        var store = itemStore($form);
        var item = { key: '', scale: '', type: 'likert', reverse: '0', prompt: '', options: '', scoring: '', imageUrl: '' };
        var index = null;
        if (store) {
            store.push(item);
            index = store.length - 1;
        }
        $form.find('[data-item-list]').append(renderItemRow($form, item, index));
        syncItemLines($form);
        updateItemRenderStatus($form);
    });

    $(document).on('click', '[data-load-more-items]', function () {
        var $form = $(this).closest('form');
        renderNextItems($form, itemRenderBatchSize);
    });

    $(document).on('click', '[data-add-option-row]', function () {
        var $itemRow = $(this).closest('[data-item-row]');
        var $form = $(this).closest('form');
        $itemRow.find('[data-item-choice-list]').append(renderChoiceRow({ value: '', label: '', score: '' }));
        syncItemChoiceFields($itemRow);
        syncItemLines($form);
    });

    $(document).on('click', '[data-remove-option-row]', function () {
        var $itemRow = $(this).closest('[data-item-row]');
        var $form = $(this).closest('form');
        $(this).closest('[data-item-choice-row]').remove();
        if (!$itemRow.find('[data-item-choice-row]').length) {
            $itemRow.find('[data-item-choice-list]').append(renderChoiceRow({ value: '', label: '', score: '' }));
        }
        syncItemChoiceFields($itemRow);
        syncItemLines($form);
    });

    $(document).on('click', '[data-remove-builder-row]', function () {
        var $form = $(this).closest('form');
        var $row = $(this).closest('[data-scale-row], [data-item-row]');
        var store = itemStore($form);
        if ($row.is('[data-item-row]') && store) {
            var index = parseInt($row.attr('data-item-index'), 10);
            if (!isNaN(index) && store[index]) {
                store[index]._deleted = true;
            }
        }
        $row.remove();
        refreshItemScaleSelects($form);
        updateItemRenderStatus($form);
        syncScaleLines($form);
        syncItemLines($form);
    });

    $(document).on('focus click', '[data-item-prompt-editor]', function () {
        initPromptEditors($(this).closest('[data-item-row]'));
    });

    $(document).on('input change', '[data-scale-row] input, [data-item-row] input, [data-item-row] textarea, [data-item-row] select', function () {
        var $form = $(this).closest('form');
        if ($(this).is('[data-scale-key], [data-scale-name]')) {
            refreshItemScaleSelects($form);
        }
        syncScaleLines($form);
        syncItemLines($form);
    });

    $(document).on('submit', '[data-test-builder] form, form:has([data-test-builder])', function () {
        var $form = $(this);
        syncScaleLines($form);
        syncItemLines($form);
    });

    initTestBuilder();

    function syncTestProgress($form) {
        var $cards = $form.find('.test-question-card');
        var $progress = $form.find('[data-test-progress]');
        if (!$progress.length) {
            return;
        }

        var total = parseInt($progress.attr('data-total'), 10) || $cards.length;
        var savedOutside = parseInt($progress.attr('data-saved-outside'), 10) || 0;
        var currentAnswered = 0;

        $cards.each(function () {
            var answered = false;
            $(this).find('input, textarea, select').each(function () {
                var $field = $(this);
                if (($field.is(':radio') || $field.is(':checkbox')) && $field.is(':checked')) {
                    answered = true;
                } else if (!$field.is(':radio, :checkbox') && $.trim($field.val())) {
                    answered = true;
                }
            });

            if (answered) {
                currentAnswered++;
            }
        });

        var answeredTotal = Math.min(total, savedOutside + currentAnswered);
        var percent = total > 0 ? Math.round((answeredTotal / total) * 100) : 0;

        $progress.find('[data-test-progress-fill]').css('width', percent + '%');
        $progress.find('[data-test-progress-label]').text(percent + '%');
        $progress.find('[data-test-progress-count]').text(answeredTotal);
        $progress.find('.test-progress-track').attr('aria-valuenow', percent);
    }

    $('.test-taking-shell form').each(function () {
        syncTestProgress($(this));
    });

    function setAudioVisualCheck($form, name, label, state, message) {
        var $item = $form.find('[data-audio-visual-check="' + name + '"]').first();
        if (!$item.length) return;
        var icons = {pending: 'bi-hourglass-split', ok: 'bi-check-circle-fill', error: 'bi-x-circle-fill'};
        var statuses = {pending: 'verificando', ok: 'operativo', error: 'no disponible'};
        var $icon = $item.find('[data-audio-visual-check-icon]').first();
        var $label = $item.find('[data-audio-visual-check-label]').first();
        var $message = $item.find('[data-audio-visual-check-message]').first();
        $item.removeClass('border-warning border-success border-danger text-warning text-success text-danger').addClass(state === 'ok' ? 'border-success text-success' : state === 'error' ? 'border-danger text-danger' : 'border-warning text-warning');
        if ($icon.length) $icon.removeClass('bi-hourglass-split bi-check-circle-fill bi-x-circle-fill').addClass(icons[state] || icons.pending);
        if ($label.length) $label.text(label + ': ' + (statuses[state] || statuses.pending)); else $item.text(label + ': ' + (statuses[state] || statuses.pending));
        if ($message.length) $message.text(message || '');
        if (message) $item.attr('title', message); else $item.removeAttr('title');
    }

    function initAudioVisualBrowserLog($form) {
        var enabled = new URLSearchParams(window.location.search || '').get('audio_debug') === '1';
        var $entries = $();
        if (enabled) {
            var $panel = $('<details open class="audio-visual-debug-panel" style="position:fixed;left:8px;right:8px;bottom:8px;z-index:2147483647;background:#111827;color:#f9fafb;border:2px solid #f59e0b;border-radius:8px;padding:6px;font:12px/1.35 -apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.35)"></details>');
            $entries = $('<div style="max-height:28vh;overflow:auto;margin-top:6px"></div>');
            var $clear = $('<button type="button" style="margin-top:6px;padding:3px 8px;border:1px solid #9ca3af;border-radius:4px;background:#374151;color:#fff">Limpiar</button>');
            $clear.on('click', function () { $entries.empty(); });
            $panel.append('<summary style="cursor:pointer;font-weight:700">Diagnóstico audiovisual · toca para contraer</summary>', $entries, $clear).appendTo('body');
        }
        return function (eventType, metadata) {
            if (!enabled) return;
            var timestamp = new Date().toISOString();
            var details = metadata || {};
            try { window.console.info('[e-talent audiovisual] ' + eventType, details); } catch (error) {}
            var serialized = '';
            try { serialized = JSON.stringify(details); } catch (error) { serialized = String(details); }
            var $entry = $('<div style="border-top:1px solid #374151;padding:4px 0;word-break:break-word"></div>');
            $('<span style="color:#fbbf24"></span>').text(timestamp).appendTo($entry);
            $('<strong></strong>').text(' ' + eventType).appendTo($entry);
            $('<br>').appendTo($entry);
            $('<span style="color:#d1d5db"></span>').text(serialized).appendTo($entry);
            $entry.appendTo($entries);
            $entries[0].scrollTop = $entries[0].scrollHeight;
        };
    }

    function getAudioVisualBrowserCompatibility() {
        var userAgent = String(window.navigator && window.navigator.userAgent || '');
        var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(userAgent) || (window.navigator && window.navigator.maxTouchPoints > 1 && window.innerWidth < 1024);
        var hasScreenCapture = Boolean(navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function');
        var isFirefox = /Firefox\//i.test(userAgent);
        var isEdge = /Edg\//i.test(userAgent);
        var isChrome = /Chrome\//i.test(userAgent) && !isEdge;
        var isSafari = /Safari\//i.test(userAgent) && !isChrome && !isEdge && !isFirefox;

        if (isMobile) {
            return {ok: true, source: 'canvas', message: 'Este dispositivo usará captura Canvas del contenido visible de la evaluación.'};
        }
        if (!window.isSecureContext || !hasScreenCapture) {
            return {ok: true, source: 'canvas', message: 'La pantalla completa no está disponible. Se usará captura Canvas del contenido visible de la evaluación.'};
        }
        if (isFirefox) {
            return {ok: true, source: 'canvas', message: 'Firefox usará captura Canvas del contenido visible de la evaluación.'};
        }
        if (isChrome || isEdge || isSafari) {
            return {ok: true, source: 'screen', message: 'Navegador compatible. Deberás seleccionar “Toda la pantalla” al iniciar.'};
        }
        return {ok: true, source: 'canvas', message: 'La pantalla completa no está disponible en este navegador. Se usará captura Canvas del contenido visible de la evaluación.'};
    }

    function applyAudioVisualBrowserCompatibility($form) {
        var result = getAudioVisualBrowserCompatibility();
        $form.data('audio-visual-browser-compatibility', result);
        var $status = $form.find('[data-audio-visual-status]').first();
        if ($status.length && result.message) {
            $status.removeClass('alert-secondary alert-success alert-danger alert-warning').addClass(result.ok && result.source === 'screen' ? 'alert-secondary' : result.ok ? 'alert-warning' : 'alert-danger').text(result.message).toggleClass('d-none', false);
        }
        if (!result.ok) {
            setAudioVisualCheck($form, 'screen', 'Captura', 'error', result.message);
        }
        return result;
    }

    function setAudioVisualReconnectStatus($form, component, message) {
        var $indicator = $form.find('[data-audio-visual-reconnect-indicator]').first();
        if (!$indicator.length) return;
        var labels = {camera: 'Cámara', microphone: 'Micrófono', screen: 'Captura de pantalla'};
        var $icon = $indicator.find('[data-audio-visual-reconnect-icon]').first();
        var $component = $indicator.find('[data-audio-visual-reconnect-component]').first();
        $indicator.removeClass('alert-danger alert-warning alert-success').addClass(component ? 'alert-danger' : 'alert-warning');
        if ($icon.length) $icon.removeClass('bi-exclamation-circle-fill bi-camera-video bi-mic-fill bi-display').addClass(component === 'camera' ? 'bi-camera-video' : component === 'microphone' ? 'bi-mic-fill' : component === 'screen' ? 'bi-display' : 'bi-exclamation-circle-fill');
        if ($component.length) $component.text((labels[component] || 'Componente audiovisual') + ': ' + (message || 'no disponible'));
    }

    function requestAudioVisualDevices($form) {
        var constraints = {
            video: {facingMode: {ideal: 'user'}, width: {ideal: 640, max: 1280}, height: {ideal: 360, max: 720}, frameRate: {ideal: 15, max: 20}},
            audio: {channelCount: {ideal: 1}, echoCancellation: true, noiseSuppression: true, autoGainControl: true}
        };
        var explain = function (component, error) {
            if (error && error.audioVisualMessage) return error.audioVisualMessage;
            var name = error && error.name ? error.name : '';
            if (name === 'NotAllowedError' || name === 'SecurityError') return component === 'camera' ? 'Permite el uso de la cámara en el navegador.' : 'Permite el uso del micrófono en el navegador.';
            if (name === 'NotFoundError') return component === 'camera' ? 'No se encontró una cámara disponible.' : 'No se encontró un micrófono disponible.';
            if (name === 'NotReadableError') return component === 'camera' ? 'La cámara está siendo utilizada o no responde.' : 'El micrófono está siendo utilizado o no responde.';
            return component === 'camera' ? 'No fue posible validar la cámara.' : 'No fue posible validar el micrófono.';
        };
        var permissionState = function (name) {
            if (!navigator.permissions || typeof navigator.permissions.query !== 'function') return Promise.resolve('unknown');
            return navigator.permissions.query({name: name}).then(function (permission) {
                return permission && permission.state ? permission.state : 'unknown';
            }).catch(function () { return 'unknown'; });
        };
        var deviceCount = function (kind) {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') return Promise.resolve(null);
            return navigator.mediaDevices.enumerateDevices().then(function (devices) {
                return devices.filter(function (device) { return device.kind === kind; }).length;
            }).catch(function () { return null; });
        };
        var diagnosticError = function (component, error, kind, permission, count) {
            var name = error && error.name ? error.name : '';
            var label = component === 'camera' ? 'cámara' : 'micrófono';
            var code = 'unknown';
            var message = 'No fue posible validar el ' + label + '.';
            if (name === 'NotAllowedError' || name === 'SecurityError' || permission === 'denied') {
                code = 'permission_blocked';
                message = 'El ' + label + ' está bloqueado para este sitio o por el sistema operativo. Activa el permiso y vuelve a intentar.';
            } else if (name === 'NotFoundError' || count === 0) {
                code = 'device_missing';
                message = 'No se encontró un ' + label + ' disponible en este dispositivo.';
            } else if (name === 'NotReadableError' || name === 'AbortError') {
                code = 'device_busy';
                message = 'El ' + label + ' está siendo utilizado por otra aplicación o no responde.';
            } else if (name === 'OverconstrainedError') {
                code = 'constraints_unsupported';
                message = 'La configuración solicitada no es compatible con el ' + label + '.';
            }
            return Object.assign(error instanceof Error ? error : new Error(code), {
                audioVisualComponent: component,
                audioVisualCode: code,
                audioVisualMessage: message
            });
        };
        if (!window.isSecureContext || !navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            var unsupported = new Error('media_unsupported');
            unsupported.audioVisualComponent = 'camera';
            unsupported.audioVisualCode = 'not_supported';
            unsupported.audioVisualMessage = 'Este navegador o contexto no permite acceder a cámara y micrófono.';
            return Promise.reject(unsupported);
        }
        setAudioVisualCheck($form, 'camera', 'Cámara', 'pending', 'Solicitando acceso…');
        return Promise.all([permissionState('camera'), deviceCount('videoinput')]).then(function (diagnostics) {
            if (diagnostics[0] === 'denied') throw diagnosticError('camera', new Error('permission_denied'), 'videoinput', diagnostics[0], diagnostics[1]);
            return navigator.mediaDevices.getUserMedia({video: constraints.video}).catch(function (error) {
                return deviceCount('videoinput').then(function (count) { return Promise.reject(diagnosticError('camera', error, 'videoinput', diagnostics[0], count)); });
            });
        }).then(function (cameraStream) {
            var cameraTrack = cameraStream.getVideoTracks()[0];
            if (!cameraTrack || cameraTrack.readyState !== 'live') throw diagnosticError('camera', new Error('camera_not_operational'), 'videoinput', 'unknown', 1);
            setAudioVisualCheck($form, 'camera', 'Cámara', 'ok', 'Acceso concedido y cámara activa.');
            setAudioVisualCheck($form, 'microphone', 'Micrófono', 'pending', 'Solicitando acceso…');
            return Promise.all([permissionState('microphone'), deviceCount('audioinput')]).then(function (diagnostics) {
                if (diagnostics[0] === 'denied') throw diagnosticError('microphone', new Error('permission_denied'), 'audioinput', diagnostics[0], diagnostics[1]);
                return navigator.mediaDevices.getUserMedia({audio: constraints.audio}).catch(function (error) {
                    return deviceCount('audioinput').then(function (count) { return Promise.reject(diagnosticError('microphone', error, 'audioinput', diagnostics[0], count)); });
                });
            }).then(function (audioStream) {
                var microphoneTrack = audioStream.getAudioTracks()[0];
                if (!microphoneTrack || microphoneTrack.readyState !== 'live') throw diagnosticError('microphone', new Error('microphone_not_operational'), 'audioinput', 'unknown', 1);
                setAudioVisualCheck($form, 'microphone', 'Micrófono', 'ok', 'Acceso concedido y micrófono activo.');
                return new MediaStream(cameraStream.getVideoTracks().concat(audioStream.getAudioTracks()));
            }).catch(function (error) {
                cameraStream.getTracks().forEach(function (track) { track.stop(); });
                error.audioVisualComponent = error.audioVisualComponent || 'microphone';
                setAudioVisualCheck($form, error.audioVisualComponent, error.audioVisualComponent === 'camera' ? 'Cámara' : 'Micrófono', 'error', explain(error.audioVisualComponent, error));
                throw error;
            });
        }).catch(function (error) {
            var component = error.audioVisualComponent || 'camera';
            setAudioVisualCheck($form, component, component === 'camera' ? 'Cámara' : 'Micrófono', 'error', explain(component, error));
            throw error;
        });
    }

    function createAudioVisualScreenCapture($form, csrfToken, getEvidenceId) {
        var options = arguments[3] || {};
        var controller = {
            source: null,
            stream: null,
            timer: null,
            frameTimer: null,
            captureNumber: 0,
            validFrames: 0,
            started: false,
            lastHash: '',
            captureInFlight: false,
            pendingUploads: [],
            initialTimer: null,
            preferCanvas: Boolean(options.preferCanvas),
            fallbackReason: '',
            fallbackLogged: false,
            onDiagnostic: typeof options.onDiagnostic === 'function' ? options.onDiagnostic : function () {}
        };
        var captureUrl = $form.attr('data-media-screenshot-url') || '';
        var uploadFailurePolicy = $form.attr('data-audio-visual-upload-failure-policy') || 'continue';
        var qualityProfile = $form.attr('data-audio-visual-quality-profile') || 'economical';
        var quality = {economical: 0.45, standard: 0.60, high: 0.75}[qualityProfile] || 0.45;
        var captureMime = null;
        var screenErrorMessage = function (error) {
            var name = error && error.name ? error.name : '';
            var reason = error && error.message ? error.message : '';
            if (reason === 'full_screen_required') return 'Selecciona “Toda la pantalla” para continuar.';
            if (reason === 'screen_capture_ended') return 'La selección de pantalla se cerró. Vuelve a compartir toda la pantalla.';
            if (reason === 'screen_capture_cancelled' || error && error.audioVisualCancelled) return 'Debes compartir una pantalla para continuar. Pulsa reintentar y selecciona “Toda la pantalla”.';
            if (name === 'NotAllowedError') return 'Debes autorizar la captura y seleccionar una pantalla completa.';
            if (name === 'AbortError') return 'Debes compartir una pantalla para continuar. Pulsa reintentar y selecciona “Toda la pantalla”.';
            if (name === 'NotFoundError') return 'No hay una pantalla disponible para compartir.';
            if (reason === 'html2canvas_load_failed' || reason === 'html2canvas_unavailable') return 'No se pudo activar la captura alternativa del contenido.';
            if (reason === 'screen_capture_upload_failed') return 'La captura se generó, pero no pudo enviarse al servidor.';
            return 'No fue posible validar la captura de pantalla.';
        };
        var $preview = $form.find('[data-audio-visual-preview]').first();
        var $status = $form.find('[data-audio-visual-status]').first();
        var $check = function (name, label, state, message) {
            setAudioVisualCheck($form, name, label, state, message || '');
        };
        var wait = function (ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); };
        var diagnostic = function (eventType, metadata) {
            try { controller.onDiagnostic(eventType, Object.assign({source: controller.source || 'unknown'}, metadata || {})); } catch (error) {}
        };
        var loadHtml2Canvas = function () {
            if (typeof window.html2canvas === 'function') return Promise.resolve(window.html2canvas);
            var existing = document.querySelector('script[data-e_talent-html2canvas]');
            if (existing) return new Promise(function (resolve, reject) { existing.addEventListener('load', function () { resolve(window.html2canvas); }); existing.addEventListener('error', reject); });
            return new Promise(function (resolve, reject) {
                var script = document.createElement('script'); script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js'; script.async = true; script.dataset.e_talentHtml2canvas = '1';
                script.onload = function () { if (typeof window.html2canvas === 'function') resolve(window.html2canvas); else reject(new Error('html2canvas_unavailable')); };
                script.onerror = function () { reject(new Error('html2canvas_load_failed')); }; document.head.appendChild(script);
            });
        };
        var upload = function (blob, source, eventType) {
            if (!captureUrl || !blob || !blob.size) return Promise.reject(new Error('screen_capture_upload_unavailable'));
            var waitForEvidence = function (attempt) {
                if (getEvidenceId()) return Promise.resolve(String(getEvidenceId()));
                if (attempt >= 120) return Promise.reject(new Error('screen_capture_evidence_unavailable'));
                return wait(250).then(function () { return waitForEvidence(attempt + 1); });
            };
            return waitForEvidence(0).then(function (evidenceId) {
                var number = controller.captureNumber++;
                var data = new FormData(); data.append('csrf_token', csrfToken); data.append('evidence_id', evidenceId); data.append('capture_source', source); data.append('capture_number', String(number)); data.append('event_type', eventType || 'periodic'); data.append('capture', blob, 'capture-' + number + (blob.type === 'image/webp' ? '.webp' : '.jpg'));
                var maxAttempts = 3;
                var send = function (attempt) { return window.fetch(captureUrl, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body: data}).then(function (response) { return response.json().catch(function () { return {}; }).then(function (payload) { if (!response.ok || !payload.ok) throw new Error(payload.reason || 'screen_capture_upload_failed'); return payload; }); }).catch(function (error) { if (attempt < maxAttempts) return wait(700 * attempt).then(function () { return send(attempt + 1); }); throw error; }); };
                var uploadPromise = send(1);
                controller.pendingUploads.push(uploadPromise);
                uploadPromise.then(function () { controller.pendingUploads = controller.pendingUploads.filter(function (item) { return item !== uploadPromise; }); }, function () { controller.pendingUploads = controller.pendingUploads.filter(function (item) { return item !== uploadPromise; }); });
                return uploadPromise;
            });
        };
        var resizeCanvas = function (canvas) {
            var maxWidth = 960;
            var maxHeight = 540;
            if (canvas.width <= maxWidth && canvas.height <= maxHeight) return canvas;
            var ratio = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);
            var resized = document.createElement('canvas');
            resized.width = Math.max(1, Math.round(canvas.width * ratio));
            resized.height = Math.max(1, Math.round(canvas.height * ratio));
            resized.getContext('2d').drawImage(canvas, 0, 0, resized.width, resized.height);
            return resized;
        };
        var canvasBlob = function (canvas) {
            var target = resizeCanvas(canvas);
            if (!captureMime) {
                try { captureMime = target.toDataURL('image/webp', quality).indexOf('data:image/webp') === 0 ? 'image/webp' : 'image/jpeg'; } catch (error) { captureMime = 'image/jpeg'; }
            }
            var encode = function (mime, level) { return new Promise(function (resolve) { target.toBlob(function (blob) { resolve(blob); }, mime, level); }); };
            var maxBytes = controller.source === 'canvas' ? 180 * 1024 : 250 * 1024;
            var compress = function (level, attempt) {
                return encode(captureMime, level).then(function (blob) {
                    if (blob && blob.size > maxBytes && attempt < 4 && level > 0.28) return compress(Math.max(0.28, level * 0.78), attempt + 1);
                    return blob;
                });
            };
            return compress(quality, 0).then(function (blob) { if (!blob || !blob.size) throw new Error('screen_capture_encode_failed'); return blob; });
        };
        var sanitizeCanvasStyles = function (clonedDocument) {
            var unsupported = /(?:color|oklch|lab|lch|hwb)\(/i;
            var colorProperties = ['color', 'background-color', 'border-color', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color', 'outline-color', 'text-decoration-color', 'column-rule-color', 'caret-color', 'fill', 'stroke'];
            var fallbackFor = function (property) { return property === 'color' || property.indexOf('border') === 0 || property === 'outline-color' || property === 'text-decoration-color' ? '#1f2937' : '#ffffff'; };
            var sanitized = 0;
            var sanitizeStyle = function (style) {
                if (!style) return;
                colorProperties.forEach(function (property) {
                    var value = style.getPropertyValue(property);
                    if (value && unsupported.test(value)) { style.setProperty(property, fallbackFor(property), style.getPropertyPriority(property)); sanitized++; }
                });
                ['background-image', 'box-shadow', 'text-shadow'].forEach(function (property) {
                    var value = style.getPropertyValue(property);
                    if (value && unsupported.test(value)) { style.setProperty(property, 'none', style.getPropertyPriority(property)); sanitized++; }
                });
                for (var index = 0; index < style.length; index++) {
                    var property = style[index];
                    var value = style.getPropertyValue(property);
                    if (property.indexOf('--') === 0 && value && unsupported.test(value)) { style.setProperty(property, '#ffffff'); sanitized++; }
                }
            };
            Array.prototype.forEach.call(clonedDocument.querySelectorAll('*'), function (element) {
                sanitizeStyle(element.style);
                try {
                    var computed = clonedDocument.defaultView.getComputedStyle(element);
                    colorProperties.forEach(function (property) {
                        var value = computed.getPropertyValue(property);
                        if (value && unsupported.test(value)) { element.style.setProperty(property, fallbackFor(property)); sanitized++; }
                    });
                    ['background-image', 'box-shadow', 'text-shadow'].forEach(function (property) {
                        var value = computed.getPropertyValue(property);
                        if (value && unsupported.test(value)) { element.style.setProperty(property, 'none'); sanitized++; }
                    });
                } catch (error) {}
            });
            var walkRules = function (rules) {
                if (!rules) return;
                Array.prototype.forEach.call(rules, function (rule) {
                    if (rule.style) sanitizeStyle(rule.style);
                    if (rule.cssRules) walkRules(rule.cssRules);
                });
            };
            Array.prototype.forEach.call(clonedDocument.styleSheets || [], function (sheet) { try { walkRules(sheet.cssRules); } catch (error) {} });
            diagnostic('audio_visual_canvas_styles_sanitized', {replacements: sanitized, unsupported_color_functions: ['color', 'oklch', 'lab', 'lch', 'hwb']});
            return sanitized;
        };
        var buildCanvasSafeRoot = function (root) {
            var clone = root.cloneNode(true);
            var rootRect = root.getBoundingClientRect();
            var rootWidth = Math.max(1, Math.ceil(rootRect.width));
            var rootHeight = Math.max(1, Math.ceil(rootRect.height));
            var frame = document.createElement('iframe');
            frame.setAttribute('title', '');
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;left:-100000px;top:0;width:' + rootWidth + 'px;height:' + rootHeight + 'px;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(frame);
            var safeDocument = frame.contentDocument;
            safeDocument.documentElement.style.margin = '0';
            safeDocument.documentElement.style.width = rootWidth + 'px';
            safeDocument.body.style.margin = '0';
            safeDocument.body.style.width = rootWidth + 'px';
            // No insertar <base> en el iframe about:blank. La CSP de la
            // aplicación aplica base-uri="self" y el origen de about:blank
            // no coincide con el origen de la página; además, el clon ya
            // conserva las URLs absolutas necesarias para la captura.
            clone.style.width = rootWidth + 'px';
            clone.style.minHeight = rootHeight + 'px';
            safeDocument.body.appendChild(clone);
            var originalElements = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
            var clonedElements = [clone].concat(Array.prototype.slice.call(clone.querySelectorAll('*')));
            var unsupported = /(?:color|oklch|lab|lch|hwb)\(/i;
            var fallbackFor = function (property) { return property === 'color' || property.indexOf('border') === 0 || property === 'outline-color' || property === 'text-decoration-color' ? '#1f2937' : '#ffffff'; };
            var sanitized = 0;
            originalElements.forEach(function (originalElement, index) {
                var clonedElement = clonedElements[index];
                if (!clonedElement) return;
                try {
                    var computed = window.getComputedStyle(originalElement);
                    for (var propertyIndex = 0; propertyIndex < computed.length; propertyIndex++) {
                        var property = computed[propertyIndex];
                        var value = computed.getPropertyValue(property);
                        if (!value) continue;
                        if (unsupported.test(value)) {
                            if (property === 'background-image' || property === 'box-shadow' || property === 'text-shadow' || property === 'filter') value = 'none';
                            else if (property.indexOf('color') !== -1 || property === 'fill' || property === 'stroke' || property.indexOf('--') === 0) value = fallbackFor(property);
                            else continue;
                            sanitized++;
                        }
                        clonedElement.style.setProperty(property, value, computed.getPropertyPriority(property));
                    }
                } catch (error) {}
            });
            Array.prototype.forEach.call(clone.querySelectorAll('style,link[rel="stylesheet"]'), function (styleNode) { styleNode.remove(); });
            diagnostic('audio_visual_canvas_safe_clone_ready', {replacements: sanitized, isolated_document: true, width: rootWidth, height: rootHeight});
            return {root: clone, cleanup: function () { frame.remove(); }};
        };
        var captureFrame = function (eventType, uploadEvidence) {
            if (controller.captureInFlight) return Promise.reject(new Error('screen_capture_busy'));
            controller.captureInFlight = true;
            var root = $form.closest('.evaluation-take-panel, .test-fullscreen-stage, .test-taking-shell')[0] || document.body;
            var capturePromise;
            if (controller.source === 'screen' && controller.stream) {
                if (!$preview.length) { $preview = $('<video muted playsinline autoplay class="d-none" data-audio-visual-preview></video>').appendTo($form); }
                $preview[0].srcObject = controller.stream; capturePromise = $preview[0].play().catch(function () {}).then(function () {
                    var canvas = document.createElement('canvas'); canvas.width = $preview[0].videoWidth || 1280; canvas.height = $preview[0].videoHeight || 720; canvas.getContext('2d').drawImage($preview[0], 0, 0, canvas.width, canvas.height); return canvasBlob(canvas);
                }).then(function (blob) { return uploadEvidence === false ? blob : upload(blob, 'screen', eventType); });
            } else if (controller.source === 'canvas') {
                capturePromise = loadHtml2Canvas().then(function (html2canvas) {
                    var safeClone = buildCanvasSafeRoot(root);
                    return html2canvas(safeClone.root, {useCORS: false, allowTaint: false, backgroundColor: '#ffffff', scale: Math.min(1, window.devicePixelRatio || 1), logging: false}).then(function (canvas) { safeClone.cleanup(); return canvasBlob(canvas); }, function (error) { safeClone.cleanup(); throw error; });
                }).then(function (blob) { return uploadEvidence === false ? blob : upload(blob, 'canvas', eventType); });
            } else {
                capturePromise = Promise.reject(new Error('screen_capture_not_started'));
            }
            return capturePromise.then(function (result) { controller.captureInFlight = false; diagnostic('audio_visual_capture_succeeded', {event_type: eventType || 'periodic', uploaded: uploadEvidence !== false, bytes: result && result.size ? result.size : null}); return result; }, function (error) { controller.captureInFlight = false; diagnostic('audio_visual_capture_failed', {event_type: eventType || 'periodic', reason: error && (error.message || error.name) || 'capture_failed'}); throw error; });
        };
        // La validación previa solo confirma que se pueden generar imágenes localmente.
        // Nunca las envía ni las encola antes de entrar a responder.
        var validateFrames = function () { return captureFrame('preflight', false).then(function () { controller.validFrames++; return wait(350); }).then(function () { return captureFrame('preflight', false); }).then(function () { controller.validFrames++; return controller; }); };
        var startCanvas = function (deferValidation, reason) {
            diagnostic('audio_visual_canvas_requested', {reason: reason || 'screen_capture_unavailable'});
            return loadHtml2Canvas().then(function () {
                controller.source = 'canvas';
                controller.fallbackReason = reason || controller.fallbackReason || 'screen_capture_unavailable';
                diagnostic('audio_visual_canvas_ready', {reason: controller.fallbackReason});
                return deferValidation ? controller : validateFrames();
            }).catch(function (error) {
                diagnostic('audio_visual_canvas_failed', {reason: error && (error.message || error.name) || 'canvas_unavailable'});
                throw error;
            });
        };
        var start = function (options) {
            var deferValidation = Boolean(options && options.deferValidation);
            controller.validFrames = 0;
            $check('screen', 'Captura', 'pending');
            if (controller.preferCanvas || !navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function' || !window.isSecureContext) {
                return startCanvas(deferValidation, controller.preferCanvas ? 'browser_capability' : 'screen_capture_unavailable');
            }
            diagnostic('audio_visual_screen_requested', {reason: 'browser_capability'});
            return navigator.mediaDevices.getDisplayMedia({video: {displaySurface: 'monitor'}, audio: false}).then(function (stream) {
                var track = stream.getVideoTracks()[0]; var surface = track && track.getSettings ? track.getSettings().displaySurface : null;
                if (!track || track.readyState !== 'live' || (surface && surface !== 'monitor')) { stream.getTracks().forEach(function (item) { item.stop(); }); throw new Error('full_screen_required'); }
                controller.source = 'screen'; controller.stream = stream; track.addEventListener('ended', function () { controller.started = false; $check('screen', 'Captura de pantalla', 'error', screenErrorMessage({message: 'screen_capture_ended'})); if (controller.onInterrupted) controller.onInterrupted('screen_capture_ended'); });
                return deferValidation ? controller : validateFrames();
            }).catch(function (error) {
                var errorName = error && error.name || '';
                var cancelled = errorName === 'AbortError' || errorName === 'NotAllowedError';
                if (cancelled) {
                    diagnostic('audio_visual_screen_capture_cancelled', {reason: errorName || 'screen_capture_cancelled'});
                    error = error || new Error('screen_capture_cancelled');
                    error.audioVisualCancelled = true;
                } else {
                    diagnostic('audio_visual_screen_failed', {reason: errorName || error && error.message || 'screen_capture_failed'});
                }
                // En un navegador compatible, cancelar o rechazar no significa
                // que Canvas sea una alternativa válida: el usuario debe volver
                // a autorizar una pantalla para mantener la evidencia requerida.
                throw error;
            });
        };
        controller.start = function (options) { return start(options).then(function () { controller.started = true; if (!(options && options.deferValidation)) $check('screen', controller.source === 'screen' ? 'Pantalla completa' : 'Captura Canvas', 'ok', controller.source === 'screen' ? 'Pantalla completa autorizada.' : 'Captura alternativa activa.'); return controller; }).catch(function (error) { error = error || new Error('screen_capture_failed'); error.audioVisualComponent = 'screen'; error.audioVisualMessage = screenErrorMessage(error); $check('screen', 'Captura', 'error', error.audioVisualMessage); throw error; }); };
        controller.validate = function () { return validateFrames().then(function () { $check('screen', controller.source === 'screen' ? 'Pantalla completa' : 'Captura Canvas', 'ok', controller.source === 'screen' ? 'Pantalla completa autorizada.' : 'Captura alternativa activa.'); return controller; }); };
        controller.startPeriodic = function () {
            if (controller.timer || controller.initialTimer) return;
            var capturePeriodic = function (eventType) {
                if (!controller.started || controller.captureInFlight) return;
                captureFrame(eventType || 'periodic').catch(function (error) {
                    if (error && error.message === 'screen_capture_busy') return;
                    if (controller.onFailure) controller.onFailure('screen_capture_upload_failed', error);
                });
            };
            // La primera captura real debe ocurrir durante una evaluación breve,
            // pero después de que el inicio audiovisual ya fue confirmado.
            controller.initialTimer = window.setTimeout(function () {
                controller.initialTimer = null;
                capturePeriodic('initial');
            }, 3000);
            controller.timer = window.setInterval(function () { capturePeriodic('periodic'); }, 20000);
        };
        controller.capture = captureFrame;
        controller.flushUploads = function () { return Promise.all(controller.pendingUploads.slice()); };
        controller.stopScheduling = function () {
            controller.started = false;
            if (controller.timer) window.clearInterval(controller.timer);
            if (controller.initialTimer) window.clearTimeout(controller.initialTimer);
            controller.timer = null;
            controller.initialTimer = null;
        };
        controller.stop = function () { controller.started = false; if (controller.timer) window.clearInterval(controller.timer); if (controller.initialTimer) window.clearTimeout(controller.initialTimer); if (controller.stream) controller.stream.getTracks().forEach(function (track) { track.stop(); }); controller.timer = null; controller.initialTimer = null; controller.stream = null; controller.captureInFlight = false; };
        controller.isReady = function () { return controller.started && controller.validFrames >= 2; };
        return controller;
    }

    function initTestActivityTracking($form) {
        if (!$form.length) {
            return;
        }

        var activityUrl = $form.attr('data-activity-url') || '';
        var csrfToken = $form.find('input[name="csrf_token"]').val() || '';
        if (!activityUrl || !csrfToken) {
            return;
        }

        var supervisedMode = $form.attr('data-supervised-mode') === '1';
        var audioVisualMode = $form.attr('data-audio-visual-mode') === '1';
        var screenController = null;
        var audioVisualBrowserLog = initAudioVisualBrowserLog($form);
        var $audioVisualReconnectPanel = $form.find('[data-audio-visual-reconnect-panel]');
        var audioVisualReconnect = null;
        var audioVisualCompatibility = audioVisualMode ? applyAudioVisualBrowserCompatibility($form) : {ok: true};
        if (audioVisualMode) audioVisualBrowserLog('audio_visual_browser_compatibility', {source: audioVisualCompatibility.source, ok: audioVisualCompatibility.ok, user_agent: String(window.navigator && window.navigator.userAgent || '').slice(0, 160)});
        var $supervisedStart = $form.find('[data-supervised-start]');
        if (audioVisualMode && $supervisedStart.length && !audioVisualCompatibility.ok) {
            $supervisedStart.prop('disabled', true);
        }

        function setAudioVisualSecurityPause(paused, statusMessage) {
            if (!audioVisualMode) return;
            var $stack = $form.find('[data-test-item-stack]');
            $form.toggleClass('is-audio-visual-paused', paused).attr('aria-busy', paused ? 'true' : 'false');
            $stack.attr('aria-hidden', paused ? 'true' : 'false');
            $stack[0].inert = Boolean(paused);
            $audioVisualReconnectPanel.toggleClass('d-none', !paused);
            if (statusMessage) $audioVisualReconnectPanel.find('[data-audio-visual-reconnect-status]').text(statusMessage);
        }

        function initAudioVisualCapture() {
            if (!audioVisualMode) {
                return $.Deferred().resolve().promise();
            }
            if (!audioVisualCompatibility.ok) {
                $form.find('[data-audio-visual-status]').removeClass('d-none').text(audioVisualCompatibility.message);
                return $.Deferred().reject('screen_capture_browser_unsupported').promise();
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
                showNotification('error', 'Este navegador no permite iniciar el control audiovisual.');
                return $.Deferred().reject('media_unsupported').promise();
            }

            var mediaInitUrl = $form.attr('data-media-init-url') || '';
            var mediaChunkUrl = $form.attr('data-media-chunk-url') || '';
            var mediaFailureUrl = $form.attr('data-media-failure-url') || '';
            var csrf = csrfToken;
            var policy = $form.find('[data-audio-visual-policy]').val() || 'pause';
            var interruptionPolicy = $form.attr('data-audio-visual-interruption-policy') || policy;
            var voicePolicy = $form.attr('data-audio-visual-voice-policy') || 'warn';
            var permissionPolicy = $form.attr('data-audio-visual-permission-policy') || interruptionPolicy;
            var qualityProfile = $form.attr('data-audio-visual-quality-profile') || 'economical';
            var consented = $form.find('[data-audio-visual-consent]').is(':checked');
            if (!consented || !mediaInitUrl || !mediaChunkUrl) {
                showNotification('warning', 'Debes aceptar la captura audiovisual antes de comenzar.');
                return $.Deferred().reject('media_consent_required').promise();
            }

            var audioVisualDiagnostic = function (eventType, metadata) {
                audioVisualBrowserLog(eventType, metadata);
                var riskUrl = $form.attr('data-media-risk-url') || '';
                if (!riskUrl) return;
                var payload = new URLSearchParams();
                payload.append('csrf_token', csrfToken);
                payload.append('event_type', eventType);
                payload.append('severity', 'info');
                payload.append('evidence_id', $form.attr('data-media-evidence-id') || '');
                payload.append('metadata', JSON.stringify(metadata || {}));
                window.fetch(riskUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: payload.toString(), keepalive: true}).catch(function () {});
            };
            screenController = createAudioVisualScreenCapture($form, csrf, function () { return $form.attr('data-media-evidence-id') || ''; }, {preferCanvas: audioVisualCompatibility.source === 'canvas', onDiagnostic: audioVisualDiagnostic});
            audioVisualDiagnostic('audio_visual_browser_compatibility', {source: audioVisualCompatibility.source, ok: audioVisualCompatibility.ok, user_agent: String(window.navigator && window.navigator.userAgent || '').slice(0, 160)});
            setAudioVisualCheck($form, 'screen', 'Captura', 'pending', 'Solicitando acceso a pantalla completa…');
            var screenCapturePromise = screenController.start({deferValidation: true});
            $form.data('audio-visual-screen', screenController);

            var initPayload = new URLSearchParams();
            initPayload.append('csrf_token', csrf);
            initPayload.append('policy', policy);
            initPayload.append('consented', '1');

            return window.fetch(mediaInitUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' },
                body: initPayload.toString()
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.ok) {
                        throw new Error(payload.reason || 'media_init_failed');
                    }
                    return payload;
                });
            }).then(function (payload) {
                $form.attr('data-media-evidence-id', String(payload.evidence_id || ''));
                return requestAudioVisualDevices($form);
            }).then(function (stream) {
                var activeStream = stream;
                var mediaRecorder;
                var chunkNumber = 0;
                var uploadChain = Promise.resolve();
                var pendingChunks = 0;
                var maxPendingChunks = 4;
                var mediaStartedAt = 0;
                var mimeCandidates = ['video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus', 'video/webm', 'video/mp4'];
                var mimeType = mimeCandidates.find(function (candidate) { return MediaRecorder.isTypeSupported(candidate); }) || '';
                var quality = {
                    economical: { video: 400000, audio: 48000 },
                    standard: { video: 700000, audio: 80000 },
                    high: { video: 1200000, audio: 128000 }
                }[qualityProfile] || { video: 400000, audio: 48000 };
                var recorderOptions = mimeType ? { mimeType: mimeType, videoBitsPerSecond: quality.video, audioBitsPerSecond: quality.audio } : {};
                mediaRisk('audio_visual_recorder_format_selected', 'info', null, {mime_type: mimeType || 'browser_default', quality_profile: qualityProfile});

                function mediaRisk(eventType, severity, confidence, metadata) {
                    audioVisualBrowserLog(eventType, Object.assign({severity: severity || 'attention'}, metadata || {}));
                    var riskUrl = $form.attr('data-media-risk-url') || '';
                    if (!riskUrl) return;
                    var payload = new URLSearchParams();
                    payload.append('csrf_token', csrf);
                    payload.append('event_type', eventType);
                    payload.append('severity', severity || 'attention');
                    if (confidence !== null && typeof confidence !== 'undefined') payload.append('confidence', String(confidence));
                    payload.append('evidence_id', $form.attr('data-media-evidence-id') || '');
                    Object.keys(metadata || {}).forEach(function (key) { payload.append('metadata[' + key + ']', String(metadata[key])); });
                    window.fetch(riskUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: payload.toString(), keepalive: true }).catch(function () {});
                }

                function uploadChunk(blob, number) {
                    var formData = new FormData();
                    formData.append('csrf_token', csrf);
                    formData.append('evidence_id', $form.attr('data-media-evidence-id') || '');
                    formData.append('chunk_number', String(number));
                    formData.append('mime_type', mimeType || blob.type || 'video/webm');
                    formData.append('chunk', blob, 'chunk-' + number + '.bin');
                    return window.fetch(mediaChunkUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: formData }).then(function (response) {
                        if (!response.ok) throw new Error('chunk_upload_failed');
                        return response.json().then(function (payload) {
                            if (!payload || !payload.ok) throw new Error((payload && payload.reason) || 'chunk_upload_rejected');
                            return payload;
                        });
                    });
                }

                function uploadChunkWithRetry(blob, number) {
                    var failurePolicy = $form.attr('data-audio-visual-upload-failure-policy') || 'continue';
                    var maxAttempts = 3;
                    var send = function (attempt) {
                        return uploadChunk(blob, number).catch(function (error) {
                            if (attempt < maxAttempts) {
                                mediaRisk('upload_retry_requested', 'attention', null, { chunk_number: number, attempt: attempt, reason: error.message || 'network_error' });
                                return new Promise(function (resolve) { window.setTimeout(resolve, 1000); }).then(function () { return send(attempt + 1); });
                            }
                            throw error;
                        });
                    };
                    return send(1);
                }

                function queueChunk(blob) {
                    if (!blob || !blob.size) return;
                    if (pendingChunks >= maxPendingChunks) {
                        mediaRisk('recording_upload_backlog', 'risk', null, { pending_chunks: pendingChunks, limit: maxPendingChunks });
                        return;
                    }
                    pendingChunks++;
                    var number = chunkNumber++;
                    uploadChain = uploadChain.then(function () { return uploadChunkWithRetry(blob, number); }).catch(function (error) {
                        var configuredFailurePolicy = $form.attr('data-audio-visual-upload-failure-policy') || 'continue';
                        mediaRisk('recording_upload_failed', 'risk', null, { chunk_number: number, attempts: 3, reason: error.message || 'upload_failed' });
                        if (mediaFailureUrl) {
                            var failureData = new URLSearchParams();
                            failureData.append('csrf_token', csrf);
                            failureData.append('evidence_id', $form.attr('data-media-evidence-id') || '');
                            failureData.append('chunk_number', String(number));
                            failureData.append('reason', error.message || 'chunk_upload_failed');
                            window.fetch(mediaFailureUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: failureData.toString(), keepalive: true }).catch(function () {});
                        }
                    }).then(function () { pendingChunks = Math.max(0, pendingChunks - 1); });
                }

                var interruptedTracks = {};
                function monitorTrack(track, kind) {
                    ['mute', 'ended'].forEach(function (eventName) {
                        track.addEventListener(eventName, function () {
                            interruptedTracks[kind] = true;
                            mediaRisk(kind + '_' + (eventName === 'mute' ? 'muted' : 'track_ended'), 'risk', null, { ready_state: track.readyState });
                            setAudioVisualCheck($form, kind, kind === 'camera' ? 'Cámara' : 'Micrófono', 'error', kind === 'camera' ? 'La cámara se interrumpió o dejó de responder.' : 'El micrófono se interrumpió o dejó de responder.');
                            setAudioVisualReconnectStatus($form, kind, 'interrumpido o sin respuesta');
                            showNotification('warning', (kind === 'camera' ? 'La camara' : 'El microfono') + ' se interrumpio. Esta accion quedara registrada.');
                            $form.addClass('is-audio-visual-interrupted');
                            if (permissionPolicy === 'pause' || interruptionPolicy === 'pause') setAudioVisualSecurityPause(true, 'La cámara o el micrófono se interrumpieron. Presiona el botón para reconectar.');
                            if (permissionPolicy === 'block' || permissionPolicy === 'pause' || interruptionPolicy === 'block' || interruptionPolicy === 'pause') $form.find('[data-test-submit-actions] button').prop('disabled', true);
                        });
                    });
                    track.addEventListener('unmute', function () {
                        mediaRisk(kind + '_recovered', 'info', null, {});
                        setAudioVisualCheck($form, kind, kind === 'camera' ? 'Cámara' : 'Micrófono', 'ok', kind === 'camera' ? 'Cámara activa nuevamente.' : 'Micrófono activo nuevamente.');
                        delete interruptedTracks[kind];
                        if (Object.keys(interruptedTracks).length === 0) {
                            $form.removeClass('is-audio-visual-interrupted');
                            if (Object.keys(interruptedTracks).length === 0) setAudioVisualSecurityPause(false);
                            if (Object.keys(interruptedTracks).length === 0 && (permissionPolicy === 'block' || permissionPolicy === 'pause' || interruptionPolicy === 'block' || interruptionPolicy === 'pause')) $form.find('[data-test-submit-actions] button').prop('disabled', false);
                        }
                    });
                }

                function startRecorderForStream(nextStream) {
                    activeStream = nextStream;
                    interruptedTracks = {};
                    nextStream.getVideoTracks().forEach(function (track) { monitorTrack(track, 'camera'); });
                    nextStream.getAudioTracks().forEach(function (track) { monitorTrack(track, 'microphone'); });
                    try { mediaRecorder = new MediaRecorder(nextStream, recorderOptions); mediaRisk('audio_visual_recorder_created', 'info', null, {mime_type: mimeType || 'browser_default'}); } catch (error) {
                        nextStream.getTracks().forEach(function (track) { track.stop(); });
                        mediaRisk('audio_visual_recorder_failed', 'risk', null, {mime_type: mimeType || 'browser_default', reason: error && (error.name || error.message) || 'recorder_error'});
                        mediaRisk('recording_error', 'risk', null, { reason: error.message || 'recorder_error' });
                        throw error;
                    }
                    mediaRecorder.ondataavailable = function (event) { queueChunk(event.data); };
                    mediaRecorder.onerror = function () { mediaRisk('recording_error', 'risk', null, {}); };
                    // Quince segundos equilibra frecuencia de envío y tamaño de fragmento.
                    mediaRecorder.start(15000);
                    $form.data('audio-visual-stream', nextStream).data('audio-visual-recorder', mediaRecorder);
                }

                var cameraTrack = stream.getVideoTracks()[0];
                var microphoneTrack = stream.getAudioTracks()[0];
                if (!cameraTrack || cameraTrack.readyState !== 'live') throw new Error('camera_not_operational');
                if (!microphoneTrack || microphoneTrack.readyState !== 'live') throw new Error('microphone_not_operational');
                setAudioVisualCheck($form, 'camera', 'Cámara', 'ok', 'Acceso concedido y cámara activa.');
                setAudioVisualCheck($form, 'microphone', 'Micrófono', 'ok', 'Acceso concedido y micrófono activo.');
                var preview = $form.find('[data-audio-visual-preview]').first()[0];
                if (preview) { preview.srcObject = stream; preview.classList.remove('d-none'); preview.play().catch(function () {}); }
                $form.find('[data-audio-visual-status]').removeClass('d-none alert-secondary alert-danger').addClass('alert-success').text('Cámara y micrófono activados correctamente. Verificando captura de pantalla.');
                screenController.onInterrupted = function (reason) {
                    mediaRisk(reason, 'risk', null, { source: screenController.source });
                    setAudioVisualCheck($form, 'screen', 'Captura', 'error', 'La selección de pantalla se interrumpió.');
                    $form.addClass('is-audio-visual-interrupted');
                    if (permissionPolicy === 'pause' || interruptionPolicy === 'pause') setAudioVisualSecurityPause(true, 'La captura de pantalla se interrumpió. Vuelve a autorizarla para continuar.');
                    if (permissionPolicy === 'block' || interruptionPolicy === 'block' || permissionPolicy === 'pause' || interruptionPolicy === 'pause') $form.find('[data-test-submit-actions] button').prop('disabled', true);
                };
                screenController.onFailure = function (reason, error) {
                    var detail = error && (error.message || error.name) || 'capture_failed';
                    mediaRisk(reason, 'risk', null, { source: screenController.source || 'unknown', detail: detail });
                    // Un fallo puntual de generación/subida no implica que la
                    // pista getDisplayMedia haya terminado. Esa interrupción
                    // real se atiende exclusivamente en onInterrupted.
                    setAudioVisualCheck($form, 'screen', 'Captura', 'warning', 'Una captura puntual no pudo guardarse; la captura continúa activa.');
                    $form.find('[data-audio-visual-status]').removeClass('alert-success alert-danger').addClass('alert-warning').text('Una captura puntual no pudo guardarse. La captura de pantalla continúa activa.');
                };
                screenCapturePromise = screenCapturePromise.then(function () { return screenController.validate(); }).then(function () {
                    if (screenController.source === 'canvas' && !screenController.fallbackLogged) {
                        screenController.fallbackLogged = true;
                        mediaRisk('screen_capture_fallback_canvas', 'attention', null, {reason: screenController.fallbackReason || 'screen_capture_unavailable'});
                    }
                    mediaStartedAt = Date.now();
                    startRecorderForStream(stream);
                    $form.data('audio-visual-upload-chain', function () { return uploadChain; }).data('audio-visual-started-at', mediaStartedAt);
                    screenController.startPeriodic();
                    return true;
                }).catch(function (error) {
                    setAudioVisualReconnectStatus($form, 'screen', error && error.message === 'full_screen_required' ? 'debes seleccionar toda la pantalla' : 'no disponible');
                    $form.find('[data-audio-visual-status]').removeClass('alert-success alert-secondary').addClass('alert-danger').text(error && error.message === 'full_screen_required' ? 'Selecciona “Toda la pantalla” para continuar.' : 'No se pudo activar la captura de pantalla.');
                    setAudioVisualSecurityPause(true, 'La captura de pantalla no está disponible. Revisa el permiso indicado y vuelve a intentarlo.');
                    throw error;
                });
                audioVisualReconnect = function () {
                    if (screenController) screenController.stop();
                    setAudioVisualCheck($form, 'screen', 'Captura', 'pending', 'Solicitando acceso a pantalla completa…');
                    var screenRecovery = screenController.start();
                    return screenRecovery.then(function () { return requestAudioVisualDevices($form); }).then(function (newStream) {
                        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
                        if (activeStream) activeStream.getTracks().forEach(function (track) { track.stop(); });
                        startRecorderForStream(newStream);
                        mediaRisk('audio_visual_reconnected', 'info', null, { source: 'user_action' });
                        $form.removeClass('is-audio-visual-interrupted');
                        setAudioVisualSecurityPause(false);
                        if (screenController) screenController.startPeriodic();
                        if (permissionPolicy === 'block' || permissionPolicy === 'pause' || interruptionPolicy === 'block' || interruptionPolicy === 'pause') {
                            $form.find('[data-test-submit-actions] button').prop('disabled', false);
                        }
                        return true;
                    }).catch(function (error) {
                        mediaRisk('audio_visual_reconnect_failed', 'risk', null, { reason: error.name || 'permission_denied' });
                        setAudioVisualSecurityPause(true, 'No se pudo reconectar. Revisa los permisos y vuelve a intentarlo.');
                        throw error;
                    });
                };
                $form.data('audio-visual-reconnect', function () { return audioVisualReconnect ? audioVisualReconnect() : Promise.reject(new Error('reconnect_unavailable')); });

                var audioContext = null;
                var analyser = null;
                var voiceTimer = null;
                try {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    if (audioContext.state === 'suspended' && typeof audioContext.resume === 'function') {
                        audioContext.resume().catch(function () {});
                    }
                    var audioSource = audioContext.createMediaStreamSource(stream);
                    analyser = audioContext.createAnalyser();
                    analyser.fftSize = 1024;
                    audioSource.connect(analyser);
                    var timeBuffer = new Uint8Array(analyser.fftSize);
                    var frequencyBuffer = new Uint8Array(analyser.frequencyBinCount);
                    var lastVoiceRisk = 0;
                    var lastVoiceSignal = 0;
                    voiceTimer = window.setInterval(function () {
                        analyser.getByteTimeDomainData(timeBuffer);
                        analyser.getByteFrequencyData(frequencyBuffer);
                        var sum = 0;
                        for (var i = 0; i < timeBuffer.length; i++) { var centered = (timeBuffer[i] - 128) / 128; sum += centered * centered; }
                        var rms = Math.sqrt(sum / timeBuffer.length);
                        var strongBands = 0;
                        var minBin = Math.max(1, Math.floor(85 * analyser.fftSize / audioContext.sampleRate));
                        var maxBin = Math.min(frequencyBuffer.length - 2, Math.ceil(3400 * analyser.fftSize / audioContext.sampleRate));
                        for (var bin = minBin; bin <= maxBin; bin++) {
                            if (frequencyBuffer[bin] > 160 && frequencyBuffer[bin] >= frequencyBuffer[bin - 1] && frequencyBuffer[bin] >= frequencyBuffer[bin + 1]) strongBands++;
                        }
                        if (rms > 0.08 && strongBands >= 4 && Date.now() - lastVoiceRisk > 8000) {
                            lastVoiceRisk = Date.now();
                            lastVoiceSignal = Date.now();
                            mediaRisk('multiple_voice_possible', voicePolicy === 'log' ? 'info' : 'attention', null, { rms: Number(rms.toFixed(4)), strong_bands: strongBands, source: 'browser_preliminary', configured_action: voicePolicy });
                            showNotification('warning', 'Se detectó una posible multiplicidad de voces. El evento quedó registrado como atención.');
                        }
                        if (voicePolicy === 'pause' && $form.hasClass('is-audio-visual-interrupted') && Object.keys(interruptedTracks).length === 0 && Date.now() - lastVoiceSignal > 5000) {
                            $form.removeClass('is-audio-visual-interrupted');
                            $form.find('[data-test-submit-actions] button').prop('disabled', false);
                        }
                    }, 1000);
                } catch (error) {
                    mediaRisk('voice_analysis_unavailable', 'attention', null, { reason: error.message || 'audio_context_error' });
                }
                $form.data('audio-visual-cleanup', function () {
                    if (voiceTimer) window.clearInterval(voiceTimer);
                    if (audioContext) audioContext.close().catch(function () {});
                    activeStream.getTracks().forEach(function (track) { track.stop(); });
                    screenController.stop();
                });
                return true;
            });
        }

        function finalizeAudioVisual() {
            if (!audioVisualMode) return Promise.resolve();
            var recorder = $form.data('audio-visual-recorder');
            var uploadChain = $form.data('audio-visual-upload-chain');
            var finalizeUrl = $form.attr('data-media-finalize-url') || '';
            var evidenceId = $form.attr('data-media-evidence-id') || '';
            var uploadFailurePolicy = $form.attr('data-audio-visual-upload-failure-policy') || 'continue';
            if (!recorder || !finalizeUrl || !evidenceId) return Promise.resolve();
            if (screenController && screenController.stopScheduling) {
                screenController.stopScheduling();
            }
            return new Promise(function (resolve, reject) {
                var stopped = false;
                recorder.addEventListener('stop', function () {
                    if (stopped) return;
                    stopped = true;
                    function requestFinalize(attempt) {
                        var payload = new URLSearchParams();
                        payload.append('csrf_token', csrfToken);
                        payload.append('evidence_id', evidenceId);
                        payload.append('duration_seconds', String(Math.max(0, Math.round((Date.now() - ($form.data('audio-visual-started-at') || Date.now())) / 1000))));
                        return window.fetch(finalizeUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json' }, body: payload.toString() }).then(function (response) {
                            return response.json().catch(function () { return { ok: false, reason: 'invalid_finalize_response' }; }).then(function (result) {
                                if (!result.ok && attempt < 3) {
                                    mediaRisk('upload_retry_requested', 'attention', null, { attempt: attempt, reason: result.reason || 'finalize_failed' });
                                    return requestFinalize(attempt + 1);
                                }
                                return result;
                            });
                        }).catch(function (error) {
                            if (attempt < 3) {
                                mediaRisk('upload_retry_requested', 'attention', null, { attempt: attempt, reason: error.message || 'network_error' });
                                return requestFinalize(attempt + 1);
                            }
                            return { ok: false, reason: error.message || 'network_error' };
                        });
                    }
                    Promise.resolve(uploadChain ? uploadChain() : null).then(function () {
                        return screenController && screenController.flushUploads
                            ? screenController.flushUploads().catch(function (error) {
                                mediaRisk('screen_capture_upload_failed', 'risk', null, {
                                    reason: error && error.message || 'capture_upload_failed',
                                    attempts: 3
                                });
                                return null;
                            })
                            : null;
                    }).then(function () {
                        return requestFinalize(1);
                    }).then(function (result) {
                        var cleanup = $form.data('audio-visual-cleanup');
                        if (cleanup) cleanup();
                        if (!result || !result.ok) {
                            var finalizeError = new Error((result && result.reason) || 'audio_visual_finalize_failed');
                            finalizeError.payload = result || {};
                            throw finalizeError;
                        }
                        return result;
                    }).then(function (result) {
                        resolve();
                        return result;
                    }, function (error) {
                        reject(error);
                    });
                });
                if (recorder.state !== 'inactive') recorder.stop(); else resolve();
            });
        }

        $form.data('audio-visual-init', initAudioVisualCapture).data('audio-visual-finalize', finalizeAudioVisual);

        $form.on('click.testAudioVisual', '[data-audio-visual-reconnect]', function () {
            var $button = $(this);
            var reconnect = $form.data('audio-visual-reconnect');
            if (!reconnect) return;
            $button.prop('disabled', true).text('Solicitando permisos...');
            Promise.resolve(reconnect()).then(function () {
                $button.prop('disabled', false).text('Reintentar conexión audiovisual');
                showNotification('success', 'El control audiovisual se recuperó. Puedes continuar la evaluación.');
            }).catch(function () {
                $button.prop('disabled', false).text('Reintentar conexión audiovisual');
                showNotification('warning', 'No se pudo reconectar. Revisa los permisos de cámara y micrófono.');
            });
        });

        function detectDeviceSnapshot() {
            var nav = window.navigator || {};
            var ua = String(nav.userAgent || '');
            var uaData = nav.userAgentData || null;
            var platform = String((uaData && uaData.platform) || nav.platform || '');
            var maxTouchPoints = Number(nav.maxTouchPoints || 0);
            var mobile = Boolean((uaData && uaData.mobile) || /Mobi|Android|iPhone|iPod|Windows Phone/i.test(ua));
            var tablet = /iPad|Tablet/i.test(ua) || (maxTouchPoints > 1 && /MacIntel/i.test(platform));
            var os = 'Desconocido';
            var browser = 'Desconocido';
            var connection = nav.connection || nav.mozConnection || nav.webkitConnection || null;
            var timezone = '';

            if (/Windows/i.test(platform) || /Windows NT/i.test(ua)) {
                os = 'Windows';
            } else if (/Android/i.test(ua)) {
                os = 'Android';
            } else if (/iPhone|iPad|iPod/i.test(ua) || tablet && /Mac/i.test(platform)) {
                os = 'iOS';
            } else if (/Mac/i.test(platform) || /Mac OS X/i.test(ua)) {
                os = 'macOS';
            } else if (/CrOS/i.test(ua)) {
                os = 'ChromeOS';
            } else if (/Linux/i.test(platform) || /Linux/i.test(ua)) {
                os = 'Linux';
            }

            if (/Edg\//i.test(ua)) {
                browser = 'Edge';
            } else if (/OPR\//i.test(ua)) {
                browser = 'Opera';
            } else if (/Firefox\//i.test(ua)) {
                browser = 'Firefox';
            } else if (/Chrome\//i.test(ua) || /CriOS\//i.test(ua)) {
                browser = 'Chrome';
            } else if (/Safari\//i.test(ua)) {
                browser = 'Safari';
            }

            try {
                timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (error) {}

            return {
                device_type: tablet ? 'tablet' : (mobile ? 'telefono' : 'pc'),
                os: os,
                browser: browser,
                browser_language: String(nav.language || ''),
                timezone: timezone,
                screen: (window.screen ? window.screen.width + 'x' + window.screen.height : ''),
                viewport: window.innerWidth + 'x' + window.innerHeight,
                pixel_ratio: String(window.devicePixelRatio || 1),
                touch_points: String(maxTouchPoints),
                hardware_concurrency: String(nav.hardwareConcurrency || ''),
                device_memory: String(nav.deviceMemory || ''),
                connection: connection && connection.effectiveType ? String(connection.effectiveType) : ''
            };
        }

        function postActivity(eventType, metadata) {
            var payload = new URLSearchParams();
            payload.append('csrf_token', csrfToken);
            payload.append('event_type', eventType);
            payload.append('block_number', String(currentBlock()));
            payload.append('remaining_seconds', String(remainingSeconds()));
            payload.append('url_path', window.location.pathname);

            $.each(metadata || {}, function (key, value) {
                if (value !== null && typeof value !== 'undefined' && value !== '') {
                    payload.append(key, String(value));
                }
            });

            window.fetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString(),
                keepalive: true
            }).catch(function () {});
        }

        function sendHeartbeat() {
            postActivity('heartbeat', {});
        }

        sendHeartbeat();
        window.setInterval(sendHeartbeat, 25000);

        if ($form.attr('data-activity-tracking') !== '1' && !supervisedMode) {
            return;
        }

        postActivity('device_snapshot', detectDeviceSnapshot());

        var minEventGap = 3000;
        var inactivityMs = 60000;
        var lastSentAt = {};
        var hiddenAt = document.hidden ? Date.now() : null;
        var blurredAt = null;
        var inactive = false;
        var lastInteractionAt = Date.now();
        var inactivityTimer = null;

        function currentBlock() {
            return parseInt($form.attr('data-current-block'), 10) || 0;
        }

        function remainingSeconds() {
            var value = parseInt($form.attr('data-remaining-seconds'), 10);
            return isNaN(value) ? '' : value;
        }

        function sendActivity(eventType, metadata) {
            var now = Date.now();
            if (lastSentAt[eventType] && (now - lastSentAt[eventType]) < minEventGap) {
                return;
            }
            lastSentAt[eventType] = now;
            postActivity(eventType, metadata);
        }

        function resetInactivityTimer() {
            var now = Date.now();
            var inactiveSeconds = Math.round((now - lastInteractionAt) / 1000);
            if (inactive) {
                inactive = false;
                sendActivity('activity_resumed', {
                    inactive_seconds: inactiveSeconds
                });
            }

            lastInteractionAt = now;
            window.clearTimeout(inactivityTimer);
            inactivityTimer = window.setTimeout(function () {
                inactive = true;
                sendActivity('inactive_detected', {
                    inactive_seconds: Math.round((Date.now() - lastInteractionAt) / 1000)
                });
            }, inactivityMs);
        }

        $(document).on('visibilitychange.testActivity', function () {
            if (document.hidden) {
                saveDraftAnswers($form, { immediate: true, beacon: true, reason: 'tab_hidden' });
                hiddenAt = Date.now();
                sendActivity('tab_hidden', { visible: false });
                return;
            }

            var hiddenSeconds = hiddenAt ? Math.round((Date.now() - hiddenAt) / 1000) : 0;
            hiddenAt = null;
            sendActivity('tab_visible', {
                visible: true,
                hidden_seconds: hiddenSeconds
            });
            resetInactivityTimer();
        });

        $(window).on('blur.testActivity', function () {
            saveDraftAnswers($form, { immediate: true, reason: 'window_blurred' });
            blurredAt = Date.now();
            sendActivity('window_blurred', { source: 'window' });
        });

        $(window).on('focus.testActivity', function () {
            var blurredSeconds = blurredAt ? Math.round((Date.now() - blurredAt) / 1000) : 0;
            blurredAt = null;
            sendActivity('window_focused', {
                source: 'window',
                hidden_seconds: blurredSeconds
            });
            resetInactivityTimer();
        });

        $(document).on('mousemove.testActivity keydown.testActivity click.testActivity scroll.testActivity input.testActivity touchstart.testActivity touchmove.testActivity pointerdown.testActivity pointermove.testActivity', resetInactivityTimer);

        $(document).on('fullscreenchange.testActivity webkitfullscreenchange.testActivity', function () {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                sendActivity('fullscreen_exited', { reason: 'fullscreenchange' });
            }
        });

        resetInactivityTimer();

        if (!supervisedMode) {
            return;
        }

        var $gate = $form.find('[data-supervised-gate]');
        var $exitWarning = $form.find('[data-supervised-exit-warning]');
        var fullscreenAttempt = 0;
        var supervisedStarted = false;
        var suppressFullscreenExit = false;

        function fullscreenShell() {
            return $form.closest('.test-taking-shell')[0];
        }

        function fullscreenStage() {
            return $form.closest('[data-test-fullscreen-stage]')[0] || fullscreenShell() || $form[0];
        }

        function fullscreenTarget() {
            return fullscreenStage() || document.documentElement;
        }

        function fullscreenScrollPane() {
            return $form.find('[data-test-fullscreen-scroll]')[0] || fullscreenTarget();
        }

        function scrollFullscreenPaneToTop(behavior) {
            var pane = fullscreenScrollPane();
            if (!pane) {
                return;
            }
            if (pane.scrollTo) {
                pane.scrollTo({
                    top: 0,
                    behavior: behavior || 'auto'
                });
                return;
            }
            pane.scrollTop = 0;
        }

        function scrollFullscreenPaneBy(delta) {
            var pane = fullscreenScrollPane();
            if (!pane || !delta || pane.scrollHeight <= pane.clientHeight) {
                return false;
            }

            var before = pane.scrollTop;
            pane.scrollTop = Math.max(0, Math.min(pane.scrollHeight - pane.clientHeight, before + delta));

            return pane.scrollTop !== before;
        }

        function fullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        function fullscreenSupported() {
            var target = fullscreenTarget();
            return !!(target.requestFullscreen || target.webkitRequestFullscreen || document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen);
        }

        function requestFormFullscreen(eventType) {
            fullscreenAttempt++;
            if (!fullscreenSupported()) {
                sendActivity('fullscreen_unavailable', {
                    supported: false,
                    attempt: fullscreenAttempt
                });
                return $.Deferred().reject('unavailable').promise();
            }

            var fullscreenStageElement = fullscreenTarget();
            var target = fullscreenStageElement.requestFullscreen || fullscreenStageElement.webkitRequestFullscreen
                ? fullscreenStageElement
                : document.documentElement;
            var request = target.requestFullscreen || target.webkitRequestFullscreen;
            var result;

            try {
                result = request.call(target);
            } catch (error) {
                sendActivity('fullscreen_failed', {
                    reason: error && error.name ? error.name : 'exception',
                    attempt: fullscreenAttempt
                });
                return $.Deferred().reject('failed').promise();
            }

            return $.when(result).then(function () {
                $(fullscreenStageElement).addClass('is-fullscreen-stage');
                sendActivity(eventType || 'fullscreen_entered', {
                    supported: true,
                    attempt: fullscreenAttempt
                });
                return true;
            }, function (error) {
                sendActivity('fullscreen_denied', {
                    reason: error && error.name ? error.name : 'denied',
                    attempt: fullscreenAttempt
                });
                return $.Deferred().reject('denied').promise();
            });
        }

        function startSupervisedEvaluation() {
            var pane = fullscreenScrollPane();
            supervisedStarted = true;
            $gate.addClass('d-none');
            $form.addClass('is-supervised-active');
            scrollFullscreenPaneToTop('auto');
            if (pane && pane.focus) {
                try {
                    pane.focus({ preventScroll: true });
                } catch (error) {
                    pane.focus();
                }
            }
            resetInactivityTimer();
        }

        function showContinueWithoutFullscreen() {
            $gate.find('[data-supervised-continue]').removeClass('d-none');
        }

        $form.addClass('is-supervised-locked');

        var lastTouchY = null;

        function isFullscreenStageActive() {
            var stage = fullscreenStage();
            var activeFullscreen = fullscreenElement();
            return !!(stage && (activeFullscreen === stage || $(stage).hasClass('is-fullscreen-stage')));
        }

        function elementCanScroll(element, delta) {
            if (!element || element === document || element === window) {
                return false;
            }

            var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
            var overflowY = style ? style.overflowY : '';
            var scrollable = /(auto|scroll|overlay)/.test(overflowY);
            if (!scrollable || element.scrollHeight <= element.clientHeight) {
                return false;
            }

            return delta < 0 ? element.scrollTop > 0 : element.scrollTop + element.clientHeight < element.scrollHeight;
        }

        function targetHasOwnScroll(target, delta) {
            var node = target;
            var pane = fullscreenScrollPane();
            while (node && node !== document && node !== pane) {
                if (elementCanScroll(node, delta)) {
                    return true;
                }
                node = node.parentElement;
            }

            return false;
        }

        function installFullscreenScrollHandlers() {
            var stage = fullscreenStage();
            if (!stage || stage.dataset.fullscreenScrollReady === '1') {
                return;
            }

            stage.dataset.fullscreenScrollReady = '1';

            stage.addEventListener('wheel', function (event) {
                if (!isFullscreenStageActive()) {
                    return;
                }

                var delta = event.deltaY || 0;
                if (targetHasOwnScroll(event.target, delta)) {
                    return;
                }

                if (scrollFullscreenPaneBy(delta)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, { passive: false, capture: true });

            stage.addEventListener('touchstart', function (event) {
                if (!isFullscreenStageActive()) {
                    return;
                }

                var touch = event.touches && event.touches[0];
                lastTouchY = touch ? touch.clientY : null;
            }, { passive: true, capture: true });

            stage.addEventListener('touchmove', function (event) {
                if (!isFullscreenStageActive() || lastTouchY === null) {
                    return;
                }

                var touch = event.touches && event.touches[0];
                if (!touch) {
                    return;
                }

                var delta = lastTouchY - touch.clientY;
                lastTouchY = touch.clientY;
                if (targetHasOwnScroll(event.target, delta)) {
                    return;
                }

                if (scrollFullscreenPaneBy(delta)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, { passive: false, capture: true });

            stage.addEventListener('touchend', function () {
                lastTouchY = null;
            }, { passive: true, capture: true });
        }

        installFullscreenScrollHandlers();

        $(fullscreenStage()).on('click.testSupervisedScrollTools', '[data-test-scroll-step]', function () {
            if (!isFullscreenStageActive()) {
                return;
            }

            var pane = fullscreenScrollPane();
            var direction = parseInt($(this).attr('data-test-scroll-step') || '0', 10);
            var step = pane ? Math.max(160, Math.round(pane.clientHeight * 0.82)) : 480;
            scrollFullscreenPaneBy(direction * step);
        });

        $(document).on('keydown.testSupervisedScroll', function (event) {
            if (!isFullscreenStageActive()) {
                return;
            }

            var target = event.target;
            if (target && /^(input|textarea|select)$/i.test(target.tagName || '')) {
                return;
            }

            var key = event.key || '';
            var pane = fullscreenScrollPane();
            var step = pane ? Math.max(80, Math.round(pane.clientHeight * 0.82)) : 480;
            var delta = 0;

            if (key === 'ArrowDown') {
                delta = 80;
            } else if (key === 'ArrowUp') {
                delta = -80;
            } else if (key === 'PageDown' || key === ' ') {
                delta = step;
            } else if (key === 'PageUp') {
                delta = -step;
            } else if (key === 'Home') {
                scrollFullscreenPaneToTop('auto');
                event.preventDefault();
                return;
            } else if (key === 'End' && pane) {
                pane.scrollTop = pane.scrollHeight;
                event.preventDefault();
                return;
            }

            if (delta && scrollFullscreenPaneBy(delta)) {
                event.preventDefault();
            }
        });

        $form.on('click.testSupervised', '[data-supervised-start]', function () {
            var startMedia = audioVisualMode && $form.data('audio-visual-init') ? $form.data('audio-visual-init')() : $.Deferred().resolve().promise();
            startMedia.then(function () {
                requestFormFullscreen('fullscreen_entered').then(startSupervisedEvaluation, function () {
                    showContinueWithoutFullscreen();
                    showNotification('warning', 'No se pudo activar pantalla completa. Puedes reintentar o continuar con advertencia registrada.');
                });
            }).catch(function () {
                $form.find('[data-audio-visual-status]').removeClass('d-none alert-secondary alert-success').addClass('alert-danger').text('No se pudo activar la cámara y el micrófono. Revisa los permisos del navegador e inténtalo nuevamente.');
            });
        });

        $form.on('click.testSupervised', '[data-supervised-continue]', function () {
            sendActivity('fullscreen_failed', {
                reason: 'continued_without_fullscreen',
                attempt: fullscreenAttempt
            });
            startSupervisedEvaluation();
        });

        if (audioVisualMode) {
            var $consent = $form.find('[data-audio-visual-consent]');
            var $startButton = $form.find('[data-supervised-start]');
            $startButton.prop('disabled', !$consent.is(':checked'));
            $consent.on('change.audioVisual', function () {
                $startButton.prop('disabled', !this.checked);
            });
        }

        $form.on('submit.testAudioVisual', function (event) {
            if (!audioVisualMode || $form.data('audio-visual-finalizing') || $form.data('audio-visual-finalized')) {
                return;
            }
            var submitter = event.originalEvent && event.originalEvent.submitter;
            var action = submitter && submitter.name === 'test_action' ? submitter.value : '';
            if (action.indexOf('save_block') === 0) {
                return;
            }
            event.preventDefault();
            $form.data('audio-visual-finalizing', true);
            $form.find('button[type="submit"]').prop('disabled', true);
            Promise.resolve(saveDraftAnswers($form, { immediate: true, force: true, reason: 'before_audio_visual_finalize' }))
                .then(function () {
                    var finalData = new FormData($form[0]);
                    var postedActions = finalData.getAll('test_action');
                    finalData.delete('test_action');
                    var finalAction = postedActions.length ? String(postedActions[postedActions.length - 1]) : (action || 'complete');
                    finalData.set('test_action', finalAction);
                    return window.fetch($form.attr('action') || window.location.href, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: finalData
                    }).then(function (response) {
                        if (!response.ok) throw new Error('answers_save_failed');
                        return response;
                    });
                })
                .then(function (response) {
                    return $form.data('audio-visual-finalize')
                        ? Promise.resolve($form.data('audio-visual-finalize')()).then(function () { return response; })
                        : response;
                })
                .then(function (response) {
                    $form.data('audio-visual-finalized', true).data('test-allow-exit', true);
                    window.location.href = String($form.attr('data-my-tests-url') || window.AppBackUrl || '/my-tests');
                })
                .catch(function (error) {
                    $form.removeData('test-saving-exit').removeData('test-allow-exit');
                    $form.data('audio-visual-finalizing', false).find('button[type="submit"]').prop('disabled', false);
                    showNotification('error', error && error.message === 'answers_save_failed'
                        ? 'No se pudieron guardar las respuestas. El video no se enviara hasta completar el guardado.'
                        : 'No se pudo cerrar la evidencia audiovisual. Intenta nuevamente.');
                });
        });

        $form.on('click.testSupervised', '[data-supervised-reenter]', function () {
            requestFormFullscreen('fullscreen_reentered').then(function () {
                var pane = fullscreenScrollPane();
                $exitWarning.addClass('d-none');
                $form.removeClass('is-supervised-paused');
                if (pane && pane.focus) {
                    try {
                        pane.focus({ preventScroll: true });
                    } catch (error) {
                        pane.focus();
                    }
                }
            }, function () {
                showNotification('warning', 'No se pudo volver a pantalla completa en este dispositivo o navegador.');
            });
        });

        $form.on('click.testSupervised', '[data-supervised-dismiss-warning]', function () {
            sendActivity('fullscreen_failed', {
                reason: 'dismissed_reentry_warning',
                attempt: fullscreenAttempt
            });
            $exitWarning.addClass('d-none');
            $form.removeClass('is-supervised-paused');
        });

        $(document).on('fullscreenchange.testSupervised webkitfullscreenchange.testSupervised', function () {
            var activeFullscreen = fullscreenElement();
            if (!activeFullscreen) {
                $('[data-test-fullscreen-stage].is-fullscreen-stage').removeClass('is-fullscreen-stage');
            }
            if (!supervisedStarted || suppressFullscreenExit || activeFullscreen) {
                suppressFullscreenExit = false;
                return;
            }

            $form.addClass('is-supervised-paused');
            $exitWarning.removeClass('d-none');
            showNotification('warning', 'Saliste de pantalla completa. Este evento quedo registrado.');
        });

        $(document).on('keydown.testSupervised', function (event) {
            var key = event.key || '';
            var lowerKey = key.toLowerCase();
            var modifier = event.ctrlKey || event.metaKey;
            var combo = [
                event.ctrlKey ? 'Ctrl' : '',
                event.metaKey ? 'Meta' : '',
                event.shiftKey ? 'Shift' : '',
                event.altKey ? 'Alt' : '',
                key
            ].filter(Boolean).join('+');

            function block(eventType) {
                event.preventDefault();
                event.stopPropagation();
                sendActivity(eventType, {
                    key: key,
                    combo: combo
                });
                return false;
            }

            if (key === 'PrintScreen') {
                sendActivity('suspicious_key_printscreen', { key: key, combo: combo });
                return;
            }
            if (modifier && lowerKey === 'p') {
                return block('suspicious_key_print');
            }
            if (modifier && lowerKey === 's') {
                return block('suspicious_key_save');
            }
            if (modifier && lowerKey === 'c') {
                return block('suspicious_key_copy');
            }
            if (key === 'F12' || (modifier && event.shiftKey && ['i', 'j', 'c'].indexOf(lowerKey) !== -1) || (event.metaKey && event.altKey && lowerKey === 'i')) {
                return block('suspicious_key_devtools');
            }
        });

        $(window).on('beforeprint.testSupervised', function () {
            sendActivity('print_blocked', { source: 'beforeprint' });
        });

        $form.on('contextmenu.testSupervised', function (event) {
            event.preventDefault();
            sendActivity('context_menu_blocked', { source: 'contextmenu' });
        });

        $form.on('copy.testSupervised cut.testSupervised paste.testSupervised', function (event) {
            var eventMap = {
                copy: 'copy_blocked',
                cut: 'cut_blocked',
                paste: 'paste_blocked'
            };
            event.preventDefault();
            sendActivity(eventMap[event.type] || 'copy_blocked', { source: event.type });
        });

        $form.on('dragstart.testSupervised', function (event) {
            event.preventDefault();
            sendActivity('drag_blocked', { source: 'dragstart' });
        });
    }

    $('.test-taking-shell form[data-test-taking-form]').each(function () {
        initTestActivityTracking($(this));
    });

    $('[data-test-countdown]').each(function () {
        var $timer = $(this);
        var $display = $timer.find('[data-test-countdown-display]');
        var $form = $timer.closest('form');
        var remaining = parseInt($timer.attr('data-test-countdown'), 10) || 0;
        var submittedByTimer = false;

        function submitExpiredEvaluation() {
            if (submittedByTimer || !$form.length) {
                return;
            }

            submittedByTimer = true;
            showNotification('warning', $form.attr('data-expired-message') || 'El tiempo finalizo. Se guardaron las respuestas registradas hasta este momento.');
            $form.data('test-allow-exit', true);
            $form.find('button[type="submit"]').prop('disabled', true);
            $form.find('input[name="test_action"]').remove();
            $('<input>', {
                type: 'hidden',
                name: 'test_action',
                value: 'time_expired'
            }).appendTo($form);

            window.setTimeout(function () {
                markFormProcessing($form);
                if (typeof $form[0].requestSubmit === 'function') {
                    $form[0].requestSubmit();
                } else {
                    $form[0].submit();
                }
            }, 1200);
        }

        function renderCountdown() {
            var minutes = Math.floor(Math.max(0, remaining) / 60);
            var seconds = Math.max(0, remaining) % 60;
            var label = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            $form.attr('data-remaining-seconds', String(Math.max(0, remaining)));
            $display.text(label);
            $form.find('[data-test-countdown-mirror]').text(label);
            $timer.toggleClass('is-warning', remaining <= 60);
            $form.find('.supervised-gate-countdown').toggleClass('is-warning', remaining <= 60);

            if (remaining <= 0) {
                window.clearInterval(intervalId);
                $timer.addClass('is-expired');
                $form.find('.supervised-gate-countdown').addClass('is-expired');
                submitExpiredEvaluation();
                return;
            }

            var mediaPolicy = $form.find('[data-audio-visual-policy]').val() || '';
            var voicePolicy = $form.attr('data-audio-visual-voice-policy') || 'warn';
            if ($form.hasClass('is-audio-visual-interrupted') && (mediaPolicy === 'pause' || voicePolicy === 'pause')) {
                return;
            }

            remaining--;
        }

        var intervalId = window.setInterval(renderCountdown, 1000);
        renderCountdown();
    });

    function initEvaluationAnswerFlow($form) {
        function questionHasAnswer($question) {
            var radioGroups = {};
            $question.find('input[type="radio"]').each(function () {
                radioGroups[this.name] = true;
            });
            var radioGroupNames = Object.keys(radioGroups);
            if (radioGroupNames.length) {
                return radioGroupNames.every(function (name) {
                    return $question.find('input[type="radio"][name="' + name.replace(/"/g, '\\"') + '"]:checked').length > 0;
                });
            }

            var answered = false;
            $question.find('input, textarea, select').each(function () {
                var $field = $(this);
                if ($field.is(':radio, :checkbox')) {
                    answered = answered || $field.is(':checked');
                } else {
                    answered = answered || $.trim(String($field.val() || '')) !== '';
                }
            });
            return answered;
        }

        function focusPending() {
            var $pending = $form.find('fieldset[data-question-required]').filter(function () { return !questionHasAnswer($(this)); }).first();
            if (!$pending.length) return;
            $pending.addClass('is-incomplete');
            $pending[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            var $focus = $pending.find('input, textarea, select').first();
            if ($focus.length) window.setTimeout(function () { $focus.trigger('focus'); }, 250);
        }

        $form.on('click.evaluationIncomplete', 'button[type="submit"], input[type="submit"]', function () {
            $form.data('evaluation-last-submitter', this);
        });

        $form.on('submit.evaluationIncomplete', function (event) {
            if ($form.data('evaluation-time-expired-submitting') || $form.data('allow-incomplete-submit')) return;
            var submitter = event.originalEvent && event.originalEvent.submitter;
            submitter = submitter || $form.data('evaluation-last-submitter');
            var action = submitter && submitter.name === 'evaluation_survey_action' ? submitter.value : 'complete';
            if (action !== 'complete') return;
            if ($form.data('evaluation-countdown-started') && ($form.data('evaluation-remaining-now') || 0) <= 0) return;
            var $pending = $form.find('fieldset[data-question-required]').filter(function () { return !questionHasAnswer($(this)); });
            if (!$pending.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            appSwalConfirm({
                title: $form.attr('data-incomplete-confirm-title') || 'Evaluacion incompleta',
                text: $form.attr('data-incomplete-confirm-message') || 'Aun quedan preguntas sin responder. Deseas contestarlas antes de finalizar?',
                icon: 'warning',
                confirmButtonText: $form.attr('data-incomplete-confirm-button') || 'Si, contestar pendientes',
                cancelButtonText: $form.attr('data-incomplete-cancel-button') || 'No, guardar y finalizar',
                focusCancel: true
            }).then(function (goToPending) {
                if (goToPending) {
                    focusPending();
                    return;
                }
                $form.data('allow-incomplete-submit', true);
                $form.find('input[name="evaluation_survey_action"][type="hidden"]').remove();
                $('<input>', { type: 'hidden', name: 'evaluation_survey_action', value: 'complete_incomplete' }).appendTo($form);
                if (typeof $form[0].requestSubmit === 'function') $form[0].requestSubmit();
                else $form[0].submit();
            });
        });
    }

    $('form.evaluation-take-form[data-evaluation-control-form]').each(function () { initEvaluationAnswerFlow($(this)); });

    function createStandardEvaluationProgress() {
        var $panel = $('<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-75" style="z-index: 2400" role="dialog" aria-modal="true" aria-labelledby="evaluation-standard-save-progress-title"><div class="bg-white rounded-3 shadow p-4" style="width:min(92vw,520px)"><h2 id="evaluation-standard-save-progress-title" class="h5 mb-2">Guardando evaluación</h2><p class="mb-3" data-evaluation-standard-progress-label>Guardando respuestas…</p><div class="progress" role="progressbar" aria-label="Progreso del guardado"><div class="progress-bar progress-bar-striped progress-bar-animated" data-evaluation-standard-progress-bar style="width:10%">10%</div></div><p class="small text-muted mt-2 mb-0">No cierres ni recargues esta ventana.</p></div></div>');
        $(document.fullscreenElement || document.body).append($panel);
        return {
            update: function (percent, label) { var value = Math.max(0, Math.min(100, Math.round(percent))); $panel.find('[data-evaluation-standard-progress-bar]').css('width', value + '%').text(value + '%'); $panel.find('[data-evaluation-standard-progress-label]').text(label); },
            remove: function () { $panel.remove(); }
        };
    }

    $('form.evaluation-take-form[data-evaluation-control-form]').on('submit.evaluationStandardSave', function (event) {
        var $form = $(this);
        if ($form.attr('data-audio-visual-mode') === '1' || $form.data('evaluation-standard-submitting')) return;

        var form = this;
        var submitter = event.originalEvent && event.originalEvent.submitter;
        var action = submitter && submitter.name === 'evaluation_survey_action'
            ? String(submitter.value || '')
            : String($form.find('input[name="evaluation_survey_action"][type="hidden"]').last().val() || 'complete');
        if (!['complete', 'complete_incomplete', 'time_expired', 'process_expired'].includes(action)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        syncAnswerSnapshot($form);
        var formData = new FormData(form);
        formData.delete('evaluation_survey_action');
        formData.append('evaluation_survey_action', action);
        $form.data('evaluation-standard-submitting', true).data('test-allow-exit', true);
        clearFormProcessing($form);
        var progress = createStandardEvaluationProgress();
        $form.find('button, input, textarea, select').prop('disabled', true);
        progress.update(10, ['time_expired', 'process_expired'].includes(action) ? 'Guardando respuestas antes de cerrar…' : 'Guardando respuestas…');

        fetch(form.action || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    var error = new Error(payload.message || 'answers_save_failed');
                    error.payload = payload;
                    throw error;
                }
                return payload;
            });
        }).then(function (payload) {
            progress.update(100, 'Sus respuestas han sido guardadas con éxito');
            window.setTimeout(function () { window.location.href = payload.redirect_url || window.AppBackUrl || window.location.href; }, 250);
        }).catch(function (error) {
            progress.remove();
            $form.removeData('evaluation-standard-submitting').removeData('test-allow-exit');
            $form.find('button, input, textarea, select').prop('disabled', false);
            showNotification('error', error && error.payload && error.payload.message ? error.payload.message : 'No se pudieron guardar las respuestas. Intenta nuevamente.');
        });
    });

    $('form.evaluation-take-form[data-evaluation-control-form][data-draft-url]').each(function () {
        var $form = $(this);
        if ($form.attr('data-evaluation-preview') === '1') return;

        var abandonOnExit = function () {
            if (!$form.data('evaluation-supervised-active') || $form.data('test-allow-exit')) return;
            var prepared = draftFormData($form, 'page_exit');
            prepared.formData.set('evaluation_survey_action', 'abandon');
            if (navigator.sendBeacon && window.URLSearchParams && window.Blob) {
                var params = new URLSearchParams();
                prepared.formData.forEach(function (value, key) { params.append(key, value); });
                var url = String($form.attr('data-draft-url') || '');
                var blob = new Blob([params.toString()], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
                if (navigator.sendBeacon(url, blob)) return;
            }
            if (window.fetch) window.fetch(String($form.attr('data-draft-url') || ''), { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: new URLSearchParams(prepared.formData).toString(), keepalive: true }).catch(function () {});
        };

        var historyExitArmed = false;
        var armHistoryExitGuard = function () {
            if (historyExitArmed || !window.history || !window.history.pushState) return;
            historyExitArmed = true;
            window.history.replaceState($.extend({}, window.history.state || {}, { evaluationTaking: true }), '', window.location.href);
            window.history.pushState({ evaluationTakingGuard: true }, '', window.location.href);
        };
        $form.on('evaluation:activated', armHistoryExitGuard);
        $(window).on('popstate.evaluationDraft', function () {
            if (!$form.data('evaluation-supervised-active') || $form.data('test-allow-exit')) return;
            window.history.pushState({ evaluationTakingGuard: true }, '', window.location.href);
            confirmAudiovisualExit(false);
        });

        $(window).on('beforeunload.evaluationDraft', function (event) {
            if ($form.attr('data-audio-visual-mode') !== '1' || !$form.data('evaluation-supervised-active') || $form.data('test-allow-exit') || $form.data('evaluation-submission-in-progress')) return;
            var message = 'Si recargas o sales ahora, la evaluación se cerrará y no podrás ingresar nuevamente.';
            event.preventDefault();
            event.originalEvent.returnValue = message;
            return message;
        });

        var confirmAudiovisualExit = function (reload) {
            appSwalConfirm({
                title: 'Evaluación audiovisual en curso',
                text: 'Si recargas o sales ahora, la evaluación se cerrará y no podrás ingresar nuevamente.',
                icon: 'warning',
                confirmButtonText: 'Cerrar evaluación',
                cancelButtonText: 'Continuar respondiendo',
                focusCancel: true
            }).then(function (confirmed) {
                if (!confirmed) return;
                abandonOnExit();
                $form.data('test-allow-exit', true);
                if (reload) window.location.reload();
                else window.history.back();
            });
        };

        $(document).on('keydown.evaluationDraft', function (event) {
            if ($form.attr('data-audio-visual-mode') !== '1' || !$form.data('evaluation-supervised-active') || $form.data('test-allow-exit')) return;
            var key = String(event.key || '').toLowerCase();
            if (key === 'f5' || ((event.ctrlKey || event.metaKey) && key === 'r')) {
                event.preventDefault();
                confirmAudiovisualExit(true);
            }
        });

        $form.on('input.evaluationDraft change.evaluationDraft', 'input, textarea, select', function () {
            if ($form.attr('data-audio-visual-mode') === '1' && !$form.data('evaluation-supervised-active')) return;
            scheduleDraftSave($form, 'answer_changed');
        });

        $(window).on('pagehide.evaluationDraft', function () {
            if ($form.data('test-allow-exit')) return;
            if ($form.attr('data-audio-visual-mode') === '1' && !$form.data('evaluation-supervised-active')) return;
            if ($form.attr('data-audio-visual-mode') === '1') abandonOnExit();
            else saveDraftAnswers($form, { immediate: true, beacon: true, force: true, reason: 'page_exit' });
        });
    });

    $('form.evaluation-take-form[data-evaluation-remaining-seconds]').each(function () {
        var $form = $(this);
        if ($form.attr('data-evaluation-preview') === '1') return;
        var raw = $form.attr('data-evaluation-remaining-seconds');
        if (raw === '') return;
        var remaining = Math.max(0, parseInt(raw, 10) || 0);
        var countdownStarted = $form.attr('data-audio-visual-mode') !== '1' || !$form.find('[data-audio-visual-consent]').length;
        var deadlineAt = Date.now() + (remaining * 1000);
        $form.data('evaluation-countdown-started', countdownStarted);
        var $badge = $form.find('[data-evaluation-countdown]');
        var expired = false;
        $form.on('evaluation:activated.evaluationCountdown', function () {
            deadlineAt = Date.now() + (remaining * 1000);
        });
        function render() {
            if ($form.data('evaluation-countdown-started')) {
                remaining = Math.max(0, Math.ceil((deadlineAt - Date.now()) / 1000));
            }
            $form.data('evaluation-remaining-now', remaining);
            var hours = Math.floor(remaining / 3600);
            var minutes = Math.floor((remaining % 3600) / 60);
            var seconds = remaining % 60;
            var label = [hours, minutes, seconds].map(function (part) { return String(part).padStart(2, '0'); }).join(':');
            $badge.text(label);
            $form.toggleClass('is-time-warning', remaining <= 60);
            if (!$form.data('evaluation-countdown-started')) {
                return;
            }
            if (remaining <= 0 && !expired) {
                expired = true;
                $form.data('evaluation-time-expired-submitting', true).data('test-allow-exit', true);
                showNotification('warning', $form.attr('data-expired-message') || 'El tiempo finalizo. Se guardaron las respuestas registradas hasta este momento.');
                $('<input>', { type: 'hidden', name: 'evaluation_survey_action', value: 'time_expired' }).appendTo($form);
                window.setTimeout(function () {
                    if (typeof $form[0].requestSubmit === 'function') $form[0].requestSubmit();
                    else $form[0].submit();
                }, 1200);
                return;
            }
        }
        render();
        window.setInterval(render, 1000);
    });

    function processDeadlineRemaining($form) {
        var raw = String($form.attr('data-process-remaining-seconds') || '');
        if (raw === '') {
            return null;
        }

        var initialRemaining = parseInt(raw, 10);
        var startedAt = parseInt($form.attr('data-process-deadline-started-at'), 10);
        if (!Number.isFinite(initialRemaining)) {
            return null;
        }

        if (!Number.isFinite(startedAt)) {
            startedAt = Date.now();
            $form.attr('data-process-deadline-started-at', String(startedAt));
        }

        return Math.max(0, initialRemaining - Math.floor((Date.now() - startedAt) / 1000));
    }

    function showProcessDeadlineEnded($form, availability) {
        if ($form.data('process-deadline-message-shown')) {
            return;
        }

        availability = availability || {};
        $form.data('process-deadline-message-shown', true);
        handleUnavailableEvaluation($form, {
            http_status: 403,
            reason: String(availability.reason || 'process_ended'),
            message: String(availability.message || 'El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar.'),
            redirect_url: String(availability.redirect_url || $form.attr('data-my-tests-url') || window.AppBackUrl || ''),
            before_redirect: saveProcessExpiredForm($form)
        });
    }

    function isProcessDeadlineEnded($form) {
        var remaining = processDeadlineRemaining($form);

        return remaining !== null && remaining <= 0;
    }

    function setProcessDeadlineRemaining($form, remainingSeconds) {
        if (remainingSeconds === null || typeof remainingSeconds === 'undefined' || remainingSeconds === '') {
            $form.attr('data-process-remaining-seconds', '');
            $form.removeAttr('data-process-deadline-started-at');
            return;
        }

        $form.attr('data-process-remaining-seconds', String(Math.max(0, parseInt(remainingSeconds, 10) || 0)));
        $form.attr('data-process-deadline-started-at', String(Date.now()));
    }

    function scheduleProcessDeadlineCheck($form) {
        var previousTimer = $form.data('process-deadline-timer');
        if (previousTimer) {
            window.clearTimeout(previousTimer);
            $form.removeData('process-deadline-timer');
        }

        var remaining = processDeadlineRemaining($form);
        if (remaining === null) {
            return;
        }

        if (remaining <= 0) {
            refreshProcessAvailability($form, true);
            return;
        }

        var timer = window.setTimeout(function () {
            refreshProcessAvailability($form, true);
        }, (remaining + 1) * 1000);

        $form.data('process-deadline-timer', timer);
    }

    function applyProcessAvailability($form, payload) {
        var availability = payload && payload.availability ? payload.availability : {};
        var reason = String(availability.reason || '');

        if (availability.remaining_seconds === null || typeof availability.remaining_seconds === 'undefined') {
            setProcessDeadlineRemaining($form, null);
        } else {
            setProcessDeadlineRemaining($form, availability.remaining_seconds);
        }

        if (availability.allowed === false || availability.allowed === 0) {
            availability.redirect_url = payload.redirect_url || String($form.attr('data-my-tests-url') || window.AppBackUrl || '');
            if (reason === 'process_ended' || reason === 'closed_now') {
                showProcessDeadlineEnded($form, availability);
            } else {
                handleUnavailableEvaluation($form, {
                    http_status: 403,
                    reason: reason,
                    message: availability.message || 'La evaluacion ya no esta disponible para responder.',
                    redirect_url: availability.redirect_url
                });
            }
            return;
        }

        $form.data('process-deadline-message-shown', false);
        scheduleProcessDeadlineCheck($form);
    }

    function refreshProcessAvailability($form, expireWhenUnavailable) {
        var url = String($form.attr('data-process-availability-url') || '');
        if (!url || !window.fetch) {
            if (expireWhenUnavailable && isProcessDeadlineEnded($form)) {
                showProcessDeadlineEnded($form);
            }
            return Promise.resolve();
        }

        return window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return readJsonResponse(response, String($form.attr('data-my-tests-url') || window.AppBackUrl || ''));
        }).then(function (payload) {
            applyProcessAvailability($form, payload);
        }).catch(function () {
            if (expireWhenUnavailable && isProcessDeadlineEnded($form)) {
                showProcessDeadlineEnded($form);
            }
        });
    }

    $('.test-taking-shell form[data-test-taking-form]').each(function () {
        var $form = $(this);
        scheduleProcessDeadlineCheck($form);
        window.setInterval(function () {
            refreshProcessAvailability($form, false);
        }, 10000);
    });

    // Las evaluaciones del módulo evaluaciones_encuestas usan otro formulario
    // y no pasan por .test-taking-shell. Su plazo de proceso debe disparar el
    // mismo submit de cierre para conservar el pipeline audiovisual.
    $('form.evaluation-take-form[data-evaluation-control-form][data-process-remaining-seconds]').each(function () {
        var $form = $(this);
        if ($form.attr('data-evaluation-preview') === '1') return;
        window.setInterval(function () {
            if (isProcessDeadlineEnded($form)) {
                if ($form.data('process-deadline-message-shown') || $form.data('evaluation-submission-in-progress') || $form.data('evaluation-standard-submitting')) return;
                $form.data('process-deadline-message-shown', true).data('evaluation-time-expired-submitting', true).data('test-allow-exit', true);
                showNotification('warning', $form.attr('data-process-expired-message') || 'El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar.');
                $form.find('input[name="evaluation_survey_action"][type="hidden"]').remove();
                $('<input>', {type: 'hidden', name: 'evaluation_survey_action', value: 'process_expired'}).appendTo($form);
                window.setTimeout(function () {
                    if (typeof $form[0].requestSubmit === 'function') $form[0].requestSubmit(); else $form[0].submit();
                }, 1200);
            }
        }, 1000);
    });

    $('[data-server-clock]').each(function () {
        var $clock = $(this);
        if ($clock.data('server-clock-ready')) {
            return;
        }
        $clock.data('server-clock-ready', true);

        var serverBase = parseInt($clock.attr('data-server-clock'), 10) || Math.floor(Date.now() / 1000);
        var serverOffset = parseInt($clock.attr('data-server-offset'), 10) || 0;
        var clientBase = Date.now();
        var $display = $clock.find('[data-server-clock-display]');
        var reloadScheduled = false;

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function currentServerSeconds() {
            return serverBase + Math.floor((Date.now() - clientBase) / 1000);
        }

        function formatClock(seconds) {
            var date = new Date((seconds + serverOffset) * 1000);
            return pad(date.getUTCDate()) + '/' + pad(date.getUTCMonth() + 1) + '/' + date.getUTCFullYear() + ' ' + pad(date.getUTCHours()) + ':' + pad(date.getUTCMinutes()) + ':' + pad(date.getUTCSeconds());
        }

        function formatDuration(seconds) {
            seconds = Math.max(0, parseInt(seconds, 10) || 0);
            var days = Math.floor(seconds / 86400);
            seconds %= 86400;
            var hours = Math.floor(seconds / 3600);
            seconds %= 3600;
            var minutes = Math.floor(seconds / 60);
            var remainingSeconds = seconds % 60;

            if (days > 0) {
                return days + 'd ' + hours + 'h ' + minutes + 'm';
            }
            if (hours > 0) {
                return hours + 'h ' + minutes + 'm ' + remainingSeconds + 's';
            }
            return minutes + 'm ' + remainingSeconds + 's';
        }

        function scheduleReload() {
            if (reloadScheduled) {
                return;
            }
            reloadScheduled = true;
            window.setTimeout(function () {
                window.location.reload();
            }, 1300);
        }

        function renderProcessTimers() {
            var elapsed = currentServerSeconds() - serverBase;
            $('[data-process-availability]').each(function () {
                var $badge = $(this);
                var mode = String($badge.attr('data-mode') || '');
                var $label = $badge.find('[data-process-availability-label]');
                var staticLabel = String($badge.attr('data-static-label') || 'Disponible');

                if (mode === 'starts') {
                    var startsIn = Math.max(0, (parseInt($badge.attr('data-starts-in-seconds'), 10) || 0) - elapsed);
                    $label.text(startsIn > 0 ? 'Inicia en ' + formatDuration(startsIn) : 'Iniciando...');
                    if (startsIn <= 0) {
                        scheduleReload();
                    }
                    return;
                }

                if (mode === 'remaining') {
                    var remaining = Math.max(0, (parseInt($badge.attr('data-remaining-seconds'), 10) || 0) - elapsed);
                    $label.text(remaining > 0 ? 'Quedan ' + formatDuration(remaining) : 'Plazo finalizado');
                    $badge.toggleClass('text-bg-warning', remaining > 0 && remaining <= 300).toggleClass('text-bg-info', remaining > 300);
                    if (remaining <= 0) {
                        scheduleReload();
                    }
                    return;
                }

                $label.text(staticLabel);
            });
        }

        function render() {
            $display.text(formatClock(currentServerSeconds()));
            renderProcessTimers();
        }

        render();
        window.setInterval(render, 1000);
    });

    $('[data-my-tests-panel]').each(function () {
        var $panel = $(this);
        if ($panel.data('my-tests-status-ready')) {
            return;
        }
        $panel.data('my-tests-status-ready', true);

        var statusUrl = String($panel.attr('data-my-tests-status-url') || '');
        var $finalizedMessage = $panel.find('[data-assigned-tests-finalized-message]');
        var finalized = String($panel.attr('data-assigned-tests-finalized') || '0') === '1';
        var reloadScheduled = false;

        function setFinalized(nextFinalized) {
            nextFinalized = !!nextFinalized;
            $panel.attr('data-assigned-tests-finalized', nextFinalized ? '1' : '0');
            $finalizedMessage.toggleClass('d-none', !nextFinalized);

            if (nextFinalized && !finalized && !reloadScheduled) {
                reloadScheduled = true;
                window.setTimeout(function () {
                    window.location.reload();
                }, 1400);
            }

            finalized = nextFinalized;
        }

        function refreshMyTestsStatus() {
            if (!statusUrl || !window.fetch) {
                return;
            }

            window.fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('status');
                }
                return response.json();
            }).then(function (payload) {
                if (payload && payload.ok) {
                    setFinalized(payload.assigned_tests_finalized === true || payload.assigned_tests_finalized === 1);
                }
            }).catch(function () {
                // La grilla sigue siendo util si esta validacion temporal falla.
            });
        }

        refreshMyTestsStatus();
        window.setInterval(refreshMyTestsStatus, 10000);
        $(document).on('visibilitychange.myTestsStatus', function () {
            if (!document.hidden) {
                refreshMyTestsStatus();
            }
        });
    });

    function scrollTestBlockIntoView($form) {
        var form = $form[0];
        var stage = $form.closest('[data-test-fullscreen-stage]')[0];
        var pane = $form.find('[data-test-fullscreen-scroll]')[0] || form;
        var fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement || null;
        if (stage && (fullscreenElement === stage || $(stage).hasClass('is-fullscreen-stage'))) {
            pane.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            return;
        }

        $('html, body').animate({
            scrollTop: Math.max(0, $form.closest('.test-taking-shell').offset().top - 80)
        }, 220);
    }

    function replaceTestRegion($form, $nextForm, selector) {
        var $current = $form.find(selector).first();
        var $next = $nextForm.find(selector).first();
        if ($current.length && $next.length) {
            $current.replaceWith($next);
        }
    }

    function readJsonResponse(response, fallbackRedirectUrl) {
        return response.text().then(function (text) {
            var payload = {};

            if (text) {
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    payload = {
                        ok: false,
                        message: response.status === 403
                            ? 'La evaluacion ya no esta disponible para responder.'
                            : 'No se pudo guardar el bloque. Intenta nuevamente.'
                    };
                }
            }

            payload.http_status = response.status;
            if (response.status === 403 && fallbackRedirectUrl && !payload.redirect_url) {
                payload.redirect_url = fallbackRedirectUrl;
            }

            if (!response.ok || !payload.ok) {
                throw payload;
            }

            return payload;
        });
    }

    function isProcessUnavailableError(error) {
        var reason = String(error && error.reason ? error.reason : '');

        return Number(error && error.http_status) === 403
            || reason === 'process_ended'
            || reason === 'closed_now'
            || reason === 'process_not_started';
    }

    function handleUnavailableEvaluation($form, error) {
        var reason = String(error && error.reason ? error.reason : '');
        var message = error && error.message
            ? String(error.message)
            : (reason === 'process_ended'
                ? 'El proceso ha finalizado. Se guardarán tus respuestas y la evidencia audiovisual antes de cerrar.'
                : 'La evaluacion ya no esta disponible para responder.');
        var redirectUrl = error && error.redirect_url
            ? String(error.redirect_url)
            : String($form.attr('data-my-tests-url') || window.AppBackUrl || '');
        var beforeRedirect = error && error.before_redirect ? error.before_redirect : null;

        $form.data('test-allow-exit', true);
        $form.find('button[type="submit"], input, textarea, select').prop('disabled', true);

        function redirectAfterSave() {
            Promise.resolve(beforeRedirect).catch(function () {
                return null;
            }).then(function () {
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                }
            });
        }

        if (window.Swal) {
            Swal.fire($.extend({}, swalBaseOptions(), {
                title: 'Evaluacion no disponible',
                text: message,
                icon: 'warning',
                timer: 2600,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                buttonsStyling: false
            })).then(function () {
                redirectAfterSave();
            });
            return;
        }

        showNotification('warning', message);
        if (redirectUrl) {
            window.setTimeout(function () {
                redirectAfterSave();
            }, 1400);
        }
    }

    function saveProcessExpiredForm($form) {
        if (!$form.length || !window.fetch || !window.FormData) {
            return Promise.resolve();
        }

        if ($form.data('process-expired-save-pending')) {
            return $form.data('process-expired-save-promise') || Promise.resolve();
        }

        var form = $form[0];
        var formData = new FormData(form);
        var answerSnapshot = collectCurrentAnswers($form);
        appendCurrentAnswerControls($form, formData);
        formData.set('answers_snapshot_json', JSON.stringify(answerSnapshot));
        formData.set('test_action', 'process_expired');
        formData.set('block', String($form.attr('data-current-block') || formData.get('block') || '1'));

        var savePromise = window.fetch(form.action || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }).then(function (response) {
            return response.text().then(function (text) {
                var payload = {};
                if (text) {
                    try { payload = JSON.parse(text); } catch (error) { payload = {}; }
                }
                if ((!response.ok || !payload.ok) && !payload.saved) {
                    throw payload;
                }
                return payload;
            });
        }).then(function (payload) {
            var finalize = $form.data('audio-visual-finalize');
            if ($form.attr('data-audio-visual-mode') !== '1' || typeof finalize !== 'function') {
                return payload;
            }

            $form.data('audio-visual-finalizing', true);
            showNotification('info', 'Respuestas guardadas. Finalizando video y capturas…');
            return Promise.resolve(finalize()).then(function () {
                $form.data('audio-visual-finalized', true);
                return payload;
            });
        });

        $form.data('process-expired-save-pending', true);
        $form.data('process-expired-save-promise', savePromise);

        return savePromise;
    }

    function collectCurrentAnswers($form) {
        var answers = {};

        $form.find('input[name^="answers["], textarea[name^="answers["], select[name^="answers["]').each(function () {
            var field = this;
            var name = String(field.name || '');
            var match = name.match(/^answers\[(\d+)\](?:\[\])?$/);
            var type = String(field.type || '').toLowerCase();
            var itemId = match ? match[1] : '';

            if (!itemId) {
                return;
            }

            if (type === 'radio') {
                if (field.checked) {
                    answers[itemId] = field.value;
                }
                return;
            }

            if (type === 'checkbox') {
                if (!Array.isArray(answers[itemId])) {
                    answers[itemId] = [];
                }
                if (field.checked) {
                    answers[itemId].push(field.value);
                }
                return;
            }

            answers[itemId] = $(field).val() || '';
        });

        return answers;
    }

    function syncAnswerSnapshot($form) {
        var snapshot = JSON.stringify(collectCurrentAnswers($form));
        var $input = $form.find('input[name="answers_snapshot_json"]');

        if (!$input.length) {
            $input = $('<input>', {
                type: 'hidden',
                name: 'answers_snapshot_json'
            }).appendTo($form);
        }

        $input.val(snapshot);
    }

    function appendCurrentAnswerControls($form, formData) {
        var seen = {};

        $form.find('input[name^="answers["], textarea[name^="answers["], select[name^="answers["]').each(function () {
            var field = this;
            var name = field.name;
            var type = String(field.type || '').toLowerCase();

            if (!name) {
                return;
            }

            if (type === 'radio') {
                if (field.checked) {
                    formData.set(name, field.value);
                }
                return;
            }

            if (type === 'checkbox') {
                if (!seen[name]) {
                    formData.delete(name);
                    seen[name] = true;
                }
                if (field.checked) {
                    formData.append(name, field.value);
                }
                return;
            }

            formData.set(name, $(field).val() || '');
        });
    }

    function draftFormData($form, reason) {
        var form = $form[0];
        var formData = new FormData(form);
        var answerSnapshot = collectCurrentAnswers($form);

        appendCurrentAnswerControls($form, formData);
        formData.set('answers_snapshot_json', JSON.stringify(answerSnapshot));
        var draftActionName = String($form.attr('data-draft-action-name') || 'test_action');
        var draftActionValue = String($form.attr('data-draft-action-value') || 'draft');
        formData.delete('test_action');
        formData.delete('evaluation_survey_action');
        formData.set(draftActionName, draftActionValue);
        formData.set('block', String($form.attr('data-current-block') || formData.get('block') || '1'));
        formData.set('remaining_seconds', String($form.attr('data-remaining-seconds') || $form.attr('data-evaluation-remaining-seconds') || ''));
        if (reason) {
            formData.set('reason', String(reason));
        }

        return {
            formData: formData,
            snapshot: JSON.stringify(answerSnapshot)
        };
    }

    function saveDraftAnswers($form, options) {
        options = options || {};
        if (!$form.length || !window.fetch || !window.FormData) {
            return Promise.resolve();
        }

        var url = String($form.attr('data-draft-url') || '');
        if (!url) {
            return Promise.resolve();
        }

        var prepared = draftFormData($form, options.reason || '');
        if (!options.force && prepared.snapshot === $form.data('last-draft-snapshot')) {
            return Promise.resolve();
        }

        if ($form.data('draft-save-pending') && !options.immediate) {
            $form.data('draft-save-queued', true);
            return $form.data('draft-save-promise') || Promise.resolve();
        }

        $form.data('draft-save-pending', true);
        $form.data('last-draft-snapshot', prepared.snapshot);

        if (options.beacon && navigator.sendBeacon && window.URLSearchParams && window.Blob) {
            var params = new URLSearchParams();
            prepared.formData.forEach(function (value, key) {
                params.append(key, value);
            });
            var blob = new Blob([params.toString()], {
                type: 'application/x-www-form-urlencoded; charset=UTF-8'
            });
            if (navigator.sendBeacon(url, blob)) {
                $form.data('draft-save-pending', false);
                return Promise.resolve();
            }
        }

        var savePromise = window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: prepared.formData,
            keepalive: !!options.immediate
        }).then(function (response) {
            return readJsonResponse(response, String($form.attr('data-my-tests-url') || window.AppBackUrl || ''));
        }).catch(function () {
            $form.removeData('last-draft-snapshot');
        }).finally(function () {
            var queued = !!$form.data('draft-save-queued');
            $form.data('draft-save-pending', false);
            $form.data('draft-save-queued', false);
            if (queued) {
                saveDraftAnswers($form, { reason: 'queued' });
            }
        });

        $form.data('draft-save-promise', savePromise);
        return savePromise;
    }

    function scheduleDraftSave($form, reason) {
        var previousTimer = $form.data('draft-save-timer');
        if (previousTimer) {
            window.clearTimeout(previousTimer);
        }

        var timer = window.setTimeout(function () {
            saveDraftAnswers($form, { reason: reason || 'change' });
        }, 1500);

        $form.data('draft-save-timer', timer);
    }

    function initAjaxBlockSaving($form) {
        if (!$form.length || $form.data('ajax-block-saving-ready') || $form.attr('data-block-ajax') !== '1') {
            return;
        }

        $form.data('ajax-block-saving-ready', true);

        $form.on('click.ajaxBlockSaving', 'button[type="submit"], input[type="submit"]', function () {
            $form.data('last-submitter', this);
        });

        $form.on('submit.ajaxBlockSaving', function (event) {
            var form = this;
            var $currentForm = $(form);
            var submitter = event.originalEvent && event.originalEvent.submitter
                ? event.originalEvent.submitter
                : $form.data('last-submitter');

            if (!submitter) {
                var activeElement = document.activeElement;
                if (activeElement
                    && activeElement.form === form
                    && /^(button|input)$/i.test(activeElement.tagName || '')
                    && activeElement.type === 'submit') {
                    submitter = activeElement;
                }
            }

            if (!submitter) {
                var $blockSubmitters = $form.find('button[type="submit"][name="test_action"], input[type="submit"][name="test_action"]');
                if ($blockSubmitters.length === 1) {
                    submitter = $blockSubmitters.get(0);
                } else {
                    submitter = $blockSubmitters.filter('[value="save_block_next"]').get(0)
                        || $blockSubmitters.get(0);
                }
            }

            var action = submitter && submitter.name === 'test_action' ? submitter.value : '';

            if (action.indexOf('save_block') !== 0 || !window.fetch || !window.FormData || !window.DOMParser) {
                return;
            }

            if (!form.checkValidity()) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            // El autoguardado pertenece al mismo bloque. Antes de cambiar o
            // cerrar el bloque, evita que una petición atrasada llegue después
            // de que la sesión ya haya quedado completada (409 de /draft).
            var draftTimer = $currentForm.data('draft-save-timer');
            if (draftTimer) {
                window.clearTimeout(draftTimer);
                $currentForm.removeData('draft-save-timer');
            }
            $currentForm.data('draft-save-queued', false);
            var pendingDraft = $currentForm.data('draft-save-promise') || Promise.resolve();

            if (isProcessDeadlineEnded($currentForm)) {
                showProcessDeadlineEnded($currentForm);
                return;
            }

            if ($currentForm.data('block-save-pending')) {
                return;
            }

            function questionHasAnswer(card) {
                var answered = false;
                $(card).find('input, textarea, select').each(function () {
                    var $field = $(this);
                    if (($field.is(':radio') || $field.is(':checkbox')) && $field.is(':checked')) {
                        answered = true;
                    } else if (!$field.is(':radio, :checkbox') && $.trim($field.val())) {
                        answered = true;
                    }
                });
                return answered;
            }

            function firstIncompleteQuestion() {
                return $currentForm.find('.test-question-card').filter(function () {
                    return !questionHasAnswer(this);
                }).first();
            }

            function focusIncompleteQuestion($question) {
                if (!$question || !$question.length) {
                    return;
                }
                $question.addClass('is-incomplete');
                scrollTestBlockIntoView($currentForm);
                window.setTimeout(function () {
                    var pane = $currentForm.find('[data-test-fullscreen-scroll]').get(0);
                    if (pane && typeof pane.scrollTo === 'function') {
                        pane.scrollTo({
                            top: Math.max(0, $question.position().top + pane.scrollTop - 40),
                            behavior: 'smooth'
                        });
                    }
                }, 120);
            }

            function requestIncompleteFinishConfirmation(message) {
                return appSwalConfirm({
                    title: $currentForm.attr('data-incomplete-confirm-title') || 'Evaluacion incompleta',
                    text: message || $currentForm.attr('data-incomplete-confirm-message') || 'Aun quedan preguntas sin contestar en la evaluacion. Deseas contestarlas antes de finalizar?',
                    icon: 'warning',
                    confirmButtonText: $currentForm.attr('data-incomplete-confirm-button') || 'Si, contestar pendientes',
                    cancelButtonText: $currentForm.attr('data-incomplete-cancel-button') || 'No, guardar y finalizar',
                    focusCancel: true
                });
            }

            function submitBlockAction(actionToSubmit) {
                if (markFormProcessing($currentForm, submitter)) {
                    return;
                }

                var $saving = $currentForm.find('[data-test-block-saving]');
                var $buttons = $currentForm.find('button[type="submit"]');
                var unavailableHandled = false;
                var fallbackRedirectUrl = String($currentForm.attr('data-my-tests-url') || window.AppBackUrl || '');
                syncAnswerSnapshot($currentForm);
                var formData = new FormData(form);
                formData.set('answers_snapshot_json', JSON.stringify(collectCurrentAnswers($currentForm)));
                formData.set('test_action', actionToSubmit);

                function applyBlockPayload(payload) {
                    var parsed = new DOMParser().parseFromString(payload.html || '', 'text/html');
                    var $nextForm = $(parsed).find('[data-test-taking-form]').first();

                    if (!$nextForm.length) {
                        throw { message: 'No se pudo cargar el siguiente bloque.' };
                    }

                    replaceTestRegion($currentForm, $nextForm, '[data-test-block-status]');
                    replaceTestRegion($currentForm, $nextForm, '[data-test-progress]');
                    replaceTestRegion($currentForm, $nextForm, '[data-test-item-stack]');

                    var $nextActions = $nextForm.find('[data-test-submit-actions]').first();
                    if ($nextActions.length) {
                        $currentForm.find('[data-test-submit-actions]').first().replaceWith($nextActions);
                    }

                    var nextBlock = String(payload.next_block || $nextForm.attr('data-current-block') || '');
                    if (nextBlock) {
                        $currentForm.attr('data-current-block', nextBlock);
                        $currentForm.find('[data-test-current-block-input]').val(nextBlock);
                    }

                    $currentForm.attr('data-process-remaining-seconds', $nextForm.attr('data-process-remaining-seconds') || '');
                    $currentForm.removeAttr('data-process-deadline-started-at');
                    $currentForm.removeData('last-draft-snapshot');
                    scheduleProcessDeadlineCheck($currentForm);

                    var nextUrl = payload.next_url || payload.redirect_url || '';
                    if (nextUrl && window.history && window.history.replaceState) {
                        window.history.replaceState($.extend({}, window.history.state || {}, { testTakingGuard: true }), '', nextUrl);
                    }

                    $currentForm.removeClass('was-validated');
                    syncTestProgress($currentForm);
                    scrollTestBlockIntoView($currentForm);
                }

                $currentForm.data('block-save-pending', true);
                $saving.removeClass('d-none');
                $buttons.prop('disabled', true);

                Promise.resolve(pendingDraft).catch(function () {}).then(function () { return window.fetch(form.action || window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                }); }).then(function (response) {
                    return readJsonResponse(response, fallbackRedirectUrl);
                }).then(function (payload) {
                    if (payload.finished) {
                        var finalizePromise = actionToSubmit === 'save_block_finish' && $currentForm.data('audio-visual-finalize')
                            ? $currentForm.data('audio-visual-finalize')()
                            : Promise.resolve();
                        return Promise.resolve(finalizePromise).then(function () {
                            $currentForm.data('test-allow-exit', true);
                            if (payload.redirect_url) {
                                window.location.href = payload.redirect_url;
                            }
                        });
                    }

                    applyBlockPayload(payload);
                    showNotification('success', payload.message || 'Bloque guardado correctamente.');
                }).catch(function (error) {
                    if (error && error.reason === 'block_incomplete') {
                        showNotification('warning', error.message || 'Debes responder todas las preguntas de este bloque antes de avanzar.');
                        var $incompleteBlockQuestion = firstIncompleteQuestion();
                        if ($incompleteBlockQuestion.length) {
                            focusIncompleteQuestion($incompleteBlockQuestion);
                        }
                        return;
                    }

                    if (error && error.reason === 'missing_answers_before_finish') {
                        requestIncompleteFinishConfirmation(error.message).then(function (confirmed) {
                        if (confirmed) {
                            var $incomplete = firstIncompleteQuestion();
                            if ($incomplete.length) {
                                focusIncompleteQuestion($incomplete);
                            }
                            return;
                        }
                        submitBlockAction('complete_incomplete');
                        });
                        return;
                    }

                    if (error && error.saved && isProcessUnavailableError(error)) {
                        unavailableHandled = true;
                        handleUnavailableEvaluation($currentForm, error);
                        return;
                    }

                    if (isProcessDeadlineEnded($currentForm)) {
                        unavailableHandled = true;
                        showProcessDeadlineEnded($currentForm);
                        return;
                    }

                    if (isProcessUnavailableError(error)) {
                        unavailableHandled = true;
                        handleUnavailableEvaluation($currentForm, error);
                        return;
                    }

                    showNotification('error', error && error.message ? error.message : 'No se pudo guardar el bloque. Intenta nuevamente.');
                }).finally(function () {
                    $saving.addClass('d-none');
                    if (!unavailableHandled) {
                        $buttons.prop('disabled', false);
                        clearFormProcessing($currentForm);
                    } else {
                        $currentForm.data('app-processing', false);
                        $currentForm.find('input[data-app-processing-submitter]').remove();
                        hideAppProcessing();
                    }
                    $currentForm.data('block-save-pending', false);
                });
            }

            var $firstIncomplete = firstIncompleteQuestion();
            if (action === 'save_block_next'
                && $currentForm.attr('data-require-block-completion') === '1'
                && $firstIncomplete.length) {
                showNotification('warning', 'Debes responder todas las preguntas de este bloque antes de avanzar.');
                focusIncompleteQuestion($firstIncomplete);
                return;
            }

            submitBlockAction(action);
        });
    }

    $('.test-taking-shell form[data-test-taking-form]').each(function () {
        var $form = $(this);
        syncAnswerSnapshot($form);
        initAjaxBlockSaving($form);
    });

    $(document).on('input change', '.test-taking-shell input, .test-taking-shell textarea, .test-taking-shell select', function () {
        var $form = $(this).closest('form');
        $(this).closest('.test-question-card').removeClass('is-incomplete');
        syncAnswerSnapshot($form);
        scheduleDraftSave($form, 'answer_changed');
        syncTestProgress($form);
    });

    function syncTestBlockSettings(scope) {
        var $scope = scope ? $(scope) : $(document);
        $scope.find('[data-toggle-test-blocks]').each(function () {
            var $toggle = $(this);
            var enabled = $toggle.is(':checked');
            var $form = $toggle.closest('form');
            $form.find('[data-test-block-option]').toggleClass('d-none', !enabled);
            $form.find('#block_size').prop('required', enabled);
        });
    }

    syncTestBlockSettings(document);

    $(document).on('change', '[data-toggle-test-blocks]', function () {
        syncTestBlockSettings($(this).closest('form'));
    });

    function drawerUrl(url) {
        return url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
    }

    function openDrawer(options) {
        updateAppViewportHeight();
        var size = ['sm', 'md', 'lg'].indexOf(options.size) !== -1 ? options.size : 'md';
        var $drawer = $('#appDrawer');
        var $backdrop = $('.app-drawer-backdrop');
        var $body = $('#appDrawerBody');

        $('#appDrawerTitle').text(options.title || 'Detalle');
        $drawer.removeClass('app-drawer-sm app-drawer-md app-drawer-lg').addClass('app-drawer-' + size);
        $body.removeClass('app-drawer-body-test-entry');
        if (options.bodyClass) {
            $body.addClass(options.bodyClass);
        }
        $body.html('<div class="drawer-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Cargando...</span></div>');
        $backdrop.prop('hidden', false);
        $('body').addClass('drawer-open');

        window.requestAnimationFrame(function () {
            $drawer.addClass('is-open').attr('aria-hidden', 'false');
            $backdrop.addClass('is-open');
        });

        if (options.html) {
            $body.html(options.html);
            initNotifications($body);
            initDataTables($body);
            initPromptEditors($body);
            initRutInputs($body);
            initBootstrapPopovers($body);
            return;
        }

        $.ajax({
            url: drawerUrl(options.url),
            method: 'GET',
            dataType: 'html'
        }).done(function (html) {
            $body.html(html);
            initNotifications($body);
            initDataTables($body);
            initPromptEditors($body);
            initRutInputs($body);
            initBootstrapPopovers($body);
        }).fail(function () {
            var message = 'No se pudo cargar el contenido solicitado.';
            $body.html('<div class="drawer-error"><i class="bi bi-exclamation-triangle"></i><p>' + message + '</p></div>');
            showNotification('danger', message);
        });
    }

    function closeDrawer() {
        var $drawer = $('#appDrawer');
        var $backdrop = $('.app-drawer-backdrop');

        $drawer.removeClass('is-open').attr('aria-hidden', 'true');
        $backdrop.removeClass('is-open');
        $('body').removeClass('drawer-open');

        window.setTimeout(function () {
            if (!$drawer.hasClass('is-open')) {
                $backdrop.prop('hidden', true);
                $('#appDrawerBody').html('');
            }
        }, 220);
    }

    window.AppDrawer = {
        open: openDrawer,
        close: closeDrawer
    };

    $(document).on('click', '[data-drawer-url]', function (event) {
        event.preventDefault();
        openDrawer({
            url: $(this).data('drawer-url'),
            title: $(this).data('drawer-title') || $(this).text().trim(),
            size: $(this).data('drawer-size') || 'md'
        });
    });

    $(document).on('submit', '[data-password-recovery-form]', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $body = $('#appDrawerBody');
        var $submit = $form.find('button[type="submit"]');
        clearFormProcessing($form);
        $submit.prop('disabled', true).prepend('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'html'
        }).done(function (html) {
            clearFormProcessing($form);
            $body.html(html);
            initNotifications($body);
            initDataTables($body);
            initBootstrapPopovers($body);
        }).fail(function () {
            clearFormProcessing($form);
            showNotification('danger', 'No se pudo solicitar la recuperación. Intenta nuevamente.');
            $submit.prop('disabled', false).find('.spinner-border').remove();
        });
    });

    $(document).on('click', '[data-import-row-drawer]', function (event) {
        var selector = $(this).data('import-row-drawer');
        var template = selector ? document.querySelector(selector) : null;

        event.preventDefault();

        if (!template) {
            showNotification('danger', 'No se pudo abrir la correccion de la fila.');
            return;
        }

        openDrawer({
            html: template.innerHTML,
            title: $(this).data('import-row-title') || 'Corregir fila',
            size: 'lg'
        });
        $('#appDrawerBody img[loading="lazy"]').removeAttr('loading');
        $('#appDrawerBody video').each(function () {
            this.load();
        });
        var attentionEventPatterns = ['posible multiplicidad de voces', 'multiple_voice_possible', 'pestaña oculta', 'tab_hidden', 'ventana sin foco', 'window_blurred', 'inactividad detectada', 'inactive_detected', 'salida de pantalla completa', 'fullscreen_exited', 'pantalla completa rechazada', 'fullscreen_denied', 'tecla de captura detectada', 'suspicious_key_printscreen', 'intento de imprimir', 'print_blocked', 'intento de guardar pagina', 'suspicious_key_save', 'copia bloqueada', 'copy_blocked', 'corte bloqueado', 'cut_blocked', 'pegado bloqueado', 'paste_blocked', 'arrastre bloqueado', 'drag_blocked', 'impresión bloqueada'];
        $('#appDrawerBody table tbody tr').each(function () {
            var eventText = String($(this).find('td').first().text() || '').toLowerCase();
            if (attentionEventPatterns.some(function (pattern) { return eventText.indexOf(pattern) !== -1; })) {
                $(this).addClass('activity-attention-row');
            }
        });
    });

    function renderImportErrorSummary(errorSummary) {
        var summary = errorSummary || {};
        var total = parseInt(summary.total_errors || 0, 10);
        var ageErrors = parseInt(summary.age_errors || 0, 10);
        var fields = summary.by_field || [];
        var messages = summary.by_message || [];
        var $container = $('[data-import-error-summary]');

        if (!$container.length) {
            return;
        }

        $container.toggleClass('d-none', total <= 0);
        $('[data-import-error-total]').text(total);
        $('[data-import-error-fields]').html(fields.map(function (item) {
            return '<span class="import-error-chip">' + escapeHtml(item.label || '') + ' <strong>' + escapeHtml(item.count || 0) + '</strong></span>';
        }).join(''));
        $('[data-import-error-messages]').html(messages.map(function (item) {
            return '<li><strong>' + escapeHtml(item.count || 0) + '</strong> ' + escapeHtml(item.message || '') + '</li>';
        }).join(''));
        $('[data-import-age-recalculate-action]').toggleClass('d-none', ageErrors <= 0);
        $('[data-import-age-error-hint]').toggleClass('d-none', ageErrors <= 0).text(
            ageErrors > 0
                ? 'Hay ' + ageErrors + ' fila(s) con error de edad. El recalculo solo modifica la edad cuando la fecha de nacimiento es valida y despues vuelve a validar toda la carga.'
                : ''
        );
    }

    $(document).on('submit', '[data-import-row-correction-form]', function (event) {
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');

        event.preventDefault();
        $submit.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            dataType: 'json',
            data: $form.serialize()
        }).done(function (payload) {
            var summary = payload.summary || {};
            var hasErrors = parseInt(summary.with_errors || 0, 10) > 0;
            var $table = $('.import-preview-table.app-data-table').first();

            $('[data-import-summary]').each(function () {
                var key = $(this).data('import-summary');
                if (Object.prototype.hasOwnProperty.call(summary, key)) {
                    $(this).text(summary[key]);
                }
            });
            $('[data-import-created]').text(payload.created || 0);
            $('[data-import-updated]').text(payload.updated || 0);
            $('[data-import-summary-card="with_errors"]').toggleClass('has-errors', hasErrors);
            $('[data-import-apply-button]').prop('disabled', !payload.valid);
            $('[data-import-apply-hint]').text(payload.valid ? 'Aplicara las filas validadas en esta previsualizacion.' : 'Disponible solo cuando la previsualizacion no tenga errores.');
            $('[data-import-status-alert]')
                .removeClass('alert-success alert-warning alert-danger')
                .addClass(payload.valid ? 'alert-success' : 'alert-warning')
                .text(payload.valid ? 'Validacion correcta. Asi se cargaran los datos al confirmar.' : 'Hay problemas pendientes. Revisa la columna final de cada fila para ver que corregir.');
            renderImportErrorSummary(payload.error_summary || {});

            if ($table.length && $.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
                $table.DataTable().ajax.reload(null, false);
            }

            closeDrawer();
            showNotification(payload.valid ? 'success' : 'warning', payload.message || 'Fila guardada y revalidada.');
        }).fail(function (xhr) {
            var payload = xhr.responseJSON || {};
            showNotification('error', payload.message || 'No se pudo guardar la correccion de la fila.');
        }).always(function () {
            clearFormProcessing($form);
            $submit.prop('disabled', false);
        });
    });

    $(document).on('submit', '[data-user-drawer-form]', function (event) {
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');

        event.preventDefault();
        $submit.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            dataType: 'json',
            data: $form.serialize()
        }).done(function (payload) {
            var $table = $('table[data-server-url*="users/data"]').first();
            if ($table.length && $.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
                $table.DataTable().ajax.reload(null, false);
            }
            closeDrawer();
            showNotification('success', payload.message || 'Usuario guardado correctamente.');
            if (payload.reload) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 450);
            }
        }).fail(function (xhr) {
            var payload = xhr.responseJSON || {};
            showNotification('error', payload.message || 'No se pudo guardar el usuario.');
        }).always(function () {
            clearFormProcessing($form);
            $submit.prop('disabled', false);
        });
    });

    $(document).on('submit', '[data-evaluation-question-form]', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        var form = $form[0];

        if (window.tinymce && typeof tinymce.triggerSave === 'function') {
            tinymce.triggerSave();
        }

        if (!form.checkValidity()) {
            $form.addClass('was-validated');
            return;
        }

        $submit.prop('disabled', true);

        $.ajax({
            url: $form.attr('action') || window.location.href,
            method: 'POST',
            dataType: 'json',
            data: $form.serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (payload) {
            closeDrawer();
            showNotification('success', payload.message || 'Pregunta guardada correctamente.');
            window.setTimeout(function () {
                window.location.reload();
            }, 450);
        }).fail(function (xhr) {
            var payload = xhr.responseJSON || {};
            showNotification('error', payload.message || 'No se pudo guardar la pregunta.');
        }).always(function () {
            clearFormProcessing($form);
            $submit.prop('disabled', false);
        });
    });

    $(document).on('click', '[data-app-drawer-close]', closeDrawer);

    $(document).on('click', '[data-screen-capture-view]', function (event) {
        event.preventDefault();
        var source = $(this).attr('href') || $(this).data('screen-capture-view');
        var alt = $(this).find('img').attr('alt') || 'Captura de pantalla';
        if (!source) return;
        var $modal = $('#app-screen-capture-viewer');
        if (!$modal.length) {
            $modal = $('<div class="modal fade" id="app-screen-capture-viewer" tabindex="-1" aria-labelledby="app-screen-capture-viewer-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h6" id="app-screen-capture-viewer-title">Captura de pantalla</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body text-center"><img class="img-fluid rounded" data-screen-capture-viewer-image alt=""></div></div></div></div>');
            $('body').append($modal);
        }
        $modal.find('[data-screen-capture-viewer-image]').attr({src: source, alt: alt}).addClass('app-screen-capture-viewer-image');
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        }
    });

    function syncAdvancedOptions($form) {
        $form.find('[data-advanced-option-list]').each(function () {
            var lines = [];
            $(this).find('[data-advanced-option-row]').each(function () {
                var value = $.trim($(this).find('[data-advanced-option-value]').val());
                var label = $.trim($(this).find('[data-advanced-option-label]').val());
                if (value && label) {
                    lines.push(value + '=' + label);
                }
            });
            $(this).closest('.test-option-builder').find('[data-advanced-options-value]').val(lines.join(';'));
        });
    }

    $(document).on('click', '[data-add-advanced-option]', function () {
        var $list = $(this).closest('.test-option-builder').find('[data-advanced-option-list]');
        $list.append(
            '<div class="test-option-row" data-advanced-option-row>' +
                '<div><label class="form-label">Valor</label><input class="form-control" data-advanced-option-value></div>' +
                '<div><label class="form-label">Texto visible</label><input class="form-control" data-advanced-option-label></div>' +
                '<div class="test-option-row-action"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-advanced-option><i class="bi bi-trash"></i></button></div>' +
            '</div>'
        );
        syncAdvancedOptions($(this).closest('form'));
    });

    $(document).on('click', '[data-remove-advanced-option]', function () {
        var $form = $(this).closest('form');
        var $list = $(this).closest('[data-advanced-option-list]');
        $(this).closest('[data-advanced-option-row]').remove();
        if (!$list.find('[data-advanced-option-row]').length) {
            $list.append(
                '<div class="test-option-row" data-advanced-option-row>' +
                    '<div><label class="form-label">Valor</label><input class="form-control" data-advanced-option-value></div>' +
                    '<div><label class="form-label">Texto visible</label><input class="form-control" data-advanced-option-label></div>' +
                    '<div class="test-option-row-action"><button class="btn btn-sm btn-outline-danger" type="button" data-remove-advanced-option><i class="bi bi-trash"></i></button></div>' +
                '</div>'
            );
        }
        syncAdvancedOptions($form);
    });

    $(document).on('input change', '[data-advanced-option-row] input', function () {
        syncAdvancedOptions($(this).closest('form'));
    });

    $(document).on('submit', 'form[data-advanced-item-form]', function () {
        var $form = $(this);
        var $editor = $form.find('[data-item-prompt-editor]');
        savePromptEditor($editor);
        var html = cleanPromptHtml($editor.val());
        $editor.val(isPromptHtmlEmpty(html) ? '' : encodePromptHtml(html));
        syncAdvancedOptions($form);
    });

    function cssVar(name, fallback) {
        var value = getComputedStyle(document.body).getPropertyValue(name).trim();
        return value || fallback;
    }

    function htmlToPlainText(html) {
        return $('<div/>').html(String(html || '')).text().replace(/\s+/g, ' ').trim();
    }

    function textToSafeHtml(text) {
        return $('<div/>').text(String(text || '')).html().replace(/\r?\n/g, '<br>');
    }

    function normalizeLineBreaks(text) {
        return String(text || '')
            .replace(/\\r\\n/g, '\n')
            .replace(/\\n/g, '\n')
            .replace(/\\r/g, '\n');
    }

    function replaceTemplateTokens(text, replacements) {
        var output = String(text || '');
        $.each(replacements || {}, function (token, value) {
            output = output.split('{' + token + '}').join(String(value || ''));
        });
        return output;
    }

    function appSwalConfirm(options) {
        var settings = $.extend({
            title: 'Confirmar accion',
            html: '',
            text: '',
            icon: 'warning',
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }, options || {});
        var message = settings.text || htmlToPlainText(settings.html);

        if (!window.Swal) {
            return Promise.resolve(window.confirm(message || settings.title));
        }

        return Swal.fire({
            target: messageTargetElement(),
            title: settings.title,
            html: settings.html || undefined,
            text: settings.html ? undefined : settings.text,
            icon: settings.icon,
            showCancelButton: true,
            confirmButtonText: settings.confirmButtonText,
            cancelButtonText: settings.cancelButtonText,
            reverseButtons: true,
            focusCancel: settings.focusCancel,
            buttonsStyling: false,
            customClass: {
                popup: 'app-swal-popup',
                title: 'app-swal-title',
                htmlContainer: 'app-swal-text',
                actions: 'app-swal-actions',
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-outline-secondary'
            },
            background: cssVar('--card-content-bg', '#ffffff'),
            color: cssVar('--app-text', '#111827'),
            iconColor: cssVar('--button-bg', '#C3A80B')
        }).then(function (result) {
            return !!result.isConfirmed;
        });
    }

    function initAutoStartRequiredPrompt() {
        var marker = document.querySelector('[data-auto-start-required]');
        if (!marker || marker.dataset.autoStartPromptShown === '1') {
            return;
        }

        marker.dataset.autoStartPromptShown = '1';
        var testName = String(marker.getAttribute('data-test-name') || 'la evaluacion');
        var testUrl = String(marker.getAttribute('data-test-url') || '');
        var entryTarget = String(marker.getAttribute('data-test-entry-target') || '');
        var message = 'Se procedera a dar inicio a la evaluacion "' + testName + '".';

        function continueToTest() {
            var entryLink = entryTarget ? document.querySelector(entryTarget) : null;
            if (entryLink) {
                entryLink.click();
                return;
            }

            if (testUrl) {
                window.location.href = testUrl;
            }
        }

        if (!window.Swal) {
            window.alert(message);
            continueToTest();
            return;
        }

        Swal.fire($.extend({}, swalBaseOptions(), {
            title: 'Evaluacion obligatoria',
            text: message,
            icon: 'info',
            confirmButtonText: 'Continuar',
            showCancelButton: false,
            showCloseButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: true,
            buttonsStyling: false
        })).then(function () {
            continueToTest();
        });
    }

    initAutoStartRequiredPrompt();

    $(document).on('click', '[data-test-entry-confirm]', function (event) {
        var link = this;
        var $link = $(link);
        var requiredEntry = $link.attr('data-test-entry-required') === '1';

        if ($link.data('test-entry-confirmed')) {
            return;
        }

        event.preventDefault();

        var testName = String($link.attr('data-test-name') || 'esta evaluacion');
        var trackingEnabled = $link.attr('data-test-activity-tracking') === '1';
        var supervisedMode = $link.attr('data-test-supervised-mode') === '1';
        var trackingText = trackingEnabled
            ? String($link.attr('data-test-activity-notice') || '')
            : String($link.attr('data-test-activity-disabled-notice') || '');
        if (supervisedMode) {
            trackingText += '\n\nEsta evaluacion usa modo supervisado: se solicitara pantalla completa cuando el dispositivo lo permita y se registraran senales como perdida de foco, cambio de pestana o app, inactividad, salida de pantalla completa e intentos de copiar, imprimir o usar combinaciones restringidas. En telefonos y tablets algunas senales dependen del navegador y sistema operativo.';
        }
        var entryMessage = replaceTemplateTokens($link.attr('data-test-entry-message') || '', {
            test_name: testName,
            activity_tracking_notice: trackingText
        });
        var instructionsTemplateId = String($link.attr('data-test-instructions-template') || '');
        var instructionsHtml = '';
        var $instructionsTemplate = instructionsTemplateId ? $('#' + instructionsTemplateId) : $();
        if ($instructionsTemplate.length) {
            instructionsHtml = $.trim($instructionsTemplate.html() || '');
        }
        var drawerHtml = '<div class="test-entry-drawer"><div class="test-entry-drawer-content">';
        if (instructionsHtml) {
            drawerHtml += '<section class="test-entry-instructions mb-4">'
                + '<p class="text-uppercase text-primary fw-bold small mb-2">Instrucciones</p>'
                + instructionsHtml
                + '</section>';
        }
        drawerHtml += '<section class="mb-4">'
            + '<p class="text-uppercase text-primary fw-bold small mb-2">Antes de continuar</p>'
            + '<div>' + textToSafeHtml(entryMessage) + '</div>'
            + '</section>'
            + '</div>'
            + '<div class="test-entry-drawer-actions">';
        if (!requiredEntry) {
            drawerHtml += '<button class="btn btn-outline-secondary" type="button" data-test-entry-drawer-cancel>'
                + escapeHtml($link.attr('data-test-entry-cancel-button') || 'Cancelar')
                + '</button>';
        }
        drawerHtml += ''
            + '<button class="btn btn-primary" type="button" data-test-entry-drawer-start>'
            + '<i class="bi bi-play-circle me-1"></i> '
            + escapeHtml($link.attr('data-test-entry-confirm-button') || 'Ingresar')
            + '</button>'
            + '</div>';

        openDrawer({
            title: $link.attr('data-test-entry-title') || 'Antes de comenzar',
            html: drawerHtml,
            size: 'lg',
            bodyClass: 'app-drawer-body-test-entry'
        });
        $('#appDrawerBody').scrollTop(0);
        $('#appDrawerBody .test-entry-drawer-content').scrollTop(0);

        $('#appDrawerBody').off('click.testEntryDrawer')
            .on('click.testEntryDrawer', '[data-test-entry-drawer-cancel]', function () {
                closeDrawer();
            })
            .on('click.testEntryDrawer', '[data-test-entry-drawer-start]', function () {
                closeDrawer();
                $link.data('test-entry-confirmed', true);
                window.location.href = link.href;
            });
    });

    function submitTestSaveExit($form) {
        if (!$form.length || $form.data('test-saving-exit')) {
            return;
        }

        $form.data('test-saving-exit', true);
        if ($form.attr('data-audio-visual-mode') === '1'
            && typeof $form.data('audio-visual-finalize') === 'function'
            && typeof $form[0].requestSubmit === 'function') {
            // El cierre audiovisual debe conservar el orden de guardado:
            // respuestas, video y capturas. Un submit nativo lo omite.
            $form.data('test-allow-exit', true);
            $form.find('button[type="submit"]').prop('disabled', true);
            $form.find('input[name="test_action"]').remove();
            $('<input>', {
                type: 'hidden',
                name: 'test_action',
                value: 'save_exit'
            }).appendTo($form);
            $form[0].requestSubmit();
            return;
        }

        $form.data('test-allow-exit', true);
        $form.find('button[type="submit"]').prop('disabled', true);
        $form.find('input[name="test_action"]').remove();
        $('<input>', {
            type: 'hidden',
            name: 'test_action',
            value: 'save_exit'
        }).appendTo($form);
        markFormProcessing($form);
        $form[0].submit();
    }

    function confirmTestExit($form) {
        return appSwalConfirm({
            title: $form.attr('data-test-exit-title') || 'Evaluacion en curso',
            html: '<div class="text-start">' + textToSafeHtml($form.attr('data-test-exit-message') || '') + '</div>',
            confirmButtonText: $form.attr('data-test-exit-save-button') || 'Guardar y salir',
            cancelButtonText: $form.attr('data-test-exit-continue-button') || 'Continuar evaluacion',
            focusCancel: true
        }).then(function (shouldSaveExit) {
            if (shouldSaveExit) {
                submitTestSaveExit($form);
            }

            return !shouldSaveExit;
        });
    }

    function initTestExitGuard($form) {
        if (!$form.length || $form.data('test-exit-guard-ready')) {
            return;
        }

        $form.data('test-exit-guard-ready', true);

        var hasHistoryGuard = !!(window.history && window.history.pushState);

        if (hasHistoryGuard) {
            window.history.replaceState($.extend({}, window.history.state || {}, { testTaking: true }), '', window.location.href);
            window.history.pushState({ testTakingGuard: true }, '', window.location.href);

            $(window).on('popstate.testExitGuard', function () {
                if ($form.data('test-allow-exit')) {
                    return;
                }

                window.history.pushState({ testTakingGuard: true }, '', window.location.href);
                confirmTestExit($form);
            });
        }

        $(document).on('click.testExitGuard', 'a[href]', function (event) {
            var link = this;
            var href = String($(link).attr('href') || '');

            if ($form.data('test-allow-exit') || !$form.closest('.test-taking-shell').length) {
                return;
            }

            if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href) || /^mailto:/i.test(href) || /^tel:/i.test(href)) {
                return;
            }

            event.preventDefault();
            confirmTestExit($form).then(function (keepTaking) {
                if (!keepTaking) {
                    return;
                }
            });
        });

        $(document).on('keydown.testExitGuard', function (event) {
            var key = String(event.key || '').toLowerCase();
            var isReloadShortcut = key === 'f5' || ((event.ctrlKey || event.metaKey) && key === 'r');

            if (!isReloadShortcut || $form.data('test-allow-exit')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            confirmTestExit($form);
        });

        $form.on('submit.testExitGuard', function (event) {
            var submitter = event.originalEvent && event.originalEvent.submitter;
            if (submitter && submitter.name === 'test_action' && submitter.value !== 'save_exit' && !this.checkValidity()) {
                return;
            }

            $form.data('test-allow-exit', true);
        });

        $(window).on('beforeunload.testExitGuard', function (event) {
            if ($form.data('test-allow-exit')) {
                return undefined;
            }

            var message = $form.attr('data-audio-visual-mode') === '1'
                ? 'La evaluación audiovisual está en curso. Si sales o recargas, el intento quedará cerrado y no podrás ingresar nuevamente.'
                : 'La evaluacion esta en curso. Si sales, se guardara el avance y podras continuar con el tiempo restante.';
            event.preventDefault();
            event.originalEvent.returnValue = message;
            return message;
        });

        $(window).on('pagehide.testExitGuard', function () {
            if ($form.data('test-allow-exit')) {
                return;
            }

            saveDraftAnswers($form, {
                immediate: true,
                beacon: true,
                force: true,
                reason: 'page_exit'
            });
        });
    }

    $('.test-taking-shell form[data-test-taking-form]').each(function () {
        initTestExitGuard($(this));
    });

    $(document).on('submit', 'form[data-confirm-submit]', function (event) {
        var form = this;
        var $form = $(form);
        var message = normalizeLineBreaks($form.data('confirm-submit') || 'Deseas continuar?');

        if ($form.data('confirm-submitted')) {
            return;
        }

        event.preventDefault();

        appSwalConfirm({
            title: 'Confirmar accion',
            html: '<div class="text-start">' + textToSafeHtml(message) + '</div>',
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }).then(function (confirmed) {
            if (confirmed) {
                $form.data('confirm-submitted', true);
                markFormProcessing($form);
                form.submit();
            }
        });
    });

    $(document).on('click', 'a[data-confirm-link]', function (event) {
        var link = this;
        var $link = $(link);
        var href = String($link.attr('href') || '');
        if ($link.data('confirm-linked') || !href || href === '#' || $link.hasClass('disabled') || $link.attr('aria-disabled') === 'true') {
            return;
        }

        event.preventDefault();

        appSwalConfirm({
            title: $link.data('confirm-title') || 'Confirmar accion',
            text: $link.data('confirm-link') || 'Deseas continuar?',
            confirmButtonText: $link.data('confirm-button') || 'Aceptar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            $link.data('confirm-linked', true);
            window.location.href = href;
        });
    });

    function initEvaluationSurveyControl($form) {
        if (!$form.length || $form.data('evaluation-control-ready')) {
            return;
        }

        $form.data('evaluation-control-ready', true);
        var mode = String($form.data('evaluation-control-mode') || 'off');
        var activityUrl = String($form.data('evaluation-activity-url') || '');
        if (!activityUrl) {
            return;
        }

        var csrf = String($form.find('input[name="csrf_token"]').val() || '');
        var sendHeartbeat = function () {
            var body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('event_type', 'heartbeat');
            fetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'},
                body: body.toString(),
                keepalive: true
            }).catch(function () {});
        };
        sendHeartbeat();
        window.setInterval(sendHeartbeat, 25000);
        if (mode === 'off') {
            return;
        }

        var audioVisualBrowserLog = initAudioVisualBrowserLog($form);
        var strictMode = mode === 'supervised' || mode === 'supervised_audio_visual';
        var sendEvent = function (eventType, metadata, keepalive) {
            if (!activityUrl) {
                return;
            }
            if (mode === 'supervised_audio_visual'
                && !$form.data('evaluation-supervised-active')
                && eventType !== 'supervised_started') {
                return;
            }
            var body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('event_type', eventType);
            body.set('metadata', JSON.stringify(metadata || {}));
            fetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'},
                body: body.toString(),
                keepalive: Boolean(keepalive)
            }).catch(function () {});
        };

        var gate = $form.find('[data-evaluation-supervised-gate]');
        var fullscreenTarget = $form.closest('.evaluation-take-panel')[0] || $form[0];
        var setActive = function (warning) {
            $form.removeClass('is-evaluation-supervised-locked');
            gate.addClass('d-none');
            $form.data('evaluation-supervised-active', true).data('evaluation-countdown-started', true);
            $form.trigger('evaluation:activated');
            if (warning) {
                $form.addClass('is-evaluation-control-warning');
            }
        };

        if (mode === 'supervised' && gate.length) {
            $form.addClass('is-evaluation-supervised-locked');
            var start = gate.find('[data-evaluation-supervised-start]');
            var continueButton = gate.find('[data-evaluation-supervised-continue]');
            var status = gate.find('[data-evaluation-supervised-status]');
            start.on('click.evaluationControl', function () {
                var request = fullscreenTarget.requestFullscreen ? fullscreenTarget.requestFullscreen() : Promise.reject(new Error('unavailable'));
                Promise.resolve(request).then(function () {
                    sendEvent('supervised_started');
                    sendEvent('fullscreen_entered');
                    setActive(false);
                }).catch(function () {
                    sendEvent(document.fullscreenEnabled ? 'fullscreen_denied' : 'fullscreen_unavailable');
                    status.text('No fue posible activar pantalla completa. Puedes continuar con una advertencia para registrar la incidencia.');
                    start.addClass('d-none');
                    continueButton.removeClass('d-none');
                });
            });
            continueButton.on('click.evaluationControl', function () {
                sendEvent('supervised_started', {fullscreen: false});
                setActive(true);
            });
            document.addEventListener('fullscreenchange', function () {
                if (document.fullscreenElement === fullscreenTarget) {
                    sendEvent('fullscreen_entered');
                } else if (!$form.hasClass('is-evaluation-supervised-locked')) {
                    sendEvent('fullscreen_exited');
                    $form.addClass('is-evaluation-control-warning');
                }
            });
        }

        if (mode === 'supervised_audio_visual' && gate.length) {
            $form.addClass('is-evaluation-supervised-locked');
            var mediaStream = null;
            var mediaRecorder = null;
            var mediaEvidenceId = '';
            var mediaChunkNumber = 0;
            var mediaUploadChain = Promise.resolve();
            var mediaPendingChunks = 0;
            var mediaMaxPendingChunks = 4;
            var mediaQualityProfile = 'economical';
            var voicePolicy = String($form.data('audio-visual-voice-policy') || 'warn');
            var mediaStartedAt = 0;
            var mediaFinalized = false;
            var mediaStart = gate.find('[data-evaluation-supervised-start]');
            var mediaContinue = gate.find('[data-evaluation-supervised-continue]');
            var mediaConsent = gate.find('[data-audio-visual-consent]');
            var mediaStatus = gate.find('[data-audio-visual-status]');
            var mediaReconnectPanel = $form.find('[data-audio-visual-reconnect-panel]');
            var mediaReconnectButton = mediaReconnectPanel.find('[data-audio-visual-reconnect]');
            var mediaFailureUrl = String($form.data('media-failure-url') || '');
            var screenController = null;
            var screenCapturePromise = null;
            var voiceTimer = null;
            var voiceContext = null;
            var mediaBrowserCompatibility = applyAudioVisualBrowserCompatibility($form);
            audioVisualBrowserLog('audio_visual_browser_compatibility', {source: mediaBrowserCompatibility.source, ok: mediaBrowserCompatibility.ok, user_agent: String(window.navigator && window.navigator.userAgent || '').slice(0, 160)});
            var requestFullscreen = function () {
                if (document.fullscreenElement === fullscreenTarget) return Promise.resolve();
                if (!document.fullscreenEnabled || typeof fullscreenTarget.requestFullscreen !== 'function') {
                    return Promise.reject(new Error(document.fullscreenEnabled ? 'fullscreen_unavailable' : 'fullscreen_denied'));
                }
                return Promise.resolve(fullscreenTarget.requestFullscreen());
            };
            mediaStart.prop('disabled', true);
            mediaConsent.on('change.evaluationAudioVisual', function () { mediaStart.prop('disabled', !this.checked || !mediaBrowserCompatibility.ok); });
            var mediaRisk = function (type, severity, metadata) {
                audioVisualBrowserLog(type, Object.assign({severity: severity || 'attention'}, metadata || {}));
                if (!$form.data('evaluation-supervised-active')) return;
                var riskUrl = String($form.data('media-risk-url') || ''); if (!riskUrl) return;
                var payload = new URLSearchParams(); payload.set('csrf_token', csrf); payload.set('event_type', type); payload.set('severity', severity || 'attention'); payload.set('evidence_id', mediaEvidenceId); payload.set('metadata', JSON.stringify(metadata || {}));
                fetch(riskUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: payload.toString(), keepalive: true}).catch(function () {});
            };
            var createSubmissionProgress = function () {
                var $panel = $('<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-75" style="z-index: 2400" role="dialog" aria-modal="true" aria-labelledby="evaluation-save-progress-title"><div class="bg-white rounded-3 shadow p-4" style="width:min(92vw,520px)"><h2 id="evaluation-save-progress-title" class="h5 mb-2">Guardando evaluación</h2><p class="mb-3" data-evaluation-save-progress-label>Preparando el cierre…</p><div class="progress" role="progressbar" aria-label="Progreso del guardado"><div class="progress-bar progress-bar-striped progress-bar-animated" data-evaluation-save-progress-bar style="width:5%">5%</div></div><p class="small text-muted mt-2 mb-0" data-evaluation-save-progress-detail>No cierres ni recargues esta ventana.</p></div></div>');
                $(document.fullscreenElement || document.body).append($panel);
                return {
                    update: function (percent, label, detail) { var value = Math.max(0, Math.min(100, Math.round(percent))); $panel.find('[data-evaluation-save-progress-bar]').css('width', value + '%').text(value + '%'); $panel.find('[data-evaluation-save-progress-label]').text(label); if (detail) $panel.find('[data-evaluation-save-progress-detail]').text(detail); },
                    remove: function () { $panel.remove(); }
                };
            };
            var stopVoiceDetection = function () {
                if (voiceTimer) window.clearInterval(voiceTimer);
                voiceTimer = null;
                if (voiceContext) voiceContext.close().catch(function () {});
                voiceContext = null;
            };
            var startVoiceDetection = function (stream) {
                stopVoiceDetection();
                try {
                    var AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextConstructor) throw new Error('audio_context_unavailable');
                    voiceContext = new AudioContextConstructor();
                    var analyser = voiceContext.createAnalyser();
                    analyser.fftSize = 1024;
                    voiceContext.createMediaStreamSource(stream).connect(analyser);
                    var timeBuffer = new Uint8Array(analyser.fftSize);
                    var frequencyBuffer = new Uint8Array(analyser.frequencyBinCount);
                    var lastVoiceRisk = 0;
                    voiceTimer = window.setInterval(function () {
                        if (!$form.data('evaluation-supervised-active') || !voiceContext) return;
                        analyser.getByteTimeDomainData(timeBuffer);
                        analyser.getByteFrequencyData(frequencyBuffer);
                        var sum = 0;
                        for (var index = 0; index < timeBuffer.length; index++) {
                            var centered = (timeBuffer[index] - 128) / 128;
                            sum += centered * centered;
                        }
                        var rms = Math.sqrt(sum / timeBuffer.length);
                        var strongBands = 0;
                        var minBin = Math.max(1, Math.floor(85 * analyser.fftSize / voiceContext.sampleRate));
                        var maxBin = Math.min(frequencyBuffer.length - 2, Math.ceil(3400 * analyser.fftSize / voiceContext.sampleRate));
                        for (var bin = minBin; bin <= maxBin; bin++) {
                            if (frequencyBuffer[bin] > 160 && frequencyBuffer[bin] >= frequencyBuffer[bin - 1] && frequencyBuffer[bin] >= frequencyBuffer[bin + 1]) strongBands++;
                        }
                        if (rms > 0.08 && strongBands >= 4 && Date.now() - lastVoiceRisk > 8000) {
                            lastVoiceRisk = Date.now();
                            mediaRisk('multiple_voice_possible', voicePolicy === 'log' ? 'info' : 'attention', {rms: Number(rms.toFixed(4)), strong_bands: strongBands, source: 'browser_preliminary', configured_action: voicePolicy});
                            if (voicePolicy === 'warn') showNotification('warning', 'Se detectó una posible multiplicidad de voces. El evento quedó registrado.');
                            if (voicePolicy === 'pause') {
                                $form.addClass('is-audio-visual-interrupted');
                                $form.find('button[type="submit"]').prop('disabled', true);
                                mediaStatus.removeClass('alert-success alert-secondary').addClass('alert-danger').text('Se detectó una posible multiplicidad de voces. Revisa el entorno y reconecta el control para continuar.');
                                mediaReconnectPanel.removeClass('d-none');
                            }
                        }
                    }, 1000);
                } catch (error) {
                    mediaRisk('voice_analysis_unavailable', 'attention', {reason: error.message || 'audio_context_error'});
                }
            };
            var uploadChunk = function (blob, number) {
                var data = new FormData(); data.append('csrf_token', csrf); data.append('evidence_id', mediaEvidenceId); data.append('chunk_number', String(number)); data.append('mime_type', blob.type || 'video/webm'); data.append('chunk', blob, 'chunk-' + number + '.bin');
                return fetch(String($form.data('media-chunk-url') || ''), {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body: data}).then(function (response) { if (!response.ok) throw new Error('chunk_upload_failed'); return response.json().then(function (payload) { if (!payload || !payload.ok) throw new Error((payload && payload.reason) || 'chunk_upload_rejected'); return payload; }); });
            };
            var uploadChunkWithRetry = function (blob, number) {
                var failurePolicy = String($form.data('audio-visual-upload-failure-policy') || 'continue');
                var maxAttempts = 3;
                var send = function (attempt) { return uploadChunk(blob, number).catch(function (error) {
                    if (attempt < maxAttempts) {
                        mediaRisk('upload_retry_requested', 'attention', {chunk_number: number, attempt: attempt, reason: error.message || 'network_error'});
                        return new Promise(function (resolve) { window.setTimeout(resolve, 1000); }).then(function () { return send(attempt + 1); });
                    }
                    throw error;
                }); };
                return send(1);
            };
            var queueChunk = function (blob) { if (!blob || !blob.size) return; if (mediaPendingChunks >= mediaMaxPendingChunks) { mediaRisk('recording_upload_backlog', 'risk', {pending_chunks: mediaPendingChunks, limit: mediaMaxPendingChunks}); return; } mediaPendingChunks++; var number = mediaChunkNumber++; mediaUploadChain = mediaUploadChain.then(function () { return uploadChunkWithRetry(blob, number); }).catch(function (error) { mediaRisk('recording_upload_failed', 'risk', {chunk_number: number, attempts: 3, reason: error.message}); if (mediaFailureUrl) { var failureData = new URLSearchParams(); failureData.append('csrf_token', csrf); failureData.append('evidence_id', mediaEvidenceId); failureData.append('chunk_number', String(number)); failureData.append('reason', error.message || 'chunk_upload_failed'); window.fetch(mediaFailureUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: failureData.toString(), keepalive: true}).catch(function () {}); } }).then(function () { mediaPendingChunks = Math.max(0, mediaPendingChunks - 1); }); };
            var startMedia = function () {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) return Promise.reject(new Error('media_unsupported'));
                var initPayload = new URLSearchParams(); initPayload.set('csrf_token', csrf); initPayload.set('consented', '1');
                return fetch(String($form.data('media-init-url') || ''), {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'}, body: initPayload.toString()}).then(function (response) { return response.json().then(function (payload) { if (!response.ok || !payload.ok) throw new Error(payload.reason || 'media_init_failed'); return payload; }); }).then(function (payload) {
                    mediaEvidenceId = String(payload.evidence_id || '');
                    mediaQualityProfile = String(payload.quality_profile || 'economical');
                    return requestAudioVisualDevices($form);
                }).then(function (stream) {
                    mediaStream = stream; mediaStartedAt = 0; mediaChunkNumber = 0;
                    var cameraTrack = stream.getVideoTracks()[0];
                    var microphoneTrack = stream.getAudioTracks()[0];
                    if (!cameraTrack || cameraTrack.readyState !== 'live') throw new Error('camera_not_operational');
                    if (!microphoneTrack || microphoneTrack.readyState !== 'live') throw new Error('microphone_not_operational');
                    setAudioVisualCheck($form, 'camera', 'Cámara', 'ok', 'Acceso concedido y cámara activa.');
                    setAudioVisualCheck($form, 'microphone', 'Micrófono', 'ok', 'Acceso concedido y micrófono activo.');
                    var preview = gate.find('[data-audio-visual-preview]').first()[0];
                    if (preview) { preview.srcObject = stream; preview.classList.remove('d-none'); preview.play().catch(function () {}); }
                    var candidates = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus', 'video/webm']; var mime = candidates.find(function (candidate) { return typeof MediaRecorder.isTypeSupported !== 'function' || MediaRecorder.isTypeSupported(candidate); }) || '';
                    var quality = {economical: {video: 400000, audio: 48000}, standard: {video: 700000, audio: 80000}, high: {video: 1200000, audio: 128000}}[mediaQualityProfile] || {video: 400000, audio: 48000};
                    var recorderOptions = mime ? {mimeType: mime, videoBitsPerSecond: quality.video, audioBitsPerSecond: quality.audio} : {};
                    mediaRisk('audio_visual_recorder_format_selected', 'info', {mime_type: mime || 'browser_default', quality_profile: mediaQualityProfile});
                    try {
                        mediaRecorder = new MediaRecorder(stream, recorderOptions);
                        mediaRisk('audio_visual_recorder_created', 'info', {mime_type: mime || 'browser_default'});
                    } catch (error) {
                        mediaRisk('audio_visual_recorder_failed', 'risk', {mime_type: mime || 'browser_default', reason: error && (error.name || error.message) || 'recorder_error'});
                        try { mediaRecorder = new MediaRecorder(stream); mediaRisk('audio_visual_recorder_created', 'info', {mime_type: 'browser_default', fallback: true}); } catch (fallbackError) {
                            mediaRisk('audio_visual_recorder_failed', 'risk', {mime_type: 'browser_default', fallback: true, reason: fallbackError && (fallbackError.name || fallbackError.message) || 'recorder_error'});
                            fallbackError.audioVisualComponent = 'recording';
                            fallbackError.audioVisualMessage = 'El navegador no pudo iniciar la grabación audiovisual.';
                            throw fallbackError;
                        }
                    }
                mediaRecorder.ondataavailable = function (event) { queueChunk(event.data); }; mediaRecorder.onerror = function (event) { mediaRisk('audio_visual_recorder_error', 'risk', {reason: event && (event.error && (event.error.name || event.error.message) || event.type) || 'recorder_error'}); };
                    startVoiceDetection(stream);
                    stream.getTracks().forEach(function (track) { ['mute', 'unmute', 'ended'].forEach(function (eventName) { track.addEventListener(eventName, function () { var kind = track.kind === 'video' ? 'camera' : 'microphone'; var label = kind === 'camera' ? 'Cámara' : 'Micrófono'; if (eventName === 'unmute' && track.readyState === 'live') { setAudioVisualCheck($form, kind, label, 'ok', label + ' activo nuevamente.'); if (mediaStream && mediaStream.getTracks().every(function (item) { return item.readyState === 'live'; }) && screenController && screenController.isReady() && mediaRecorder && mediaRecorder.state === 'recording') { $form.removeClass('is-audio-visual-interrupted'); mediaReconnectPanel.addClass('d-none'); $form.find('button[type="submit"]').prop('disabled', false); mediaStatus.removeClass('alert-danger').addClass('alert-success').text('Cámara, micrófono y captura activos.'); } return; } mediaRisk('audio_visual_' + eventName, 'risk', {kind: track.kind}); setAudioVisualCheck($form, kind, label, 'error', label + ' se interrumpió o dejó de responder.'); setAudioVisualReconnectStatus($form, kind, 'interrumpido o sin respuesta'); $form.addClass('is-audio-visual-interrupted'); mediaReconnectPanel.removeClass('d-none'); mediaStatus.removeClass('alert-success').addClass('alert-danger').text(label + ' interrumpido. Revisa el permiso indicado antes de continuar.'); if ($form.data('audio-visual-interruption-policy') !== 'continue') $form.find('button[type="submit"]').prop('disabled', true); }); }); });
                    mediaStatus.removeClass('alert-secondary alert-danger').addClass('alert-success').text('Cámara y micrófono activos. Verificando captura de pantalla.');
                });
            };
            var startRecording = function () {
                if (!mediaRecorder || mediaRecorder.state !== 'inactive') return false;
                mediaStartedAt = Date.now();
                mediaChunkNumber = 0;
                mediaRecorder.start(15000);
                mediaRisk('audio_visual_recorder_started', 'info', {timeslice_ms: 15000});
                return true;
            };
            mediaStart.on('click.evaluationAudioVisual', function () {
                mediaStart.prop('disabled', true);
                if (!screenController) {
                    screenController = createAudioVisualScreenCapture($form, csrf, function () { return mediaEvidenceId; }, {preferCanvas: mediaBrowserCompatibility.source === 'canvas', onDiagnostic: function (eventType, metadata) { mediaRisk(eventType, 'info', metadata); }});
                    mediaRisk('audio_visual_browser_compatibility', 'info', {source: mediaBrowserCompatibility.source, ok: mediaBrowserCompatibility.ok, user_agent: String(window.navigator && window.navigator.userAgent || '').slice(0, 160)});
                    screenController.onInterrupted = function (reason) {
                        mediaRisk(reason, 'risk', {source: screenController.source});
                        setAudioVisualCheck($form, 'screen', 'Captura', 'error', 'La selección de pantalla se interrumpió.');
                        setAudioVisualReconnectStatus($form, 'screen', 'interrumpida');
                        $form.addClass('is-audio-visual-interrupted');
                        mediaStatus.removeClass('alert-success').addClass('alert-danger').text('La captura de pantalla se interrumpió.');
                        mediaReconnectPanel.removeClass('d-none');
                        if ($form.data('audio-visual-interruption-policy') !== 'continue') $form.find('button[type="submit"]').prop('disabled', true);
                    };
                    screenController.onFailure = function (reason, error) {
                        var detail = error && (error.message || error.name) || 'capture_failed';
                        mediaRisk(reason, 'risk', {source: screenController.source || 'unknown', detail: detail});
                        // No abrir el panel de reconexión por un fallo puntual
                        // de captura/subida: la pista de pantalla sigue activa.
                        setAudioVisualCheck($form, 'screen', 'Captura', 'warning', 'Una captura puntual no pudo guardarse; la captura continúa activa.');
                        mediaStatus.removeClass('alert-success alert-danger').addClass('alert-warning').text('Una captura puntual no pudo guardarse. La captura de pantalla continúa activa.');
                    };
                }
                var mediaRequest = startMedia().then(function () {
                    setAudioVisualCheck($form, 'screen', 'Captura', 'pending', 'Solicitando acceso a pantalla completa…');
                    screenCapturePromise = screenController.start({deferValidation: true});
                    return screenCapturePromise.then(function () { return screenController.validate(); }).then(function () {
                        if (!mediaRecorder || mediaRecorder.state !== 'inactive') return Promise.reject(new Error('recorder_not_ready'));
                        return {ok: true};
                    });
                }).catch(function (error) {
                    mediaRisk('audio_visual_start_failed', 'risk', {component: error && error.audioVisualComponent || 'unknown', name: error && error.name || 'Error', reason: error && error.message || 'media_start_failed'});
                    screenController.stop();
                    var failedStream = $form.data('audio-visual-stream');
                    if (failedStream) failedStream.getTracks().forEach(function (track) { track.stop(); });
                    return {ok: false, error: error};
                });
                var fullscreenRequest = mediaRequest.then(function (mediaOutcome) {
                    if (!mediaOutcome.ok) return {ok: false, error: mediaOutcome.error};
                    return requestFullscreen().then(function () {
                        return {ok: true};
                    }).catch(function (error) {
                        return {ok: false, error: error};
                    });
                });
                Promise.all([mediaRequest, fullscreenRequest]).then(function (outcomes) {
                    var mediaOutcome = outcomes[0];
                    var fullscreenOutcome = outcomes[1];
                    if (!mediaOutcome.ok) {
                        var failedComponent = mediaOutcome.error && mediaOutcome.error.audioVisualComponent;
                        var failedLabel = failedComponent === 'camera' ? 'La cámara' : failedComponent === 'microphone' ? 'El micrófono' : failedComponent === 'recording' ? 'La grabación audiovisual' : 'La configuración audiovisual';
                        var failureMessage = (mediaOutcome.error && mediaOutcome.error.audioVisualMessage) || (failedLabel + ' no está disponible. Revisa el permiso indicado y vuelve a intentarlo.');
                        setAudioVisualReconnectStatus($form, failedComponent, failureMessage);
                        mediaRisk('permission_or_recording_failed', 'risk', {reason: mediaOutcome.error && mediaOutcome.error.message ? mediaOutcome.error.message : 'media_init_failed'});
                        mediaStatus.removeClass('alert-secondary').addClass('alert-danger').text(failureMessage);
                        mediaStart.prop('disabled', false);
                        mediaContinue.addClass('d-none');
                        mediaReconnectPanel.removeClass('d-none');
                        return;
                    }
                    if (!fullscreenOutcome.ok) {
                        var fullscreenEvent = fullscreenOutcome.error && fullscreenOutcome.error.message === 'fullscreen_unavailable' ? 'fullscreen_unavailable' : 'fullscreen_denied';
                        sendEvent(fullscreenEvent);
                        mediaStatus.removeClass('alert-secondary alert-success').addClass('alert-warning').text('La cámara y el micrófono están activos, pero no fue posible activar pantalla completa. Puedes continuar con advertencia.');
                        mediaStart.addClass('d-none');
                        mediaContinue.removeClass('d-none').data('audio-visual-active', true);
                        return;
                    }
                    if (!startRecording()) {
                        mediaStatus.removeClass('alert-success alert-secondary').addClass('alert-danger').text('No se pudo iniciar la grabación audiovisual. Intenta nuevamente.');
                        mediaReconnectPanel.removeClass('d-none');
                        return;
                    }
                    sendEvent('supervised_started', {audio_visual: true, evidence_id: mediaEvidenceId});
                    sendEvent('fullscreen_entered');
                    setActive(false);
                    screenController.startPeriodic();
                });
            });
            mediaContinue.on('click.evaluationAudioVisual', function () {
                if ($(this).data('audio-visual-active')) {
                    sendEvent('supervised_started', {audio_visual: true, fullscreen: false, evidence_id: mediaEvidenceId});
                } else {
                    mediaRisk('continued_without_audio_visual', 'risk', {});
                }
                if (!startRecording()) {
                    mediaStatus.removeClass('alert-success alert-secondary').addClass('alert-danger').text('No se pudo iniciar la grabación audiovisual. Intenta nuevamente.');
                    mediaReconnectPanel.removeClass('d-none');
                    return;
                }
                setActive(true);
                if (screenController) screenController.startPeriodic();
            });
            mediaReconnectButton.on('click.evaluationAudioVisual', function () {
                var button = this; $(button).prop('disabled', true).text('Solicitando permisos...');
                if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
                if (mediaStream) mediaStream.getTracks().forEach(function (track) { track.stop(); });
                if (screenController) screenController.stop();
                setAudioVisualCheck($form, 'screen', 'Captura', 'pending', 'Solicitando acceso a pantalla completa…');
                screenCapturePromise = screenController ? screenController.start({deferValidation: true}) : Promise.resolve(true);
                startMedia().then(function () {
                    return screenCapturePromise.then(function () { return screenController.validate(); });
                }).then(function () {
                    if (!mediaRecorder || mediaRecorder.state !== 'inactive') throw new Error('recorder_not_ready');
                    if (!startRecording()) throw new Error('recorder_not_ready');
                    mediaReconnectPanel.addClass('d-none'); $form.removeClass('is-audio-visual-interrupted'); $form.find('button[type="submit"]').prop('disabled', false); setActive(false);
                    if (screenController) screenController.startPeriodic();
                    $(button).prop('disabled', false).text('Reintentar conexión audiovisual');
                }).catch(function (error) {
                    mediaReconnectPanel.removeClass('d-none'); $(button).prop('disabled', false).text('Reintentar conexión audiovisual');
                    var reconnectComponent = error && error.audioVisualComponent ? error.audioVisualComponent : 'microphone';
                    var reconnectMessage = (error && error.audioVisualMessage) || 'No se pudo reconectar. Revisa los permisos de cámara, micrófono y pantalla.';
                    setAudioVisualReconnectStatus($form, reconnectComponent, reconnectMessage);
                    mediaStatus.removeClass('alert-success').addClass('alert-danger').text(reconnectMessage);
                });
            });
            document.addEventListener('fullscreenchange', function () {
                if (document.fullscreenElement === fullscreenTarget) {
                    sendEvent('fullscreen_entered');
                } else if (!$form.hasClass('is-evaluation-supervised-locked')) {
                    sendEvent('fullscreen_exited');
                    $form.addClass('is-evaluation-control-warning');
                }
            });
            $form.on('submit.evaluationAudioVisual', function (event) {
                if (mediaFinalized) return;
                if (!mediaRecorder || mediaRecorder.state === 'inactive' || !mediaEvidenceId || !$form.data('evaluation-supervised-active')) {
                    event.preventDefault();
                    mediaStatus.removeClass('alert-success alert-secondary').addClass('alert-warning').text('La cámara, el micrófono y la captura deben validarse antes de continuar.');
                    return;
                }
                var submitter = event.originalEvent && event.originalEvent.submitter; if (submitter && submitter.value === 'draft') return;
                $form.data('evaluation-submission-in-progress', true);
                event.preventDefault(); mediaFinalized = true;
                clearFormProcessing($form);
                var progress = createSubmissionProgress();
                var answersPayload = new FormData($form[0]);
                var submittedActions = answersPayload.getAll('evaluation_survey_action');
                var finalAction = submittedActions.length ? String(submittedActions[submittedActions.length - 1]) : 'complete';
                answersPayload.set('evaluation_survey_action', finalAction);
                $form.find('button, input, textarea, select').prop('disabled', true);
                progress.update(5, 'Deteniendo video y capturas…', 'No se generarán nuevos archivos mientras se guardan las respuestas.');
                if (screenController && screenController.stopScheduling) screenController.stopScheduling();
                var recorderStopped = mediaRecorder.state === 'inactive' ? Promise.resolve() : new Promise(function (resolve) { mediaRecorder.addEventListener('stop', resolve, {once: true}); mediaRecorder.stop(); });
                if (mediaStream) mediaStream.getTracks().forEach(function (track) { track.stop(); });
                progress.update(10, 'Guardando respuestas…', 'Las respuestas y el estado se guardan primero.');
                fetch($form.attr('action') || window.location.href, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: answersPayload}).then(function (response) { return response.json().then(function (result) { if (!response.ok || !result.ok) { var error = new Error(result.message || 'answers_save_failed'); error.payload = result; throw error; } return result; }); }).then(function (result) {
                    progress.update(40, 'Respuestas guardadas con éxito', 'El intento quedó completado. Continuando con la evidencia audiovisual.');
                    return recorderStopped.then(function () { progress.update(55, 'Enviando fragmentos de video…', 'Cada fragmento tiene hasta 3 intentos.'); return Promise.resolve(mediaUploadChain); }).then(function () {
                        progress.update(82, 'Enviando capturas…', 'Cada captura tiene hasta 3 intentos.');
                        return screenController && screenController.flushUploads ? screenController.flushUploads().catch(function (error) { mediaRisk('screen_capture_upload_failed', 'risk', {reason: error.message || 'capture_upload_failed', attempts: 3}); }) : null;
                    }).then(function () {
                        progress.update(92, 'Finalizando evidencia audiovisual…', 'Las respuestas ya están guardadas y no serán modificadas.');
                        var payload = new URLSearchParams(); payload.set('csrf_token', csrf); payload.set('evidence_id', mediaEvidenceId); payload.set('duration_seconds', String(Math.max(0, Math.round((Date.now() - mediaStartedAt) / 1000))));
                        var requestFinalize = function (attempt) { return fetch(String($form.data('media-finalize-url') || ''), {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'Accept': 'application/json'}, body: payload.toString()}).then(function (response) { return response.json().catch(function () { return {ok: false, reason: 'invalid_finalize_response'}; }).then(function (finalizeResult) { if (!finalizeResult.ok && attempt < 3) return new Promise(function (resolve) { window.setTimeout(resolve, 700 * attempt); }).then(function () { return requestFinalize(attempt + 1); }); if (!finalizeResult.ok) mediaRisk('finalize_failed', 'risk', {reason: finalizeResult.reason || 'finalize_rejected', attempts: 3}); return finalizeResult; }); }).catch(function (error) { if (attempt < 3) return new Promise(function (resolve) { window.setTimeout(resolve, 700 * attempt); }).then(function () { return requestFinalize(attempt + 1); }); mediaRisk('finalize_failed', 'risk', {reason: error.message || 'network_error', attempts: 3}); return {ok: false}; }); };
                        return requestFinalize(1).then(function () { progress.update(100, 'Respuestas guardadas con éxito', 'El intento quedó completado.'); return result; });
                    });
                }).then(function (result) { $form.data('test-allow-exit', true); if (mediaStream) mediaStream.getTracks().forEach(function (track) { track.stop(); }); if (screenController) screenController.stop(); window.location.href = result.drawer_url || result.redirect_url || window.location.href; }).catch(function (error) { progress.remove(); $form.removeData('evaluation-submission-in-progress'); mediaFinalized = false; $form.find('button, input, textarea, select').prop('disabled', false); if (error && error.payload && error.payload.message) showNotification('warning', error.payload.message); else { mediaRisk('finalize_failed', 'risk', {}); mediaStatus.removeClass('alert-success').addClass('alert-danger').text('Las respuestas no pudieron guardarse. Revisa la conexión e inténtalo nuevamente.'); } });
            });
        }

        document.addEventListener('visibilitychange', function () { sendEvent(document.hidden ? 'tab_hidden' : 'tab_visible'); });
        window.addEventListener('blur', function () { sendEvent('window_blurred'); });
        window.addEventListener('focus', function () { sendEvent('window_focused'); });
        ['copy', 'cut', 'paste', 'contextmenu', 'dragstart'].forEach(function (eventName) {
            $form.on(eventName + '.evaluationControl', function (event) {
                if (!strictMode) { return; }
                event.preventDefault();
                sendEvent(eventName === 'contextmenu' ? 'context_menu_blocked' : eventName + '_blocked');
            });
        });
        $form.on('keydown.evaluationControl', function (event) {
            if (!strictMode) { return; }
            var key = String(event.key || '').toLowerCase();
            var blocked = event.key === 'PrintScreen' ? 'suspicious_key_printscreen' : null;
            if (!blocked && (event.ctrlKey || event.metaKey) && key === 'p') { blocked = 'print_blocked'; }
            if (!blocked && (event.ctrlKey || event.metaKey) && key === 's') { blocked = 'suspicious_key_save'; }
            if (!blocked && (event.ctrlKey || event.metaKey) && key === 'c') { blocked = 'suspicious_key_copy'; }
            if (!blocked && (event.ctrlKey || event.metaKey) && key === 'u') { blocked = 'suspicious_key_devtools'; }
            if (!blocked && event.key === 'F12') { blocked = 'suspicious_key_devtools'; }
            if (blocked) { event.preventDefault(); sendEvent(blocked); }
        });
        window.addEventListener('beforeprint', function () { if (mode === 'supervised') { sendEvent('print_blocked'); } });
        $form.on('change.evaluationControl', 'input, select, textarea', function () { sendEvent('answer_changed'); });
    }

    $('form[data-evaluation-control-form]').each(function () { initEvaluationSurveyControl($(this)); });

    initPromptEditors(document);

    function initEvaluationFormControlSettings() {
        $('form.app-form-stack').each(function () {
            var $form = $(this);
            var $control = $form.find('#control_mode');
            var $fields = $form.find('.evaluation-audio-visual-field');
            if (!$control.length || !$fields.length || $form.data('audio-visual-settings-ready')) return;
            $form.data('audio-visual-settings-ready', true);
            var sync = function () {
                var enabled = $control.val() === 'supervised_audio_visual';
                $fields.toggleClass('d-none', !enabled).attr('aria-hidden', enabled ? 'false' : 'true');
                $fields.find('select, input, textarea').prop('disabled', !enabled);
                $control.prop('disabled', false);
            };
            $control.on('change.evaluationAudioVisualSettings', sync);
            sync();
        });
    }

    initEvaluationFormControlSettings();

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('#appDrawer').hasClass('is-open')) {
            closeDrawer();
        }
    });
});
