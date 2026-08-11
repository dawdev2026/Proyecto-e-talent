(function () {
    'use strict';

    var scriptLoading = false;

    function notify(type, message) {
        if (window.AppNotify && typeof window.AppNotify[type] === 'function') {
            window.AppNotify[type](message);
            return;
        }
        if (type === 'error') {
            console.error(message);
        } else if (type === 'warning') {
            console.warn(message);
        }
    }

    function setShellState(shell, joined, launcherVisible, container) {
        if (shell) {
            shell.classList.toggle('daily-kit-joined', joined);
            shell.classList.toggle('daily-kit-prejoin', !joined && !launcherVisible);
        }
        if (container) {
            container.hidden = launcherVisible;
        }
    }

    function showFallback(container, meetingUrl, message) {
        container.innerHTML = ''
            + '<div class="daily-kit-state">'
            + '<div class="daily-kit-icon" aria-hidden="true">!</div>'
            + '<h2>No se pudo cargar la videollamada</h2>'
            + '<p>' + escapeHtml(message || 'Revisa la conexion o abre la sala en una ventana nueva.') + '</p>'
            + '<a class="daily-kit-button daily-kit-button-secondary" href="' + encodeAttr(meetingUrl || '#') + '" target="_blank" rel="noopener">Abrir en ventana</a>'
            + '</div>';
    }

    function showEnded(container, onRejoin) {
        container.innerHTML = ''
            + '<div class="daily-kit-state">'
            + '<div class="daily-kit-icon" aria-hidden="true">✓</div>'
            + '<h2>Videollamada finalizada</h2>'
            + '<p>La sala se cerro correctamente.</p>'
            + '<button class="daily-kit-button" type="button" data-daily-kit-rejoin>Volver a entrar</button>'
            + '</div>';
        var button = container.querySelector('[data-daily-kit-rejoin]');
        if (button) {
            button.addEventListener('click', onRejoin);
        }
    }

    function loadDailyScript(src, callback, onError) {
        if (window.DailyIframe) {
            callback();
            return;
        }
        if (scriptLoading) {
            window.setTimeout(function () {
                loadDailyScript(src, callback, onError);
            }, 150);
            return;
        }

        scriptLoading = true;
        var script = document.createElement('script');
        script.src = src || 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js/dist/daily-iframe.js';
        script.async = true;
        script.onload = function () {
            scriptLoading = false;
            callback();
        };
        script.onerror = function () {
            scriptLoading = false;
            onError();
        };
        document.head.appendChild(script);
    }

    function initContainer(container) {
        var meetingUrl = container.getAttribute('data-daily-url') || '';
        var meetingToken = container.getAttribute('data-daily-token') || '';
        var userName = container.getAttribute('data-daily-user') || 'Participante';
        var apiUrl = container.getAttribute('data-daily-api') || '';
        var minHeight = container.getAttribute('data-daily-min-height') || '640px';
        var shell = container.closest('[data-daily-shell]');
        var launcher = shell ? shell.querySelector('[data-daily-launcher]') : document.querySelector('[data-daily-launcher]');
        var launchButton = launcher ? launcher.querySelector('[data-daily-launch]') : null;
        var transcriptionPanel = shell ? shell.querySelector('[data-daily-transcription-panel]') : null;
        var transcriptionStart = transcriptionPanel ? transcriptionPanel.querySelector('[data-transcription-start]') : null;
        var transcriptionStop = transcriptionPanel ? transcriptionPanel.querySelector('[data-transcription-stop]') : null;
        var transcriptionStatus = transcriptionPanel ? transcriptionPanel.querySelector('[data-transcription-status]') : null;
        var transcriptionEnabled = container.getAttribute('data-daily-transcription-enabled') === '1';
        var transcriptionAutoStart = container.getAttribute('data-daily-transcription-auto-start') === '1';
        var transcriptionUrl = container.getAttribute('data-transcription-url') || '';
        var snapshotIntervalSeconds = Math.max(5, Math.min(300, parseInt(container.getAttribute('data-transcription-snapshot-interval') || '20', 10) || 20));
        var transcriptLines = [];
        var participantNames = {};
        var transcriptionActive = false;
        var transcriptionPending = false;
        var transcriptionTimer = null;
        var lastTranscriptSnapshot = '';
        var autoTranscriptionRequested = false;
        var abandonConfirmed = false;
        var api = null;

        function closeMeeting() {
            var activeApi = api;
            api = null;
            if (activeApi && typeof activeApi.destroy === 'function') {
                activeApi.destroy();
            }
        }

        function setTranscriptionStatus(message) {
            if (transcriptionStatus) {
                transcriptionStatus.textContent = message;
            }
        }

        function setTranscriptionButtons(active) {
            transcriptionActive = active;
            if (transcriptionStart) {
                transcriptionStart.hidden = active || transcriptionPending;
                transcriptionStart.disabled = !transcriptionEnabled || active || transcriptionPending;
            }
            if (transcriptionStop) {
                transcriptionStop.hidden = !active && !transcriptionPending;
                transcriptionStop.disabled = !transcriptionEnabled || !active;
            }
        }

        function participantIdFrom(payload) {
            if (!payload || typeof payload !== 'object') {
                return '';
            }
            if (payload.session_id || payload.participantId || payload.participant_id || payload.user_id) {
                return String(payload.session_id || payload.participantId || payload.participant_id || payload.user_id);
            }
            return payload.participant && typeof payload.participant === 'object' ? participantIdFrom(payload.participant) : '';
        }

        function participantNameFrom(payload) {
            if (!payload || typeof payload !== 'object') {
                return '';
            }
            var direct = payload.user_name || payload.userName || payload.name || '';
            if (direct) {
                return String(direct).trim();
            }
            return payload.participant && typeof payload.participant === 'object' ? participantNameFrom(payload.participant) : '';
        }

        function rememberParticipant(participant) {
            var id = participantIdFrom(participant);
            var name = participantNameFrom(participant);
            if (id && name) {
                participantNames[id] = name;
            }
        }

        function refreshParticipants() {
            if (!api || typeof api.participants !== 'function') {
                return;
            }
            try {
                var participants = api.participants();
                Object.keys(participants || {}).forEach(function (id) {
                    var participant = participants[id] || {};
                    participant.session_id = participant.session_id || id;
                    rememberParticipant(participant);
                });
            } catch (error) {}
        }

        function transcriptLineFrom(event) {
            var text = event && event.text ? String(event.text).trim() : '';
            if (!text) {
                return '';
            }
            var id = participantIdFrom(event);
            var speaker = participantNameFrom(event) || participantNames[id] || 'Participante';
            return speaker ? speaker + ': ' + text : text;
        }

        function currentTranscriptText() {
            return transcriptLines.join('\n').trim();
        }

        function sendTranscription(action, message, transcriptText, status, useBeacon) {
            if (!transcriptionUrl) {
                return;
            }
            var data = new FormData();
            data.append('csrf_token', container.getAttribute('data-csrf-token') || '');
            data.append('transcription_action', action);
            if (message) {
                data.append('message', message);
            }
            if (transcriptText) {
                data.append('transcript_text', transcriptText);
            }
            if (status) {
                data.append('transcription_status', status);
            }
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon(transcriptionUrl, data);
                return;
            }
            fetch(transcriptionUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        }

        function flushTranscriptionSnapshot(status, useBeacon, force) {
            var text = currentTranscriptText();
            if (!text || (!force && text === lastTranscriptSnapshot)) {
                return;
            }
            lastTranscriptSnapshot = text;
            sendTranscription('snapshot', '', text, status || (transcriptionActive ? 'recording' : 'processing'), !!useBeacon);
        }

        function startTranscriptionTimer() {
            if (transcriptionTimer) {
                return;
            }
            transcriptionTimer = window.setInterval(function () {
                flushTranscriptionSnapshot('recording', false, false);
            }, snapshotIntervalSeconds * 1000);
        }

        function stopTranscriptionTimer() {
            if (transcriptionTimer) {
                window.clearInterval(transcriptionTimer);
                transcriptionTimer = null;
            }
        }

        function markTranscriptionFailed(message) {
            transcriptionPending = false;
            stopTranscriptionTimer();
            flushTranscriptionSnapshot('processing', true, true);
            setTranscriptionStatus('Con error');
            setTranscriptionButtons(false);
            sendTranscription('failed', message || 'Daily no pudo controlar la transcripcion.');
            notify('error', 'No se pudo controlar la transcripcion.');
        }

        function runTranscriptionAction(callback, fallback) {
            try {
                var result = callback();
                if (result && typeof result.catch === 'function') {
                    result.catch(function (error) {
                        markTranscriptionFailed(error && (error.errorMsg || error.message) ? String(error.errorMsg || error.message) : fallback);
                    });
                }
            } catch (error) {
                markTranscriptionFailed(error && (error.errorMsg || error.message) ? String(error.errorMsg || error.message) : fallback);
            }
        }

        function requestTranscriptionStart() {
            if (!api || typeof api.startTranscription !== 'function' || !transcriptionEnabled || transcriptionActive || transcriptionPending) {
                return;
            }
            transcriptionPending = true;
            setTranscriptionStatus('Solicitando inicio...');
            setTranscriptionButtons(false);
            runTranscriptionAction(function () {
                return api.startTranscription({ language: 'es', punctuate: true });
            }, 'Daily rechazo la solicitud de transcripcion.');
        }

        function requestTranscriptionStop() {
            if (!api || typeof api.stopTranscription !== 'function' || !transcriptionEnabled || !transcriptionActive) {
                return;
            }
            flushTranscriptionSnapshot('processing', false, true);
            transcriptionPending = false;
            setTranscriptionStatus('Solicitando detencion...');
            setTranscriptionButtons(true);
            runTranscriptionAction(function () {
                return api.stopTranscription();
            }, 'Daily rechazo la detencion de la transcripcion.');
        }

        function cleanupBeforeAbandon() {
            abandonConfirmed = true;
            stopTranscriptionTimer();
            flushTranscriptionSnapshot(transcriptionActive ? 'processing' : 'available', true, true);
            if (api && transcriptionActive && typeof api.stopTranscription === 'function') {
                try {
                    api.stopTranscription();
                } catch (error) {}
            }
            closeMeeting();
        }

        function hasAbandonRisk() {
            return !abandonConfirmed && !!api && (transcriptionActive || transcriptionPending);
        }

        function confirmAbandon() {
            if (!hasAbandonRisk()) {
                return Promise.resolve(true);
            }
            flushTranscriptionSnapshot(transcriptionActive ? 'processing' : 'available', true, true);
            if (!window.Swal) {
                notify('warning', 'Confirma el abandono de la sala antes de navegar fuera.');
                return Promise.resolve(false);
            }
            return window.Swal.fire({
                title: 'Abandonar sala',
                text: 'La transcripcion esta activa. Guardaremos el ultimo respaldo antes de salir.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Abandonar sala',
                cancelButtonText: 'Seguir en la sala'
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    cleanupBeforeAbandon();
                    return true;
                }
                return false;
            });
        }

        function joinMeeting() {
            abandonConfirmed = false;
            if (launchButton) {
                launchButton.disabled = true;
            }
            if (launcher) {
                launcher.hidden = true;
            }
            setShellState(shell, false, false, container);

            loadDailyScript(apiUrl, function () {
                if (!meetingUrl || !meetingToken || !window.DailyIframe) {
                    showFallback(container, meetingUrl, 'Faltan datos para iniciar la sala.');
                    if (launchButton) {
                        launchButton.disabled = false;
                    }
                    return;
                }

                container.innerHTML = '';
                try {
                    api = window.DailyIframe.createFrame(container, {
                        iframeStyle: {
                            width: '100%',
                            height: '100%',
                            minHeight: minHeight,
                            border: '0',
                            display: 'block'
                        },
                        showLeaveButton: true,
                        showFullscreenButton: true,
                        userName: userName
                    });
                } catch (error) {
                    showFallback(container, meetingUrl, error && error.message ? error.message : '');
                    if (launchButton) {
                        launchButton.disabled = false;
                    }
                    return;
                }

                api.on('joined-meeting', function () {
                    refreshParticipants();
                    setShellState(shell, true, false, container);
                    if (transcriptionPanel && transcriptionEnabled) {
                        transcriptionPanel.hidden = false;
                    }
                    if (transcriptionAutoStart && !autoTranscriptionRequested) {
                        autoTranscriptionRequested = true;
                        window.setTimeout(function () {
                            if (!transcriptionActive && !transcriptionPending) {
                                requestTranscriptionStart();
                            }
                        }, 1200);
                    }
                });
                api.on('participant-joined', function (event) {
                    rememberParticipant(event && event.participant ? event.participant : event);
                });
                api.on('participant-updated', function (event) {
                    rememberParticipant(event && event.participant ? event.participant : event);
                });
                api.on('left-meeting', function () {
                    abandonConfirmed = true;
                    stopTranscriptionTimer();
                    flushTranscriptionSnapshot(transcriptionActive ? 'processing' : 'available', true, true);
                    setShellState(shell, false, false, container);
                    closeMeeting();
                    showEnded(container, joinMeeting);
                    if (launchButton) {
                        launchButton.disabled = false;
                    }
                });
                api.on('error', function (event) {
                    var message = event && (event.errorMsg || event.message || event.type)
                        ? String(event.errorMsg || event.message || event.type)
                        : 'Daily informo un error en la videollamada.';
                    if ((transcriptionPending || transcriptionActive) && message.toLowerCase().indexOf('trans') !== -1) {
                        markTranscriptionFailed(message);
                        return;
                    }
                    notify('error', message);
                });
                api.on('transcription-started', function () {
                    transcriptionPending = false;
                    setTranscriptionStatus('Transcribiendo');
                    setTranscriptionButtons(true);
                    sendTranscription('start');
                    startTranscriptionTimer();
                });
                api.on('transcription-stopped', function () {
                    stopTranscriptionTimer();
                    transcriptionPending = false;
                    setTranscriptionStatus('Procesando');
                    setTranscriptionButtons(false);
                    if (currentTranscriptText()) {
                        flushTranscriptionSnapshot('available', false, true);
                    } else {
                        sendTranscription('stop');
                    }
                });
                api.on('transcription-message', function (event) {
                    var line = transcriptLineFrom(event);
                    if (line) {
                        transcriptLines.push(line);
                    }
                });
                api.on('transcription-error', function (event) {
                    markTranscriptionFailed(event && (event.errorMsg || event.message || event.type) ? String(event.errorMsg || event.message || event.type) : '');
                });

                api.join({
                    url: meetingUrl,
                    token: meetingToken,
                    userName: userName
                }).catch(function (error) {
                    showFallback(container, meetingUrl, error && (error.errorMsg || error.message) ? String(error.errorMsg || error.message) : '');
                    if (launchButton) {
                        launchButton.disabled = false;
                    }
                });

                var frame = container.querySelector('iframe');
                if (frame) {
                    frame.setAttribute('title', container.getAttribute('data-daily-title') || 'Videollamada');
                    frame.setAttribute('allow', 'camera; microphone; fullscreen; display-capture; autoplay');
                    frame.setAttribute('allowfullscreen', 'true');
                }
            }, function () {
                showFallback(container, meetingUrl, 'No se pudo cargar daily-js.');
                if (launchButton) {
                    launchButton.disabled = false;
                }
            });
        }

        if (transcriptionStart) {
            transcriptionStart.addEventListener('click', requestTranscriptionStart);
        }
        if (transcriptionStop) {
            transcriptionStop.addEventListener('click', requestTranscriptionStop);
        }

        document.addEventListener('click', function (event) {
            var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-daily-kit-abandon-allow')) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || !hasAbandonRisk()) {
                return;
            }
            event.preventDefault();
            confirmAbandon().then(function (confirmed) {
                if (confirmed) {
                    window.location.href = new URL(href, window.location.href).href;
                }
            });
        }, true);

        window.addEventListener('beforeunload', cleanupBeforeAbandon);

        if (launchButton) {
            launchButton.addEventListener('click', joinMeeting);
            if (launcher) {
                launcher.hidden = false;
            }
            container.hidden = true;
            return;
        }

        joinMeeting();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function encodeAttr(value) {
        return escapeHtml(value);
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.slice.call(document.querySelectorAll('[data-daily-meeting]')).forEach(initContainer);
    });
})();
