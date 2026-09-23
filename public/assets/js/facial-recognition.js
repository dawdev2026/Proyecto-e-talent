(function () {
    'use strict';

    // Punto de calibración común para enrolamiento, validación y evaluaciones/tests.
    // El motor mide área de cara / área total del cuadro (no solo ancho). Un
    // 32% exige acercarse excesivamente a cámaras de notebook/escritorio.
    // 4% permite una distancia natural y la prueba de vida/descriptor siguen
    // descartando caras borrosas, tapadas o no frontales.
    var DEFAULT_MIN_FACE_RATIO = 0.04;

    function status(camera, message, type) {
        var node = camera.querySelector('[data-face-status]');
        if (!node) return;
        if (node.dataset.message === message && node.dataset.statusType === (type || '')) return;
        if (camera._faceQueuedStatus
            && camera._faceQueuedStatus.message === message
            && camera._faceQueuedStatus.type === (type || '')) return;
        var now = Date.now();
        var immediate = type === 'error';
        if (immediate || now >= (camera._faceStatusHoldUntil || 0)) {
            if (camera._faceStatusTimer) window.clearTimeout(camera._faceStatusTimer);
            camera._faceStatusTimer = null;
            if (immediate) camera._faceQueuedStatus = null;
        } else {
            camera._faceQueuedStatus = { message: message, type: type || '' };
            if (!camera._faceStatusTimer) {
                camera._faceStatusTimer = window.setTimeout(function () {
                    camera._faceStatusTimer = null;
                    var queued = camera._faceQueuedStatus;
                    camera._faceQueuedStatus = null;
                    camera._faceStatusHoldUntil = 0;
                    if (queued) status(camera, queued.message, queued.type);
                }, Math.max(0, camera._faceStatusHoldUntil - now));
            }
            return;
        }
        node.dataset.message = message;
        node.dataset.statusType = type || '';
        var icons = { instruction: 'bi-person-walking', success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill' };
        node.className = 'face-camera-status' + (type ? ' is-' + type : '');
        node.textContent = '';
        if (type && icons[type]) {
            var icon = document.createElement('i');
            icon.className = 'bi ' + icons[type];
            icon.setAttribute('aria-hidden', 'true');
            node.appendChild(icon);
        }
        var text = document.createElement('span');
        text.textContent = message;
        node.appendChild(text);
        camera._faceStatusHoldUntil = now + (type === 'instruction' ? 2400 : (type === 'success' ? 2600 : 0));
        if (camera._faceStatusHoldUntil > now) {
            camera._faceStatusTimer = window.setTimeout(function () {
                camera._faceStatusTimer = null;
                var queued = camera._faceQueuedStatus;
                camera._faceQueuedStatus = null;
                camera._faceStatusHoldUntil = 0;
                if (queued) status(camera, queued.message, queued.type);
            }, camera._faceStatusHoldUntil - now);
        }
    }

    function guide(camera, state) {
        var node = camera.querySelector('[data-face-guide]');
        if (!node) return;
        var immediate = state === 'waiting' || state === 'blocked';
        if (node.dataset.state === state) return;
        if (camera._faceGuideTimer) window.clearTimeout(camera._faceGuideTimer);
        camera._faceGuidePending = state;
        var apply = function () {
            camera._faceGuideTimer = null;
            var next = camera._faceGuidePending;
            camera._faceGuidePending = null;
            if (!next || node.dataset.state === next) return;
            node.className = 'face-camera-guide is-' + next;
            node.dataset.state = next;
        };
        if (immediate) apply();
        else camera._faceGuideTimer = window.setTimeout(apply, 500);
    }

    function diagnostic(name, data) {
        var detail = Object.assign({ name: name, timestamp: Date.now() }, data || {});
        window.dispatchEvent(new CustomEvent('facial-recognition:diagnostic', { detail: detail }));
        if (new URLSearchParams(window.location.search).get('face_debug') === '1') {
            console.debug('[facial-recognition]', detail);
        }
    }

    function cameraErrorMessage(error) {
        var messages = {
            NotAllowedError: 'El navegador bloqueó la cámara. Revisa el permiso del sitio y vuelve a intentarlo.',
            PermissionDeniedError: 'El navegador bloqueó la cámara. Revisa el permiso del sitio y vuelve a intentarlo.',
            NotFoundError: 'No se encontró una cámara disponible en este dispositivo.',
            NotReadableError: 'La cámara está siendo usada por otra aplicación o no pudo iniciarse. Ciérrala y vuelve a intentarlo.',
            OverconstrainedError: 'La cámara no admite la configuración solicitada. Prueba con otra cámara o dispositivo.',
            AbortError: 'La cámara se interrumpió antes de iniciar. Vuelve a intentarlo.',
            SecurityError: 'El navegador no permite usar la cámara en esta página.',
            TimeoutError: 'La cámara tardó demasiado en iniciar. Comprueba que esté conectada e inténtalo otra vez.',
            CAMERA_ACCESS_DENIED: 'El navegador bloqueó la cámara. Revisa el permiso del sitio y vuelve a intentarlo.',
            STREAM_ACQUISITION_FAILED: 'No se pudo iniciar el flujo de cámara. Cierra otras aplicaciones que la estén usando y reintenta.',
            DETECTOR_NOT_INITIALIZED: 'El detector facial no alcanzó a prepararse. Vuelve a intentarlo.',
            INTERNAL_ERROR: 'Ocurrió un error durante la prueba facial. Vuelve a intentarlo.'
        };
        return messages[error && (error.name || error.code)] || (error && error.message) || 'No se pudo iniciar la cámara. Revisa el permiso e inténtalo nuevamente.';
    }

    function setFile(input, blob) {
        if (!window.DataTransfer) return false;
        var transfer = new DataTransfer();
        transfer.items.add(new File([blob], 'captura-facial.jpg', { type: 'image/jpeg' }));
        input.files = transfer.files;
        return input.files.length === 1;
    }

    function stop(camera) {
        if (camera._facePump) {
            window.clearInterval(camera._facePump);
            camera._facePump = null;
        }
        if (camera._faceGuideTimer) window.clearTimeout(camera._faceGuideTimer);
        camera._faceGuideTimer = null;
        camera._faceGuidePending = null;
        if (camera._faceStatusTimer) window.clearTimeout(camera._faceStatusTimer);
        camera._faceStatusTimer = null;
        camera._faceQueuedStatus = null;
        camera._faceStatusHoldUntil = 0;
        var engine = camera._faceEngine;
        camera._faceEngine = null;
        if (engine && typeof engine.stopDetection === 'function') { try { engine.stopDetection(false); } catch (e) {} }
        var video = camera.querySelector('[data-face-video]');
        var stream = (video && video.srcObject) || camera._faceStream;
        if (stream && stream.getTracks) stream.getTracks().forEach(function (track) { track.stop(); });
        if (video) video.srcObject = null;
        camera._faceStream = null;
    }

    function dataUrlBlob(value) {
        var parts = String(value || '').split(',');
        if (parts.length !== 2) return null;
        var mime = (parts[0].match(/data:([^;]+)/) || [])[1] || 'image/jpeg';
        var binary = atob(parts[1]); var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
        return new Blob([bytes], { type: mime });
    }

    function challengeUrl() { return window.FaceChallengeUrl || (location.origin + '/reconocimiento-facial/desafio'); }

    async function createChallenge(form, purpose) {
        var user = form.querySelector('[name="user_id"]');
        var csrf = form.querySelector('input[name="csrf_token"]');
        if (!user || !user.value) throw new Error('Selecciona un usuario antes de activar la cámara.');
        var body = new URLSearchParams({ csrf_token: csrf ? csrf.value : '', user_id: user.value, purpose: purpose });
        var response = await fetch(challengeUrl(), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: body });
        var data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'No fue posible iniciar el desafío facial.');
        form.querySelector('[name="face_challenge"]').value = data.challenge;
        form.querySelector('[name="face_start_key"]').value = data.start_key;
        return data;
    }

    window.FaceRecognitionChallenges = window.FaceRecognitionChallenges || {};
    window.FaceRecognitionChallenges.refresh = function (form) {
        return createChallenge(form, (form && form.dataset.facePurpose) || 'validate');
    };

    async function embeddingFromImage(camera, blob, engine) {
        var image = new Image();
        image.src = URL.createObjectURL(blob);
        await new Promise(function (resolve, reject) { image.onload = resolve; image.onerror = reject; });
        try {
            if (!image.naturalWidth || !image.naturalHeight) throw new Error('La captura facial está vacía. Activa la cámara y vuelve a intentarlo.');
            var human = (engine || camera._faceEngine) && (engine || camera._faceEngine).human;
            if (!human || typeof human.detect !== 'function') throw new Error('El reconocedor facial no terminó de cargar. Vuelve a activar la cámara.');
            // Reutiliza el motor cargado para la prueba de vida y extrae el
            // descriptor una sola vez, con el video ya detenido.
            if (human.config && human.config.face && human.config.face.description) {
                human.config.face.description.skipFrames = 0;
                human.config.face.description.skipTime = 0;
            }
            var processed = await human.detect(image);
            if (!processed || !Array.isArray(processed.face) || processed.face.length !== 1) {
                throw new Error(processed && processed.face && processed.face.length > 1
                    ? 'Se detectó más de un rostro. Deja solo una persona frente a la cámara y repite la captura.'
                    : 'No se pudo localizar un rostro en la captura. Activa la cámara, centra la cara y vuelve a intentarlo.');
            }
            var descriptor = processed.face[0].embedding;
            if (!Array.isArray(descriptor) || descriptor.length !== 1024) throw new Error('El modelo FaceRes no generó una huella facial válida. Recarga la página e inténtalo otra vez.');
            return descriptor.map(Number);
        } finally { URL.revokeObjectURL(image.src); }
    }

    function showPhoto(camera, url) {
        var photo = camera.querySelector('[data-face-photo]');
        var video = camera.querySelector('[data-face-video]');
        var placeholder = camera.querySelector('[data-face-placeholder]');
        if (photo) {
            if (camera._facePhotoUrl) URL.revokeObjectURL(camera._facePhotoUrl);
            camera._facePhotoUrl = url;
            photo.src = url;
            photo.classList.remove('d-none');
        }
        if (video) video.classList.add('d-none');
        if (placeholder) placeholder.classList.add('d-none');
        camera.querySelector('[data-face-retake]').classList.remove('d-none');
    }

    function init(camera) {
        var form = camera.closest('form');
        var video = camera.querySelector('[data-face-video]');
        var file = form && form.querySelector('input[name="face_image"]');
        var start = camera.querySelector('[data-face-start]');
        var retake = camera.querySelector('[data-face-retake]');
        if (!form || !video || !file || !start || !retake) return;
        var purpose = form.dataset.facePurpose || 'validate';
        // Cámaras integradas/exteriores suelen estar a mayor distancia que un
        // teléfono. Este umbral afecta solo al tamaño mínimo del rostro para
        // capturarlo; no modifica la coincidencia ni la prueba de vida.
        var configuredMinFaceRatio = Number(camera.dataset.minFaceRatio);
        var minFaceRatio = Number.isFinite(configuredMinFaceRatio)
            ? Math.min(0.8, Math.max(0.2, configuredMinFaceRatio))
            : DEFAULT_MIN_FACE_RATIO;
        var embedding = form.querySelector('[name="face_embedding"]');
        var liveness = form.querySelector('[name="face_liveness"]');
        var lastDetectorCode = '';
        var lastDiagnosticAt = 0;
        var guideNode = camera.querySelector('[data-face-guide]');
        if (!guideNode) {
            guideNode = document.createElement('div');
            guideNode.className = 'face-camera-guide is-waiting';
            guideNode.dataset.faceGuide = '';
            guideNode.setAttribute('aria-hidden', 'true');
            guideNode.innerHTML = '<span></span>';
            var preview = camera.querySelector('.face-camera-preview');
            if (preview) preview.appendChild(guideNode);
        }
        guide(camera, 'waiting');
        status(camera, 'Activa la cámara para iniciar la prueba.');

        function superviseDetection(engine) {
            if (camera._facePump) window.clearInterval(camera._facePump);
            // face-liveness-detector puede consumir el primer requestAnimationFrame
            // mientras el video aún no tiene un frame disponible y no reprogramarlo.
            // Este supervisor vuelve a invocar el ciclo sin duplicar trabajo: el
            // propio motor ignora llamadas concurrentes mediante su guard interno.
            camera._facePump = window.setInterval(function () {
                if (!camera._faceEngine || camera._faceEngine !== engine) return;
                var state = typeof engine.getEngineState === 'function' ? engine.getEngineState() : engine.engineState;
                if (state !== 'detecting' || !video.srcObject || video.readyState < 2) return;
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    engine.actualVideoWidth = video.videoWidth;
                    engine.actualVideoHeight = video.videoHeight;
                }
                if (typeof engine.detect === 'function') engine.detect();
            }, 500);
        }

        async function startDetectionSafely(engine) {
            var readinessPulse = window.setInterval(function () {
                // Algunos navegadores emiten canplay antes de que el listener
                // interno de face-liveness-detector quede registrado.
                if (video.readyState >= 2) video.dispatchEvent(new Event('canplay'));
            }, 150);
            var timeout = new Promise(function (_, reject) {
                window.setTimeout(function () { reject(new Error('La cámara no entregó un video reproducible a tiempo.')); }, 15000);
            });
            try {
                await Promise.race([engine.startDetection(video), timeout]);
            } finally {
                window.clearInterval(readinessPulse);
            }
        }

        start.addEventListener('click', async function () {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { status(camera, 'La cámara no está disponible.', 'error'); return; }
            start.disabled = true;
            var startedAt = Date.now();
            guide(camera, 'waiting');
            try {
                if (!window.FaceDetectionEngine || !window.Human || !window.cv) throw new Error('La prueba de vida y el reconocedor facial no están disponibles. Recarga la página.');
                var Engine = window.FaceDetectionEngine.default || window.FaceDetectionEngine.FaceDetectionEngine;
                var engine = camera._faceEngine = new Engine({
                    human_model_path: window.HumanModelBase,
                    tensorflow_wasm_path: window.HumanWasmBase,
                    // Evita crear huellas con rostros pequeños o demasiado girados,
                    // que suelen producir similitudes inestables en la comparación.
                    tensorflow_backend: 'webgl', collect_min_face_ratio: minFaceRatio, collect_min_face_frontal: 0.9,
                    photo_attack_passed_frame_count: 10, enable_face_moving_detection: true, enable_photo_attack_detection: true,
                    action_liveness_action_count: 1, action_liveness_verify_timeout: 15000
                });
                engine.on('detector-action', function (data) {
                    var actions = { blink: 'Parpadea una vez', mouth_open: 'Abre la boca', nod_down: 'Mira suavemente hacia abajo', nod_up: 'Mira suavemente hacia arriba' };
                    if (data.status === 'timeout') {
                        guide(camera, 'adjust');
                        status(camera, 'No alcanzamos a detectar el movimiento. Mantén el rostro centrado y vuelve a intentarlo.', 'error');
                    } else if (data.status === 'completed') {
                    status(camera, 'Correcto. Mantén el rostro centrado.', 'success');
                    } else {
                        guide(camera, 'active');
                        status(camera, actions[data.action] || 'Sigue la instrucción…', 'instruction');
                    }
                });
                engine.on('detector-info', function (data) {
                    var messages = {
                        VIDEO_NO_FACE: 'Ubica tu rostro frente a la cámara…',
                        MULTIPLE_FACE: 'Debe haber una sola persona.',
                        FACE_TOO_SMALL: 'El rostro se ve pequeño en el encuadre. Ajusta la posición o altura de la cámara y mantén una distancia cómoda; no hace falta acercarte demasiado.',
                        FACE_TOO_LARGE: 'Aléjate un poco de la cámara.',
                        FACE_NOT_FRONTAL: 'Mira de frente a la cámara.',
                        FACE_LOW_QUALITY: 'Busca mejor iluminación y mantén el rostro enfocado.',
                        FACE_NOT_MOVING: 'Mueve suavemente la cabeza para confirmar que estás presente.',
                        PHOTO_ATTACK_DETECTED: 'No se pudo confirmar que el rostro esté presente.',
                        FACE_IMAGE_CAPTURED: 'Encuadre correcto. Sigue la instrucción de la prueba de vida.'
                    };
                    var blocked = data.code === 'MULTIPLE_FACE' || data.code === 'PHOTO_ATTACK_DETECTED';
                    var ready = data.passed === true || data.code === 'FACE_IMAGE_CAPTURED';
                    guide(camera, blocked ? 'blocked' : (ready ? 'ready' : (data.code ? 'adjust' : 'active')));
                    var now = Date.now();
                    if (data.code !== lastDetectorCode || now - lastDiagnosticAt >= 5000) {
                        lastDetectorCode = data.code || '';
                        lastDiagnosticAt = now;
                        diagnostic('detector-info', {
                            code: String(data.code || 'unknown').slice(0, 48),
                            passed: Boolean(data.passed),
                            minFaceRatio: minFaceRatio,
                            faceCount: Number.isFinite(Number(data.faceCount)) ? Number(data.faceCount) : null,
                            faceRatio: Number.isFinite(Number(data.faceRatio)) ? Number(data.faceRatio) : null,
                            faceFrontal: Number.isFinite(Number(data.faceFrontal)) ? Number(data.faceFrontal) : null,
                            imageQuality: Number.isFinite(Number(data.imageQuality)) ? Number(data.imageQuality) : null
                        });
                    }
                    if (messages[data.code]) status(camera, messages[data.code], blocked ? 'error' : (ready ? 'success' : 'instruction'));
                });
                engine.on('detector-error', function (error) {
                    stop(camera); start.disabled = false; guide(camera, 'blocked');
                    var code = String(error && error.code || 'INTERNAL_ERROR').slice(0, 48);
                    diagnostic('detector-error', { code: code, elapsedMs: Date.now() - startedAt });
                    status(camera, cameraErrorMessage(error), 'error');
                });
                engine.on('detector-finish', async function (data) {
                    // Mantener una referencia al motor: stop(camera) limpia
                    // camera._faceEngine y detiene el stream, pero necesitamos
                    // el Human ya inicializado para extraer el descriptor final.
                    var completedEngine = camera._faceEngine;
                    stop(camera);
                    if (!data.success) { start.disabled = false; guide(camera, 'adjust'); diagnostic('detector-finish', { passed: false, elapsedMs: Date.now() - startedAt }); status(camera, 'Prueba de vida no aprobada. Centra el rostro y vuelve a intentarlo.', 'error'); return; }
                    try {
                        // La evidencia debe conservar la resolución completa de la
                        // cámara (mínimo 640×480). El recorte facial se usa solo
                        // para calcular la huella y puede ser más pequeño.
                        var evidenceBlob = dataUrlBlob(data.bestFrameImage || data.bestFaceImage);
                        var faceBlob = dataUrlBlob(data.bestFaceImage || data.bestFrameImage);
                        if (!evidenceBlob || !setFile(file, evidenceBlob)) throw new Error('No fue posible preparar la fotografía.');
                        // Human.js localiza/alinea la cara y calcula el descriptor
                        // FaceRes con el mismo pipeline de enrolamiento/validación.
                        var vector = await embeddingFromImage(camera, evidenceBlob || faceBlob, completedEngine);
                        if (vector.length !== 1024 || vector.some(function (value) { return !Number.isFinite(value); })) throw new Error('La huella facial quedó incompleta. Repite la captura con el rostro centrado.');
                        embedding.value = JSON.stringify(vector); liveness.value = '1';
                        showPhoto(camera, URL.createObjectURL(evidenceBlob));
                        guide(camera, 'ready');
                        diagnostic('capture-ready', {
                            elapsedMs: Date.now() - startedAt,
                            quality: Number.isFinite(Number(data.bestQualityScore)) ? Number(data.bestQualityScore) : null,
                            width: video.videoWidth || null,
                            height: video.videoHeight || null
                        });
                        status(camera, 'Prueba de vida aprobada. ' + (window.AppProjectName || 'La plataforma') + ' preparó la huella facial.', 'success');
                    } catch (error) { start.disabled = false; guide(camera, 'blocked'); diagnostic('capture-error', { code: String(error && error.name || 'capture_error').slice(0, 48), elapsedMs: Date.now() - startedAt }); status(camera, error.message, 'error'); }
                });
                status(camera, 'Preparando cámara y modelos faciales…', 'instruction');
                await engine.initialize();
                video.classList.remove('d-none'); camera.querySelector('[data-face-placeholder]').classList.add('d-none');
                // Emitir el token después de cargar motores/modelos: el tiempo de
                // descarga inicial ya no consume la vigencia del desafío.
                var stream = engine.stream;
                var videoTrack = stream && stream.getVideoTracks ? stream.getVideoTracks()[0] : null;
                var cameraSettings = videoTrack && videoTrack.getSettings ? videoTrack.getSettings() : {};
                diagnostic('camera-ready', {
                    width: Number(cameraSettings.width) || null,
                    height: Number(cameraSettings.height) || null,
                    frameRate: Number(cameraSettings.frameRate) || null
                });
                await createChallenge(form, purpose);
                status(camera, 'Preparando cámara para la prueba de vida…', 'instruction');
                await startDetectionSafely(engine); camera._faceStream = engine.stream;
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    engine.actualVideoWidth = video.videoWidth;
                    engine.actualVideoHeight = video.videoHeight;
                }
                superviseDetection(engine);
            } catch (error) {
                stop(camera); start.disabled = false; guide(camera, 'blocked');
                diagnostic('start-error', { code: String(error && error.name || 'initialization_error').slice(0, 48), elapsedMs: Date.now() - startedAt });
                status(camera, cameraErrorMessage(error), 'error');
            }
        });

        retake.addEventListener('click', function () {
            stop(camera);
            if (camera._facePhotoUrl) { URL.revokeObjectURL(camera._facePhotoUrl); camera._facePhotoUrl = null; }
            var photo = camera.querySelector('[data-face-photo]');
            if (photo) { photo.removeAttribute('src'); photo.classList.add('d-none'); }
            var placeholder = camera.querySelector('[data-face-placeholder]');
            if (placeholder) placeholder.classList.remove('d-none');
            if (video) video.classList.add('d-none');
            file.value = ''; embedding.value = ''; liveness.value = '0';
            form.querySelector('[name="face_challenge"]').value = '';
            form.querySelector('[name="face_start_key"]').value = '';
            start.disabled = false; start.classList.remove('d-none'); retake.classList.add('d-none');
            guide(camera, 'waiting');
            status(camera, 'Activa la cámara. Se iniciará una verificación nueva.');
        });
        var userSelector = form.querySelector('select[name="user_id"]');
        if (userSelector) userSelector.addEventListener('change', function () { form.querySelector('[name="face_challenge"]').value = ''; form.querySelector('[name="face_start_key"]').value = ''; embedding.value = ''; liveness.value = '0'; });
        form.addEventListener('submit', function (event) {
            var threshold = parseFloat(form.dataset.faceLivenessThreshold || '0.60');
            if (!Number.isFinite(threshold)) threshold = 0.60;
            if (!embedding.value || !file.files || file.files.length !== 1 || parseFloat(liveness.value || '0') < threshold) {
                event.preventDefault();
                status(camera, 'Completa la prueba de vida con la cámara antes de enviar.', 'error');
                return;
            }
            if (form._faceChallengeReadyToSubmit) {
                form._faceChallengeReadyToSubmit = false;
                return;
            }
            event.preventDefault();
            if (form._faceChallengeRefreshPending) return;
            form._faceChallengeRefreshPending = true;
            var submitter = event.submitter || null;
            var submitterWasDisabled = submitter && submitter.disabled;
            if (submitter) submitter.disabled = true;
            window.FaceRecognitionChallenges.refresh(form).then(function () {
                form._faceChallengeRefreshPending = false;
                form._faceChallengeReadyToSubmit = true;
                if (submitter) submitter.disabled = submitterWasDisabled;
                if (typeof form.requestSubmit === 'function') form.requestSubmit(submitterWasDisabled ? undefined : submitter || undefined);
                else HTMLFormElement.prototype.submit.call(form);
            }).catch(function (error) {
                form._faceChallengeRefreshPending = false;
                if (submitter) submitter.disabled = submitterWasDisabled;
                diagnostic('challenge-refresh-error', { code: String(error && error.name || 'refresh_failed').slice(0, 48) });
                status(camera, error.message || 'No se pudo preparar una nueva verificación. Vuelve a intentarlo.', 'error');
            });
        });
        window.addEventListener('pagehide', function () { stop(camera); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery('.js-face-user-select').select2({
                width: '100%',
                placeholder: function () { return this.getAttribute('data-placeholder') || 'Selecciona un usuario'; },
                allowClear: true,
                minimumResultsForSearch: 0,
                language: { noResults: function () { return 'No se encontraron usuarios'; }, searching: function () { return 'Buscando…'; } }
            });
        }
        document.querySelectorAll('[data-face-camera]').forEach(init);
    });
}());
