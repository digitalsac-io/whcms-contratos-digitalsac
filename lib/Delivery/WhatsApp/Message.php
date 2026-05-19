<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp;

/**
 * Immutable value object describing a WhatsApp message to be sent.
 * Drivers map this to their specific payload formats.
 */
final class Message
{
    public function __construct(
        public readonly string $phoneE164,
        public readonly string $text,
        public readonly ?string $pdfPath = null,
        public readonly ?string $pdfFilename = null,
        public readonly ?string $caption = null,
        public readonly array $meta = [],
    ) {}

    public function hasAttachment(): bool
    {
        return $this->pdfPath !== null && is_file($this->pdfPath);
    }
}
