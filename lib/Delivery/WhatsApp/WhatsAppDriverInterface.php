<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp;

interface WhatsAppDriverInterface
{
    /**
     * Driver identifier (digitalsac, zuckzapgo, generic).
     */
    public function name(): string;

    /**
     * Send a message. Drivers should never throw; failure is reported via SendResult.
     */
    public function send(Message $message): SendResult;
}
