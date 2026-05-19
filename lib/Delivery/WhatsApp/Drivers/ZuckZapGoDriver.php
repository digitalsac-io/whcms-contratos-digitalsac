<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp\Drivers;

use DigitalSac\MpContratos\Delivery\WhatsApp\Message;
use DigitalSac\MpContratos\Delivery\WhatsApp\SendResult;
use DigitalSac\MpContratos\Delivery\WhatsApp\WhatsAppDriverInterface;
use DigitalSac\MpContratos\Support\Logger;

/**
 * ZuckZapGo driver — talks to the Go rewrite of ZuckZapGo v1.9.1.
 *
 * Auth header: Token <token>  (per the original API)
 * Endpoints used:
 *   POST {endpoint}/chat/send/text       { Phone, Body }
 *   POST {endpoint}/chat/send/document   { Phone, Document (base64), FileName, Caption }
 */
final class ZuckZapGoDriver implements WhatsAppDriverInterface
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token,
        private readonly HttpClient $http = new HttpClient(),
    ) {}

    public function name(): string
    {
        return 'zuckzapgo';
    }

    public function send(Message $message): SendResult
    {
        try {
            $headers = ['Token: ' . $this->token];
            $base    = rtrim($this->endpoint, '/');
            $phone   = preg_replace('/\D/', '', $message->phoneE164);

            if ($message->hasAttachment()) {
                $payload = [
                    'Phone'    => $phone,
                    'Document' => 'data:application/pdf;base64,' . base64_encode((string) file_get_contents($message->pdfPath)),
                    'FileName' => $message->pdfFilename ?? 'contrato.pdf',
                    'Caption'  => $message->caption ?? $message->text,
                ];
                $resp = $this->http->postJson($base . '/chat/send/document', $payload, $headers);
            } else {
                $payload = ['Phone' => $phone, 'Body' => $message->text];
                $resp = $this->http->postJson($base . '/chat/send/text', $payload, $headers);
            }

            $ok = $resp['status'] >= 200 && $resp['status'] < 300
                && (!isset($resp['decoded']['success']) || $resp['decoded']['success'] === true);
            $providerId = $resp['decoded']['data']['Id'] ?? null;

            Logger::moduleLog('zuckzapgo.send', ['url' => $base, 'phone' => $phone], (string) $resp['status']);

            return $ok
                ? SendResult::ok((string) $providerId, $resp['decoded'] ?? [])
                : SendResult::fail('HTTP ' . $resp['status'] . ' ' . ($resp['decoded']['error'] ?? ''), $resp['decoded'] ?? []);
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }
}
