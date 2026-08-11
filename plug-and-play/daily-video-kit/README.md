# Daily Video Kit

Kit portable para integrar Daily en proyectos PHP con tres bloques reutilizables:

- videollamada embebida;
- transcripcion Daily;
- configuracion Daily.

Incluye:

- creacion/sincronizacion de room Daily;
- meeting token por usuario;
- iframe embebido con `daily-js`;
- controles de salida y reingreso;
- transcripcion opcional con inicio manual o automatico;
- snapshots incrementales de transcripcion cada N segundos;
- guardado best-effort al salir/cerrar mediante `sendBeacon`;
- confirmacion SweetAlert opcional al abandonar la sala con transcripcion activa;
- formulario/configuracion reusable para dominio, API Key, transcripcion y bolsa de minutos;
- helpers para calcular uso Daily y proyectar minutos requeridos por sub-eventos futuros.

## Estructura

```text
daily-video-kit/
├── backend/
│   ├── DailyMeetingService.php
│   ├── DailySettings.php
│   └── daily_config.example.php
├── frontend/
│   ├── daily-meeting.css
│   └── daily-meeting.js
├── views/
│   ├── daily-meeting.partial.php
│   └── daily-settings.partial.php
└── examples/
    └── meeting-page.example.php
```

## Instalacion rapida

1. Copia la carpeta `daily-video-kit` al nuevo proyecto.
2. Copia `backend/daily_config.example.php` como `daily_config.php`.
3. Configura:

```php
return [
    'enabled' => true,
    'domain' => 'tu-dominio.daily.co',
    'api_key' => 'DAILY_API_KEY',
    'transcription_enabled' => true,
    'transcription_snapshot_interval_seconds' => 20,
    'minutes_pool_total' => 10000,
    'minutes_pool_warning_percent' => 80,
];
```

4. Incluye CSS y JS:

```html
<link rel="stylesheet" href="/daily-video-kit/frontend/daily-meeting.css">
<script src="/daily-video-kit/frontend/daily-meeting.js" defer></script>
```

5. En tu controlador o pagina:

```php
require_once __DIR__ . '/daily-video-kit/backend/DailyMeetingService.php';

require_once __DIR__ . '/daily-video-kit/backend/DailySettings.php';

$config = DailySettings::normalize(require __DIR__ . '/daily-video-kit/backend/daily_config.php');
$daily = new DailyMeetingService($config);

$meeting = $daily->meetingPayload([
    'room_slug' => 'curso-123-subevento-456',
    'user_name' => 'Nombre Usuario',
    'user_id' => 'user-123',
    'is_owner' => true,
    'transcription_enabled' => true,
    'transcription_auto_start' => true,
    'transcription_url' => '/daily/transcription',
    'transcription_snapshot_interval_seconds' => 20,
    'csrf_token' => csrf_token(),
]);

require __DIR__ . '/daily-video-kit/views/daily-meeting.partial.php';
```

## Configuracion reusable

Para renderizar el formulario de configuracion Daily:

```php
require_once __DIR__ . '/daily-video-kit/backend/DailySettings.php';

$dailySettings = DailySettings::normalize($settingsFromDatabase ?? []);
require __DIR__ . '/daily-video-kit/views/daily-settings.partial.php';
```

Para procesar el POST:

```php
$dailySettings = DailySettings::fromPost($_POST, $settingsFromDatabase ?? []);
$errors = DailySettings::validationErrors($dailySettings);

if ($errors === []) {
    // Guardar $dailySettings en la BD/configuracion del proyecto destino.
}
```

Campos incluidos:

- `daily_enabled`
- `daily_domain`
- `daily_api_key`
- `daily_webhook_secret`
- `daily_token_ttl_seconds`
- `daily_default_lang`
- `daily_transcription_enabled`
- `daily_transcription_auto_start_default`
- `daily_transcription_snapshot_interval_seconds`
- `daily_minutes_pool_enabled`
- `daily_minutes_pool_total`
- `daily_minutes_pool_warning_percent`

