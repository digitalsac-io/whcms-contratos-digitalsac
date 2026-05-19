<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp;

/**
 * Minimal cURL wrapper with JSON helpers. Avoids a Guzzle dependency.
 */
final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        private readonly int $timeout = 30,
    ) {}

    public function postJson(string $path, array $body, array $headers = []): array
    {
        return $this->request('POST', $path, $headers, json_encode($body, JSON_UNESCAPED_UNICODE), 'application/json');
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, $headers, null, null);
    }

    private function request(string $method, string $path, array $headers, mixed $body, ?string $contentType): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        $allHeaders = array_merge($this->defaultHeaders, $headers);
        if ($contentType) {
            $allHeaders['Content-Type'] = $contentType;
        }
        $headerLines = [];
        foreach ($allHeaders as $k => $v) {
            $headerLines[] = is_int($k) ? $v : "{$k}: {$v}";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status' => 0, 'body' => null, 'raw' => '', 'error' => $err];
        }
        $decoded = json_decode((string) $raw, true);
        return [
            'status' => (int) $status,
            'body'   => is_array($decoded) ? $decoded : null,
            'raw'    => (string) $raw,
            'error'  => null,
        ];
    }
}
