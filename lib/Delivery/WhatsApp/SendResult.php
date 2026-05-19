<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp;

final class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public static function ok(?string $providerId = null, array $raw = []): self
    {
        return new self(true, $providerId, null, $raw);
    }

    public static function fail(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }
}
