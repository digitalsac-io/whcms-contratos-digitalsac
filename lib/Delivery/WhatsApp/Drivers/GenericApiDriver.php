<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp\Drivers;

use DigitalSac\MpContratos\Delivery\WhatsApp\Message;
use DigitalSac\MpContratos\Delivery\WhatsApp\SendResult;
use DigitalSac\MpContratos\Delivery\WhatsApp\WhatsAppDriverInterface;
use DigitalSac\MpContratos\Support\Logger;

/**
 * Generic JSON-POST WhatsApp driver. Sends a flexible payload so admins can
 * plug in any provider that accepts a JSON body with phone + text + optional
 * base64 attachment.
 *
 * Payload shape:
 *   { phone, text, has_attachment, file_name, file_base64, file_caption }
 *
 * Auth: token is sent as Authorization: Bearer <token>.
 */
final class GenericApiDriver implements WhatsAppDriverInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token = '',
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    public function name(): string
    {
        return 'generic';
    }

    public function send(Message $message): SendResult
    {
        try {
            $headers = [];
            if ($this->token !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->token;
            }
            $phone = preg_replace('/\D/', '', $message->phoneE164);
            $payload = [
                'phone'          => $phone,
                'text'           => $message->text,
                'has_attachment' => $message->hasAttachment(),
            ];
            if ($message->hasAttachment()) {
                $payload['file_name']    = $message->pdfFilename ?? 'contrato.pdf';
                $payload['file_caption'] = $message->caption ?? $message->text;
                $payload['file_base64']  = base64_encode((string) file_get_contents($message->pdfPath));
            }

            $resp = $this->http->postJson($this->endpoint, $payload, $headers);
            $ok = $resp['status'] >= 200 && $resp['status'] < 300;
            $providerId = $resp['decoded']['id'] ?? $resp['decoded']['message_id'] ?? null;

            Logger::moduleLog('generic.send', ['url' => $this->endpoint, 'phone' => $phone], (string) $resp['status']);

            return $ok
                ? SendResult::ok((string) $providerId, $resp['decoded'] ?? [])
                : SendResult::fail('HTTP ' . $resp['status'], $resp['decoded'] ?? []);
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }
}