## Payload principal

`meetingPayload()` retorna:

```php
[
    'ok' => true,
    'url' => 'https://dominio.daily.co/sala?t=token',
    'room_name' => 'sala',
    'token' => 'token',
    'user_name' => 'Nombre Usuario',
    'api_url' => 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js/dist/daily-iframe.js',
    'transcription_enabled' => true,
    'transcription_auto_start' => true,
    'transcription_url' => '/daily/transcription',
    'transcription_snapshot_interval_seconds' => 20,
]
```

Si `ok` es `false`, el partial muestra `error`.

## Contrato de transcripcion

El proyecto destino debe implementar el endpoint indicado en `transcription_url`.

Debe aceptar `POST` con:

```text
csrf_token
transcription_action = start | stop | snapshot | failed
transcript_text     = texto acumulado, solo en snapshot
transcription_status = recording | processing | available
message             = detalle de error, solo en failed
```

Reglas recomendadas:

- `start`: marcar sesion como `recording`.
- `snapshot`: guardar `transcript_text` acumulado y respetar `transcription_status`.
- `stop`: marcar como `processing` si no hay texto final.
- `failed`: guardar `message` y marcar estado de error.
- Permitir `sendBeacon`, porque el navegador puede cerrar la pestaña antes de completar un `fetch`.

Daily puede devolver `undefined` en `startTranscription()` y `stopTranscription()`. El JS del kit no asume promesas y por eso evita el error `can't access property "catch"`.

## Bolsa de minutos

Daily entrega reuniones y participantes desde `/meetings`; cada participante trae `duration`. El helper:

```php
$usage = $daily->meetingUsageSummary($startTimestamp, $endTimestamp);
```

retorna:

```php
[
    'participant_minutes' => 1234,
    'meeting_minutes' => 500,
    'meetings' => 10,
    'participants' => 80,
]
```

Para proyectar sub-eventos futuros:

```php
$projection = DailyMeetingService::futureMinutesProjection([
    [
        'name' => 'Sub-evento 1',
        'duration_minutes' => 90,
        'participant_count' => 5,
    ],
    [
        'name' => 'Sub-evento sin inscritos',
        'duration_minutes' => 60,
        'participant_count' => 0,
    ],
]);
```

Regla aplicada:

- si `participant_count > 0`, suma `duration_minutes * participant_count`;
- si no tiene participantes, queda `quantifiable = false` y no suma minutos;
- esos casos deben mostrarse como warning porque aun no se pueden cuantificar.

## Auto-transcripcion

Para iniciar transcripcion automaticamente:

```php
'transcription_auto_start' => true
```

El usuario debe ser owner/moderador (`is_owner = true`). El servicio agrega `auto_start_transcription` al meeting token solo cuando corresponde.

## Abandono de sala

El JS hace dos cosas:

- si el usuario navega por enlaces internos mientras la transcripcion esta activa, usa SweetAlert si existe `window.Swal`;
- si se cierra o recarga la pestaña, guarda el ultimo snapshot por `sendBeacon`.

Los navegadores no permiten mostrar SweetAlert durante cierre real de pestaña; por eso ese caso queda como guardado silencioso.

## Seguridad

- La API Key de Daily debe vivir solo en backend.
- El token se genera en backend y se envia al navegador solo para entrar a la sala.
- Usa rooms privadas.
- Usa `is_owner = true` solo para moderadores.
- Limita `token_ttl_seconds` segun la duracion real del evento cuando sea posible.

## Adaptaciones habituales

- Reemplazar `room_slug` por ID o slug del evento/sub-evento.
- Resolver `user_name`, `user_id` e `is_owner` desde la sesion.
- Implementar endpoint de transcripcion en el framework destino.
- Guardar estados de transcripcion en tablas propias.
- Alimentar `minutes_pool` desde el modulo de planificacion del proyecto destino.
