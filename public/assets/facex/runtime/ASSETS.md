# Runtime local de reconocimiento facial

Runtime dependencies are served from this directory so camera and liveness
checks do not depend on a third-party CDN being reachable from the user's
browser.

- Human.js: `@vladmandic/human` 3.3.6 (`human.js`, BlazeFace/FaceMesh for
  detection and alignment, and FaceRes for the 1024-dimensional identity
  descriptor; MIT, see `LICENSE-HUMAN-MIT` and `LICENSE-HUMAN-MODELS-MIT`).
- Liveness engine: `@sssxyd/face-liveness-detector` 0.4.3
  (`face-liveness-detector.js`; MIT, see `LICENSE-LIVENESS-MIT`).
- OpenCV.js: `@techstark/opencv-js` 4.12.0-release.1 (`opencv.js`; see
  `LICENSE-OPENCV`).
- TensorFlow.js WASM backend: `@tensorflow/tfjs-backend-wasm` 4.22.0
  (WASM backend binaries; Apache-2.0, see `LICENSE-TFJS`).

Enrolamiento y verificación utilizan el mismo proveedor/modelo/versionado:
Human.js 3.3.6 + FaceRes (`human-3.3.6-faceres-1024-v1`). Los antiguos pesos y
runtime FaceX (`../det_500m_int8.bin`, `../edgeface_xs_fp32.bin`, `detect.js`,
`facex.js` y WASM asociado) se conservan como archivos heredados, pero no son
cargados por la interfaz activa. No se deben mezclar sus descriptores con los
FaceRes existentes; los enrolamientos antiguos deben volver a registrarse con
consentimiento explícito.
