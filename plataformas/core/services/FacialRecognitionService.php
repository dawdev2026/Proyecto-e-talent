<?php
declare(strict_types=1);

/** Human.js orchestration: browser inference, backend-owned challenge and decision. */
final class FacialRecognitionService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $integrations = load_config('integrations');
        $configured = is_array($integrations['facial_recognition'] ?? null) ? $integrations['facial_recognition'] : [];
        $this->config = array_merge([
            'enabled' => true,
            'provider' => 'human',
            // La versión identifica FaceRes 1024D de Human.js 3.3.6.
            // Nunca comparar con descriptores del proveedor FaceX anterior.
            'model_version' => 'human-3.3.6-faceres-1024-v1',
            'similarity_threshold' => 0.50,
            'liveness_threshold' => 0.60,
            'challenge_ttl_seconds' => 300,
        ], $configured, is_array($config) ? $config : []);
        // La versión identifica el pipeline de embeddings y no debe poder quedar
        // desfasada por la configuración local ignorada o por un valor antiguo.
        $this->config['model_version'] = 'human-3.3.6-faceres-1024-v1';
    }

    public function settings(bool $includeSecret = false): array
    {
        return [
            'enabled' => (bool) $this->config['enabled'],
            'provider' => 'human',
            'model_version' => (string) $this->config['model_version'],
            'similarity_threshold' => $this->similarityThreshold(),
            'liveness_threshold' => $this->livenessThreshold(),
            'challenge_ttl_seconds' => $this->challengeTtl(),
        ];
    }

    public function isConfigured(): bool { return (bool) $this->config['enabled']; }
    public function similarityThreshold(): float { return max(0.50, min(0.99, (float) $this->config['similarity_threshold'])); }
    public function livenessThreshold(): float { return max(0.50, min(0.99, (float) $this->config['liveness_threshold'])); }
    public function challengeTtl(): int { return max(60, min(900, (int) $this->config['challenge_ttl_seconds'])); }
    public function modelVersion(): string { return (string) $this->config['model_version']; }

    public function createChallenge(int $actorId, int $targetUserId, string $purpose): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $now = time();
        foreach ($_SESSION['facial_challenges'] ?? [] as $challengeId => $challengeRecord) {
            if (!is_array($challengeRecord)
                || !empty($challengeRecord['used'])
                || (int) ($challengeRecord['expires_at'] ?? 0) <= $now) {
                unset($_SESSION['facial_challenges'][$challengeId]);
            }
        }
        $payload = [
            'id' => bin2hex(random_bytes(16)),
            'actor_id' => $actorId,
            'target_user_id' => $targetUserId,
            'purpose' => $purpose,
            'issued_at' => $now,
            'expires_at' => $now + $this->challengeTtl(),
            'nonce' => bin2hex(random_bytes(32)),
        ];
        $token = encrypt_payload($payload, 'facial-challenge');
        $_SESSION['facial_challenges'][$payload['id']] = [
            'token_hash' => hash('sha256', $token),
            'used' => false,
            'expires_at' => $payload['expires_at'],
            'actor_id' => $actorId,
            'target_user_id' => $targetUserId,
            'purpose' => $purpose,
        ];
        return [
            'challenge_id' => $payload['id'],
            'challenge' => $token,
            'start_key' => hash_hmac('sha256', $token, master_key()),
            'expires_at' => $payload['expires_at'],
            'provider' => 'human',
            'model_version' => $this->modelVersion(),
        ];
    }

    public function consumeChallenge(string $token, string $signature, int $actorId, int $targetUserId, string $purpose): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if ($token === '' || !hash_equals(hash_hmac('sha256', $token, master_key()), $signature)) {
            throw new RuntimeException('La llave de inicio de la verificación no es válida.');
        }
        $payload = decrypt_payload($token, 'facial-challenge');
        $id = (string) ($payload['id'] ?? '');
        $record = $_SESSION['facial_challenges'][$id] ?? null;
        if (!is_array($record) || !hash_equals((string) $record['token_hash'], hash('sha256', $token))) {
            throw new RuntimeException('El desafío facial no existe o ya expiró.');
        }
        if (!empty($record['used']) || (int) ($record['expires_at'] ?? $payload['expires_at'] ?? 0) <= time()) {
            throw new RuntimeException('El desafío facial expiró o ya fue utilizado.');
        }
        if ((int) ($payload['actor_id'] ?? 0) !== $actorId || (int) ($payload['target_user_id'] ?? 0) !== $targetUserId || (string) ($payload['purpose'] ?? '') !== $purpose) {
            throw new RuntimeException('El desafío facial no corresponde a esta operación.');
        }
        $_SESSION['facial_challenges'][$id]['used'] = true;
        return $payload;
    }

    public function finishKey(array $challenge, bool $result, ?float $similarity, ?float $liveness): string
    {
        $canonical = implode('|', [
            (string) ($challenge['id'] ?? ''), $result ? 'verified' : 'not_verified',
            number_format((float) ($similarity ?? 0), 5, '.', ''),
            number_format((float) ($liveness ?? 0), 5, '.', ''),
        ]);
        return hash_hmac('sha256', $canonical, master_key());
    }

    public function normalizeEmbedding(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || count($decoded) !== 1024) throw new RuntimeException('Human.js no devolvió una huella facial válida.');
        $embedding = [];
        foreach ($decoded as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) throw new RuntimeException('La huella facial contiene valores inválidos.');
            $embedding[] = (float) $value;
        }
        $norm = 0.0;
        foreach ($embedding as $value) $norm += $value * $value;
        if (!is_finite($norm) || $norm < 1.0e-12) throw new RuntimeException('Human.js devolvió una huella vacía. Repite la captura con buena iluminación y el rostro centrado.');
        return $embedding;
    }

    public function assertEnrollmentCompatible(array $enrollment): void
    {
        $storedVersion = trim((string) ($enrollment['model_version'] ?? ''));
        if ($storedVersion !== $this->modelVersion()) {
            throw new RuntimeException('La referencia facial fue creada con un método anterior y debe realizarse un nuevo enrolamiento antes de validar.');
        }
    }

    /** Reproduce Human.js 3.3.6 match.similarity(order=2,multiplier=25,min=.2,max=.8). */
    public function descriptorSimilarity(array $left, array $right): float
    {
        if (count($left) !== 1024 || count($right) !== 1024) return 0.0;
        $sum = 0.0;
        for ($i = 0; $i < 1024; $i++) {
            $difference = (float) $left[$i] - (float) $right[$i];
            $sum += $difference * $difference;
        }
        $distance = round(25 * $sum, 2);
        if ($distance <= 0.0) return 1.0;
        $normalized = (1 - (sqrt($distance) / 100) - 0.2) / (0.8 - 0.2);
        return round(max(0.0, min(1.0, $normalized)), 2);
    }
}
