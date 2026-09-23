(function () {
    'use strict';

    var page = document.querySelector('[data-component-validation-page]');
    if (!page) return;

    var button = page.querySelector('[data-component-start]');
    var form = page.querySelector('[data-component-save-form]');
    var payloadInput = page.querySelector('[data-component-validation-payload]');
    var preview = page.querySelector('[data-component-camera-preview]');
    var summary = page.querySelector('[data-component-device-summary]');
    var captureGuidance = page.querySelector('[data-component-capture-guidance]');
    var labels = {camera: 'Cámara frontal', microphone: 'Micrófono', media_recorder: 'Grabación del navegador', screen_capture: 'Captura de pantalla'};
    var statuses = {};
    var componentMessages = {};
    var captureSource = 'unavailable';
    var detectedDevice = null;
    var captureMode = 'canvas';

    function setStatus(key, state, message) {
        statuses[key] = state;
        componentMessages[key] = String(message || '').slice(0, 180);
        var card = page.querySelector('[data-component-status="' + key + '"]');
        if (!card) return;
        var color = state === 'passed' ? 'success' : (state === 'failed' || state === 'not_supported' ? 'danger' : 'warning');
        card.classList.remove('border-success', 'border-danger', 'border-warning', 'text-success', 'text-danger', 'text-warning');
        card.classList.add('border-' + color);
        var label = key === 'screen_capture' && captureSource === 'canvas' ? 'Captura interfaz evaluación (Canvas)' : labels[key];
        card.querySelector('[data-component-label]').textContent = label + ': ' + ({passed: 'correcto', warning: 'aviso', failed: 'falló', not_supported: 'no compatible', not_checked: 'sin comprobar'})[state];
        card.querySelector('[data-component-message]').textContent = message;
    }

    function detectDevice() {
        var nav = navigator;
        var ua = String(nav.userAgent || '');
        var platform = String((nav.userAgentData && nav.userAgentData.platform) || nav.platform || '');
        var ipad = /iPad/i.test(ua) || (/MacIntel/i.test(platform) && nav.maxTouchPoints > 1);
        var browser = 'Desconocido';
        var version = '';
        var patterns = [
            ['Edge', /Edg\/([\d.]+)/],
            ['Opera', /(?:OPR|Opera)\/([\d.]+)/],
            ['Firefox', /Firefox\/([\d.]+)/], ['Chrome', /Chrome\/([\d.]+)/],
            ['Safari', /Version\/([\d.]+).*Safari/]
        ];
        patterns.some(function (entry) {
            var match = ua.match(entry[1]);
            if (!match) return false;
            browser = entry[0]; version = match[1] || ''; return true;
        });
        var os = 'Desconocido';
        if (ipad) os = 'iPadOS';
        else if (/Windows|Windows NT/i.test(platform + ' ' + ua)) os = 'Windows';
        else if (/Android/i.test(ua)) os = 'Android';
        else if (/iPhone|iPad|iPod/i.test(ua)) os = 'iOS';
        else if (/CrOS/i.test(ua)) os = 'ChromeOS';
        else if (/Mac/i.test(platform + ' ' + ua)) os = 'macOS';
        else if (/Linux/i.test(platform + ' ' + ua)) os = 'Linux';
        var androidTablet = /Android/i.test(ua) && !/Mobile/i.test(ua);
        var device = ipad || androidTablet || /Tablet/i.test(ua) ? 'tablet' : (/Android|iPhone|iPod|Mobile/i.test(ua) ? 'mobile' : 'desktop');
        return {os: os, browser: browser, browser_version: version, device_type: device};
    }

    function chooseCaptureMode(device) {
        var ua = String(navigator.userAgent || '');
        // Preferir pantalla solo cuando el navegador realmente expone la API.
        var hasScreenCapture = Boolean(window.isSecureContext && navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function');
        var isFirefox = /Firefox\//i.test(ua);
        var isEdge = /Edg\//i.test(ua);
        // La evaluación clasifica Chromium derivado (incluido Opera) con token Chrome.
        var isChrome = /Chrome\//i.test(ua) && !isEdge;
        var isSafari = /Safari\//i.test(ua) && !isChrome && !isEdge && !isFirefox;
        var supportedScreenBrowser = !isFirefox && (isChrome || isEdge || isSafari);
        return hasScreenCapture && supportedScreenBrowser ? 'screen' : 'canvas';
    }

    function updateCaptureGuidance() {
        if (!captureGuidance || !detectedDevice) return;
        var browser = detectedDevice.browser + (detectedDevice.browser_version ? ' ' + detectedDevice.browser_version : '');
        var device = detectedDevice.device_type + ' · ' + detectedDevice.os;
        if (captureMode === 'screen') {
            captureGuidance.textContent = 'Este navegador ofrece compartir pantalla y e-Talent lo usará; debes autorizarlo. La disponibilidad depende del navegador, dispositivo y sistema operativo. Detectamos ' + browser + ' en ' + device + '. En el selector, elige “Toda la pantalla”.';
        } else {
            var hasScreenCapture = Boolean(navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function');
            var reason = !window.isSecureContext
                ? 'Esta conexión no permite compartir pantalla; se usará Canvas.'
                : detectedDevice.device_type !== 'desktop' && !hasScreenCapture
                    ? 'Este navegador móvil o tableta no ofrece compartir pantalla a e-Talent; se usará Canvas.'
                    : detectedDevice.browser === 'Firefox'
                    ? 'En e-Talent, Firefox usa la alternativa Canvas.'
                    : 'Este navegador o contexto no tiene habilitado el modo de pantalla completa en e-Talent.';
            captureGuidance.textContent = reason + ' Se comprobará Canvas para capturar solo la interfaz de la evaluación; no incluye otras aplicaciones ni el escritorio. Para usar pantalla completa, prueba Chrome o un navegador basado en Chromium, Edge o Safari de escritorio en un contexto seguro y con la API de pantalla disponible.';
        }
        if (summary) summary.textContent = device + ' · ' + browser;
    }

    function checkCameraAndMicrophone() {
        var devices = navigator.mediaDevices;
        if (!window.isSecureContext || !devices || typeof devices.getUserMedia !== 'function') {
            setStatus('camera', 'not_supported', 'Este navegador o conexión no permite acceder a la cámara.');
            setStatus('microphone', 'not_supported', 'Este navegador o conexión no permite acceder al micrófono.');
            return Promise.resolve(null);
        }
        setStatus('camera', 'warning', 'Solicitando permiso…');
        setStatus('microphone', 'warning', 'Solicitando permiso…');
        var cameraPermissionDenied = false;
        var cameraCheck = devices.getUserMedia({video: {facingMode: {ideal: 'user'}, width: {ideal: 640}, height: {ideal: 360}}})
            .then(function (stream) {
                var track = stream.getVideoTracks().find(function (item) { return item.readyState === 'live'; });
                stream.getTracks().filter(function (item) { return item !== track; }).forEach(function (item) { item.stop(); });
                return track || null;
            }).catch(function (error) {
                var permissionDenied = error && error.name === 'NotAllowedError';
                cameraPermissionDenied = permissionDenied;
                setStatus('camera', permissionDenied ? 'warning' : 'failed', permissionDenied ? 'No se concedió el permiso de cámara.' : (error && error.name === 'NotFoundError' ? 'No se encontró una cámara.' : 'No fue posible activar la cámara.'));
                return null;
            });
        return cameraCheck.then(function (cameraTrack) {
            return devices.getUserMedia({audio: {channelCount: {ideal: 1}, echoCancellation: true, noiseSuppression: true}})
                .then(function (source) {
                    var track = source.getAudioTracks().find(function (item) { return item.readyState === 'live'; });
                    setStatus('microphone', track ? 'passed' : 'failed', track ? 'Permiso concedido y pista de audio activa; esta revisión no mide el volumen.' : 'El navegador no entregó audio de micrófono.');
                    source.getTracks().filter(function (item) { return item !== track; }).forEach(function (item) { item.stop(); });
                    return track || null;
                }).catch(function (error) {
                    var permissionDenied = error && error.name === 'NotAllowedError';
                    setStatus('microphone', permissionDenied ? 'warning' : 'failed', permissionDenied ? 'No se concedió el permiso de micrófono.' : (error && error.name === 'NotFoundError' ? 'No se encontró un micrófono.' : 'No fue posible activar el micrófono.'));
                    return null;
                }).then(function (microphoneTrack) {
            var stream = new MediaStream([cameraTrack, microphoneTrack].filter(Boolean));
            if (!cameraTrack) {
                preview.classList.add('d-none');
                if (!cameraPermissionDenied) setStatus('camera', 'failed', 'No se recibió una pista de video de la cámara.');
                return stream.getTracks().length ? stream : null;
            }
            preview.srcObject = new MediaStream([cameraTrack]);
            preview.classList.remove('d-none');
            return new Promise(function (resolve) {
                var settled = false;
                var finish = function (passed) {
                    if (settled) return;
                    settled = true;
                    setStatus('camera', passed ? 'passed' : 'failed', passed ? 'Permiso concedido y la cámara entregó un fotograma de video.' : 'La cámara se activó, pero no entregó un fotograma de video.');
                    resolve(stream.getTracks().length ? stream : null);
                };
                var verifyFrame = function () { if (preview.videoWidth > 0 && preview.videoHeight > 0 && preview.readyState >= 2) finish(true); };
                preview.addEventListener('loadeddata', verifyFrame, {once: true});
                preview.play().then(verifyFrame).catch(function () { finish(false); });
                window.setTimeout(function () { finish(false); }, 5000);
            });
                });
        });
    }

    function checkRecorder(stream) {
        if (!window.MediaRecorder || !stream || stream.getTracks().length === 0) {
            setStatus('media_recorder', window.MediaRecorder ? 'failed' : 'not_supported', window.MediaRecorder ? 'No hubo una pista disponible para probar la grabación.' : 'Este navegador no incluye MediaRecorder.');
            return Promise.resolve();
        }
        var hasVideo = stream.getVideoTracks().some(function (track) { return track.readyState === 'live'; });
        var hasAudio = stream.getAudioTracks().some(function (track) { return track.readyState === 'live'; });
        return new Promise(function (resolve) {
            var receivedData = false;
            var recorder;
            try { recorder = new MediaRecorder(stream); }
            catch (error) { setStatus('media_recorder', 'failed', 'El navegador no pudo iniciar MediaRecorder.'); resolve(); return; }
            recorder.ondataavailable = function (event) { receivedData = receivedData || Boolean(event.data && event.data.size); };
            recorder.onerror = function () { setStatus('media_recorder', 'failed', 'Falló la grabación local de prueba. No se guardó ningún video.'); resolve(); };
            recorder.onstop = function () {
                var avTracksLive = hasVideo && hasAudio
                    && stream.getVideoTracks().some(function (track) { return track.readyState === 'live'; })
                    && stream.getAudioTracks().some(function (track) { return track.readyState === 'live'; });
                var state = !receivedData ? 'failed' : (avTracksLive ? 'passed' : 'warning');
                var message = !receivedData ? 'La grabación no generó datos.' : (avTracksLive
                    ? 'MediaRecorder operó con cámara y micrófono; los datos temporales se descartaron.'
                    : 'Se probó solo la pista disponible; se requiere cámara y micrófono para comprobar el registro audiovisual completo.');
                setStatus('media_recorder', state, message);
                resolve();
            };
            try { recorder.start(); window.setTimeout(function () { if (recorder.state !== 'inactive') recorder.stop(); }, 700); }
            catch (error) { setStatus('media_recorder', 'failed', 'Falló la grabación local de prueba.'); resolve(); }
        });
    }

    function checkScreenCapture() {
        if (captureMode === 'canvas') return checkCanvasCapture();
        var devices = navigator.mediaDevices;
        if (!window.isSecureContext || !devices || typeof devices.getDisplayMedia !== 'function') {
            captureSource = 'unavailable';
            setStatus('screen_capture', 'failed', 'El modo de pantalla completa dejó de estar disponible. Reintenta desde Chrome o Edge de escritorio, o Safari de escritorio en macOS.');
            return Promise.resolve();
        }
        captureSource = 'screen';
        setStatus('screen_capture', 'warning', 'Elige la pantalla completa en el diálogo. Solo se comprobará localmente y no se guardará su contenido.');
        return devices.getDisplayMedia({
            video: {displaySurface: 'monitor'},
            audio: false,
            monitorTypeSurfaces: 'include',
            selfBrowserSurface: 'exclude',
            preferCurrentTab: false
        }).then(function (stream) {
            return new Promise(function (resolve) {
                var video = document.createElement('video'); video.muted = true; video.playsInline = true; video.srcObject = stream;
                var settled = false;
                var playbackStarted = false;
                var frameAttempts = 0;
                var videoTrack = stream.getVideoTracks().find(function (track) { return track.readyState === 'live'; });
                var displaySurface = '';
                try {
                    displaySurface = videoTrack && typeof videoTrack.getSettings === 'function'
                        ? String(videoTrack.getSettings().displaySurface || '')
                        : '';
                } catch (error) {
                    displaySurface = '';
                }
                var finish = function (state, message) {
                    if (settled) return;
                    settled = true;
                    stream.getTracks().forEach(function (track) { track.stop(); }); video.srcObject = null;
                    setStatus('screen_capture', state, message); resolve();
                };
                var captureFrameWhenReady = function () {
                    if (settled) return;
                    if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
                        captureSource = 'screen';
                        finish(displaySurface === 'monitor' ? 'passed' : 'warning', displaySurface === 'monitor'
                            ? 'Se confirmó que compartiste la pantalla completa. No se guardó contenido.'
                            : 'El navegador compartió la pantalla, pero no confirmó que fuera la pantalla completa.');
                        return;
                    }
                    frameAttempts += 1;
                    if (frameAttempts >= 100) {
                        finish('failed', 'Se seleccionó la pantalla, pero el navegador no entregó un fotograma dentro del tiempo esperado.');
                        return;
                    }
                    window.setTimeout(captureFrameWhenReady, 100);
                };
                var beginPlayback = function () {
                    if (playbackStarted || settled) return;
                    playbackStarted = true;
                    video.play().then(captureFrameWhenReady).catch(function () { finish('failed', 'Se seleccionó la pantalla, pero el navegador no pudo reproducir el flujo de video.'); });
                };
                video.onloadedmetadata = beginPlayback;
                video.onloadeddata = captureFrameWhenReady;
                video.oncanplay = captureFrameWhenReady;
                if (!videoTrack) {
                    finish('failed', 'El navegador no entregó una pista de pantalla.');
                    return;
                }
                if (displaySurface && displaySurface !== 'monitor') {
                    finish('failed', 'Seleccionaste una ventana o pestaña. Para esta evaluación, selecciona la pantalla completa.');
                    return;
                }
                beginPlayback();
                video.onerror = function () { finish('failed', 'Se seleccionó la pantalla, pero no se recibió video de ella.'); };
                window.setTimeout(function () { finish('failed', 'Se seleccionó la pantalla, pero no se recibió un fotograma a tiempo.'); }, 12000);
            });
        }).catch(function (error) {
            var dismissed = error && (error.name === 'NotAllowedError' || error.name === 'AbortError');
            var message = dismissed ? 'No se concedió el permiso o se canceló la selección de pantalla.' : 'No fue posible activar la captura de pantalla.';
            setStatus('screen_capture', dismissed ? 'warning' : 'failed', message);
        });
    }

    function loadHtml2Canvas() {
        if (typeof window.html2canvas === 'function') return Promise.resolve(window.html2canvas);
        var existing = document.querySelector('script[data-e_talent-html2canvas]');
        if (existing) {
            return new Promise(function (resolve, reject) {
                if (typeof window.html2canvas === 'function') { resolve(window.html2canvas); return; }
                existing.addEventListener('load', function () { typeof window.html2canvas === 'function' ? resolve(window.html2canvas) : reject(new Error('html2canvas_unavailable')); }, {once: true});
                existing.addEventListener('error', function () { reject(new Error('html2canvas_load_failed')); }, {once: true});
            });
        }
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
            script.async = true;
            script.dataset.e_talentHtml2canvas = '1';
            script.onload = function () { typeof window.html2canvas === 'function' ? resolve(window.html2canvas) : reject(new Error('html2canvas_unavailable')); };
            script.onerror = function () { reject(new Error('html2canvas_load_failed')); };
            document.head.appendChild(script);
        });
    }

    function checkCanvasCapture() {
        captureSource = 'canvas';
        setStatus('screen_capture', 'warning', 'Comprobando Canvas localmente. No se guardará ni enviará ninguna imagen.');
        return loadHtml2Canvas().then(function (html2canvas) {
            var target = page.querySelector('[data-component-canvas-probe]');
            if (!target) throw new Error('html2canvas_target_unavailable');
            return html2canvas(target, {useCORS: false, allowTaint: false, backgroundColor: '#ffffff', scale: 1, logging: false});
        }).then(function (canvas) {
            var works = Boolean(canvas && canvas.width > 0 && canvas.height > 0);
            if (canvas) { canvas.width = 0; canvas.height = 0; }
            if (works) {
                setStatus('screen_capture', 'passed', 'Canvas pudo renderizar una muestra local de la interfaz. La imagen temporal se descartó y no se envió.');
            } else {
                setStatus('screen_capture', 'failed', 'Canvas no pudo generar una imagen de prueba. Revisa la compatibilidad del navegador.');
            }
        }).catch(function (error) {
            var message = error && error.message === 'html2canvas_load_failed'
                ? 'No se pudo cargar html2canvas. Revisa la conexión e inténtalo nuevamente.'
                : 'html2canvas no pudo renderizar la muestra de prueba en este navegador.';
            setStatus('screen_capture', 'failed', message);
        });
    }

    detectedDevice = detectDevice();
    captureMode = chooseCaptureMode(detectedDevice);
    updateCaptureGuidance();

    button.addEventListener('click', function () {
        button.disabled = true;
        button.textContent = 'Comprobando…';
        Object.keys(labels).forEach(function (key) { setStatus(key, 'warning', 'Comprobando componente…'); });
        var device = detectedDevice;
        updateCaptureGuidance();

        checkScreenCapture().then(checkCameraAndMicrophone).then(function (stream) {
            return checkRecorder(stream).then(function () {
                if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
                preview.srcObject = null;
                preview.classList.add('d-none');
                var validation = Object.assign({
                    components: statuses,
                    component_messages: componentMessages,
                    capture_source: captureSource,
                    validator_version: '1'
                }, device);
                payloadInput.value = JSON.stringify(validation);
                form.classList.remove('d-none');
                form.submit();
            });
        }).catch(function () {
            button.disabled = false;
            button.textContent = 'Reintentar revisión';
        });
    });
}());
