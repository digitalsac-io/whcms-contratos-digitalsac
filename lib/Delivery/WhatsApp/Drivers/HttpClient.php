<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp\Drivers;

use DigitalSac\MpContratos\Support\Logger;

/**
 * Minimal cURL wrapper shared by WhatsApp drivers.
 * Avoids pulling Guzzle/composer deps so the addon drops in cleanly.
 */
final class HttpClient
{
    public function __construct(
        private readonly int $timeoutSec = 30,
        private readonly bool $verifySsl = true,
    ) {}

    /** @return array{status:int, body:string, decoded:array|null} */
    public function postJson(string $url, array $body, array $headers = []): array
    {
        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        return $this->exec($url, 'POST', $headers, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{status:int, body:string, decoded:array|null} */
    public function postMultipart(string $url, array $fields, array $headers = []): array
    {
        $payload = [];
        foreach ($fields as $k => $v) {
            $payload[$k] = $v instanceof \CURLFile ? $v : (string) $v;
        }
        return $this->exec($url, 'POST', $headers, $payload);
    }

    private function exec(string $url, string $method, array $headers, mixed $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeoutSec,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $rawBody = curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($rawBody === false) {
            Logger::moduleLog('http.exec', ['url' => $url, 'method' => $method], 'curl error: ' . $err);
            return ['status' => 0, 'body' => '', 'decoded' => null];
        }

        $decoded = null;
        if ($rawBody !== '' && str_starts_with(ltrim((string) $rawBody), '{')) {
            $decoded = json_decode((string) $rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }
        return ['status' => $status, 'body' => (string) $rawBody, 'decoded' => $decoded];
    }
}
