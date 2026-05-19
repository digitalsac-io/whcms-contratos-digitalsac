<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp\Drivers;

use DigitalSac\MpContratos\Delivery\WhatsApp\Message;
use DigitalSac\MpContratos\Delivery\WhatsApp\SendResult;
use DigitalSac\MpContratos\Delivery\WhatsApp\WhatsAppDriverInterface;
use DigitalSac\MpContratos\Support\Logger;

/**
 * DigitalSac WhatsApp API driver.
 *
 * Endpoints (assumed):
 *   POST {endpoint}/api/sendText      { number, message, session }
 *   POST {endpoint}/api/sendFile      { number, base64, filename, caption, session }
 */
final class DigitalSacDriver implements WhatsAppDriverInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token,
        private readonly string $session,
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    public function name(): string
    {
        return 'digitalsac';
    }

    public function send(Message $message): SendResult
    {
        try {
            $headers = ['Authorization: Bearer ' . $this->token];
            $base    = rtrim($this->endpoint, '/');
            $phone   = preg_replace('/\D/', '', $message->phoneE164);

            if ($message->hasAttachment()) {
                $payload = [
                    'number'   => $phone,
                    'base64'   => base64_encode((string) file_get_contents($message->pdfPath)),
                    'filename' => $message->pdfFilename ?? 'contrato.pdf',
                    'caption'  => $message->caption ?? $message->text,
                    'session'  => $this->session,
                ];
                $resp = $this->http->postJson($base . '/api/sendFile', $payload, $headers);
            } else {
                $payload = [
                    'number'  => $phone,
                    'message' => $message->text,
                    'session' => $this->session,
                ];
                $resp = $this->http->postJson($base . '/api/sendText', $payload, $headers);
            }

            $ok = $resp['status'] >= 200 && $resp['status'] < 300;
            $providerId = $resp['decoded']['id'] ?? $resp['decoded']['messageId'] ?? null;

            Logger::moduleLog('digitalsac.send', ['url' => $base, 'phone' => $phone], (string) $resp['status']);

            return $ok
                ? SendResult::ok((string) $providerId, $resp['decoded'] ?? [])
                : SendResult::fail('HTTP ' . $resp['status'], $resp['decoded'] ?? []);
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }
}
