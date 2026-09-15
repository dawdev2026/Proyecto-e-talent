<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function send(array $settings, string $recipient, string $subject, string $htmlBody, string $textBody = ''): void
    {
        if (empty($settings['enabled'])) {
            $this->logFailure('correo SMTP desactivado');
            throw new RuntimeException('El correo SMTP de la empresa está desactivado.');
        }
        if (trim((string) ($settings['host'] ?? '')) === '') {
            $this->logFailure('servidor SMTP no configurado');
            throw new RuntimeException('El servidor SMTP no está configurado.');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->logFailure('destinatario no válido');
            throw new InvalidArgumentException('El destinatario no es válido.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = trim((string) $settings['host']);
        $mail->Port = max(1, min(65535, (int) ($settings['port'] ?? 587)));
        $mail->SMTPAuth = !empty($settings['smtp_auth']);
        if ($mail->SMTPAuth) {
            $mail->Username = (string) ($settings['username'] ?? '');
            $mail->Password = (string) ($settings['password'] ?? '');
        }

        $encryption = strtolower((string) ($settings['encryption'] ?? 'starttls'));
        if ($encryption === 'smtps') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }

        $authType = strtoupper(trim((string) ($settings['auth_type'] ?? '')));
        if (in_array($authType, ['LOGIN', 'PLAIN', 'CRAM-MD5'], true)) {
            $mail->AuthType = $authType;
        }

        $mail->Timeout = max(5, min(120, (int) ($settings['timeout'] ?? 30)));
        $fromEmail = trim((string) ($settings['from_email'] ?? $settings['username'] ?? ''));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logFailure('remitente no válido');
            throw new RuntimeException('El correo remitente no es válido.');
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, trim((string) ($settings['from_name'] ?? 'e-talent')) ?: 'e-talent');
        $replyTo = trim((string) ($settings['reply_to_email'] ?? ''));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo, trim((string) ($settings['reply_to_name'] ?? '')));
        }
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        try {
            $mail->send();
        } catch (MailException $exception) {
            $errorInfo = trim((string) $mail->ErrorInfo);
            if ($errorInfo === '') {
                $errorInfo = $exception->getMessage();
            }
            $errorInfo = preg_replace(
                '/(password|pass|pwd)\s*[:=]\s*[^\s,;]+/i',
                '$1=[redacted]',
                $errorInfo
            ) ?? $errorInfo;
            $this->logFailure($errorInfo);
            throw new RuntimeException('No se pudo enviar el correo SMTP.', 0, $exception);
        }
    }

    private function logFailure(string $message): void
    {
        $message = preg_replace(
            '/(password|pass|pwd)\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            trim($message)
        ) ?? trim($message);
        security_log('Fallo SMTP: ' . $message);
    }
}
