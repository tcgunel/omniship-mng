<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Label;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ShipmentResponse;

class CreateReturnShipmentResponse extends AbstractResponse implements ShipmentResponse
{
    public function isSuccessful(): bool
    {
        $status = $this->httpStatus();

        return $status !== null && $status >= 200 && $status < 300;
    }

    public function getMessage(): ?string
    {
        $body = $this->body();

        if (is_array($body)) {
            if (isset($body['title']) && is_string($body['title'])) {
                return $body['title'];
            }
            if (isset($body['detail']) && is_string($body['detail'])) {
                return $body['detail'];
            }
        }

        return null;
    }

    public function getCode(): ?string
    {
        $status = $this->httpStatus();

        return $status === null ? null : (string) $status;
    }

    public function getShipmentId(): ?string
    {
        $body = $this->body();

        if (is_array($body) && isset($body['orderInvoiceId'])) {
            return (string) $body['orderInvoiceId'];
        }

        return null;
    }

    public function getTrackingNumber(): ?string
    {
        $body = $this->body();

        if (is_array($body) && isset($body['referenceId'])) {
            return (string) $body['referenceId'];
        }

        return null;
    }

    public function getBarcode(): ?string
    {
        return $this->getTrackingNumber();
    }

    public function getReturnLabelUrl(): ?string
    {
        $body = $this->body();

        if (is_array($body) && isset($body['returnOrderLabelURL'])) {
            return (string) $body['returnOrderLabelURL'];
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

    /**
     * @return array<string, mixed>|null
     */
    private function body(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['body'])) {
            return null;
        }

        return is_array($this->data['body']) ? $this->data['body'] : null;
    }

    private function httpStatus(): ?int
    {
        if (!is_array($this->data) || !isset($this->data['status'])) {
            return null;
        }

        return is_int($this->data['status']) ? $this->data['status'] : null;
    }
}
