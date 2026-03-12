<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Label;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ShipmentResponse;

class CreateShipmentResponse extends AbstractResponse implements ShipmentResponse
{
    public function isSuccessful(): bool
    {
        return $this->getResult() === '1';
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['Message'])) {
            return null;
        }

        return (string) $this->data['Message'];
    }

    public function getCode(): ?string
    {
        return $this->getResult();
    }

    public function getShipmentId(): ?string
    {
        // MNG returns no shipment ID — the order number IS the identifier
        $request = $this->getRequest();

        if ($request instanceof CreateShipmentRequest) {
            return $request->getOrderNumber();
        }

        return null;
    }

    public function getTrackingNumber(): ?string
    {
        // The order number (pSiparisNo) serves as the tracking reference
        return $this->getShipmentId();
    }

    public function getBarcode(): ?string
    {
        // The barcode text sent during creation
        $request = $this->getRequest();

        if ($request instanceof CreateShipmentRequest) {
            return $request->getBarcodeText() ?? $request->getOrderNumber();
        }

        return null;
    }

    public function getLabel(): ?Label
    {
        return null;
    }

    public function getTotalCharge(): ?float
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    private function getResult(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['Result'])) {
            return null;
        }

        return (string) $this->data['Result'];
    }
}
