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
        $orderHttp = $this->intField('orderHttpStatus');
        $barcodeHttp = $this->intField('barcodeHttpStatus');

        if ($orderHttp === null || $orderHttp < 200 || $orderHttp >= 300) {
            return false;
        }

        if ($barcodeHttp === null) {
            return false;
        }

        return $barcodeHttp >= 200 && $barcodeHttp < 300;
    }

    public function getMessage(): ?string
    {
        return self::extractErrorMessage($this->orderBody())
            ?? self::extractErrorMessage($this->barcodeBody());
    }

    /**
     * @param array<string, mixed>|null $body
     */
    public static function extractErrorMessage(?array $body): ?string
    {
        if ($body === null) {
            return null;
        }

        // MNG error shape: {"error":{"code":"...","message":"...","description":"..."}}
        if (isset($body['error']) && is_array($body['error'])) {
            $error = $body['error'];
            $description = $error['description'] ?? null;
            if (is_string($description) && $description !== '') {
                return $description;
            }
            $message = $error['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        // ProblemDetails shape (per swagger): {"title":"...","detail":"..."}
        if (isset($body['detail']) && is_string($body['detail']) && $body['detail'] !== '') {
            return $body['detail'];
        }
        if (isset($body['title']) && is_string($body['title']) && $body['title'] !== '') {
            return $body['title'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        $http = $this->intField('orderHttpStatus');

        return $http === null ? null : (string) $http;
    }

    public function getShipmentId(): ?string
    {
        $barcodeBody = $this->barcodeBody();

        if (is_array($barcodeBody) && isset($barcodeBody['shipmentId'])) {
            return (string) $barcodeBody['shipmentId'];
        }

        $orderBody = $this->orderBody();
        if (is_array($orderBody) && isset($orderBody['orderInvoiceId'])) {
            return (string) $orderBody['orderInvoiceId'];
        }

        return null;
    }

    public function getTrackingNumber(): ?string
    {
        $barcodeBody = $this->barcodeBody();

        if (is_array($barcodeBody) && isset($barcodeBody['shipmentId'])) {
            return (string) $barcodeBody['shipmentId'];
        }

        $orderBody = $this->orderBody();
        if (is_array($orderBody) && isset($orderBody['referenceId'])) {
            return (string) $orderBody['referenceId'];
        }

        return null;
    }

    public function getBarcode(): ?string
    {
        $barcodes = $this->getBarcodes();

        return $barcodes[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getBarcodes(): array
    {
        $barcodeBody = $this->barcodeBody();

        if (
            !is_array($barcodeBody)
            || !isset($barcodeBody['barcodes'])
            || !is_array($barcodeBody['barcodes'])
        ) {
            return [];
        }

        $values = [];
        foreach ($barcodeBody['barcodes'] as $entry) {
            if (is_array($entry) && isset($entry['value'])) {
                $values[] = (string) $entry['value'];
            }
        }

        return $values;
    }

    public function getInvoiceId(): ?string
    {
        $barcodeBody = $this->barcodeBody();

        if (is_array($barcodeBody) && isset($barcodeBody['invoiceId'])) {
            return (string) $barcodeBody['invoiceId'];
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
    private function orderBody(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['order'])) {
            return null;
        }

        return is_array($this->data['order']) ? $this->data['order'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function barcodeBody(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['barcode'])) {
            return null;
        }

        return is_array($this->data['barcode']) ? $this->data['barcode'] : null;
    }

    private function intField(string $key): ?int
    {
        if (!is_array($this->data) || !isset($this->data[$key])) {
            return null;
        }

        return is_int($this->data[$key]) ? $this->data[$key] : null;
    }
}
