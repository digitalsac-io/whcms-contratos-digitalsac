<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Delivery\WhatsApp;

use DigitalSac\MpContratos\Bootstrap;
use DigitalSac\MpContratos\Delivery\WhatsApp\Drivers\DigitalSacDriver;
use DigitalSac\MpContratos\Delivery\WhatsApp\Drivers\GenericApiDriver;
use DigitalSac\MpContratos\Delivery\WhatsApp\Drivers\ZuckZapGoDriver;

final class WhatsAppManager
{
    public function isEnabled(): bool
    {
        $driver = (string) Bootstrap::setting('whatsapp_driver', 'disabled');
        return $driver !== '' && $driver !== 'disabled';
    }

    public function driver(): ?WhatsAppDriverInterface
    {
        $name     = (string) Bootstrap::setting('whatsapp_driver', 'disabled');
        $endpoint = (string) Bootstrap::setting('whatsapp_endpoint', '');
        $token    = (string) Bootstrap::setting('whatsapp_token', '');
        $session  = (string) Bootstrap::setting('whatsapp_session', '');

        if ($endpoint === '' || $name === 'disabled') {
            return null;
        }

        return match ($name) {
            'digitalsac' => new DigitalSacDriver($endpoint, $token, $session),
            'zuckzapgo'  => new ZuckZapGoDriver($endpoint, $token),
            'generic'    => new GenericApiDriver($endpoint, $token),
            default      => null,
        };
    }

    public function send(Message $message): SendResult
    {
        $driver = $this->driver();
        if (!$driver) {
            return SendResult::fail('Driver de WhatsApp não configurado.');
        }
        return $driver->send($message);
    }
}
