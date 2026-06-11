<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\HttpException;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

/**
 * Two-step flow:
 *   1. POST /standardcmdapi/createOrder      → creates the order
 *   2. POST /barcodecmdapi/createbarcode     → invoices it and returns barcodes
 */
class CreateShipmentRequest extends AbstractMngRequest
{
    private const CREATE_ORDER_PATH = '/mngapi/api/standardcmdapi/createOrder';
    private const CREATE_BARCODE_PATH = '/mngapi/api/barcodecmdapi/createbarcode';

    /**
     * MNG returns error 20001 ("VARIŞ ŞUBESİ BULUNAMADI") when createBarcode
     * fires before they've resolved the destination branch from the recipient
     * registration. Retry pattern: total 3 attempts (initial + 2 retries),
     * sleeping between each so MNG has time to finish the lookup.
     */
    private const BARCODE_RETRY_DELAYS_SECONDS = [5, 10];

    protected function getEndpoint(): string
    {
        return self::CREATE_ORDER_PATH;
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * Whether to chain the second step (createBarcode) after createOrder.
     *
     * Defaults to true to preserve the historical two-step behaviour. Set to
     * false for merchants that don't print labels, or whose MNG account isn't
     * subscribed to the Barcode Command API product. When false the shipment
     * is registered (order placed) but no ZPL label / scannable barcode is
     * generated, and tracking falls back to the referenceId.
     */
    public function getCreateBarcode(): bool
    {
        return (bool) ($this->getParameter('createBarcode') ?? true);
    }

    public function setCreateBarcode(bool $value): static
    {
        return $this->setParameter('createBarcode', $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate(
            'clientId',
            'clientSecret',
            'customerNumber',
            'password',
            'referenceId',
            'shipTo',
            'recipientCityCode',
            'recipientDistrictCode',
        );

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        $packages = $this->getPackages() ?? [];
        $referenceId = $this->normalizeReference((string) $this->getReferenceId());

        return [
            'order' => $this->buildOrder($referenceId, $packages),
            'orderPieceList' => $this->buildPieceList($referenceId, $packages),
            'recipient' => $this->buildCustomer($shipTo),
        ];
    }

    /**
     * Parent's sendData() does step 1; createResponse() chains step 2 inside.
     */
    protected function createResponse(mixed $data): ResponseInterface
    {
        $orderBody = is_array($data) ? ($data['body'] ?? null) : null;
        $statusCode = is_array($data) ? ($data['status'] ?? 0) : 0;

        $orderSucceeded = $statusCode >= 200 && $statusCode < 300 && is_array($orderBody);

        // MNG returns 500 + code 3002 / "BU SİPARİS NUMARASINA AİT KAYIT ZATEN
        // VAR!" when the same referenceId was already accepted on a previous
        // call. The order IS on their side — skip ahead to createBarcode.
        $orderAlreadyExists = !$orderSucceeded
            && is_array($orderBody)
            && $this->isOrderAlreadyExistsError($orderBody);

        if ($orderSucceeded || $orderAlreadyExists) {
            // Merchant (or their MNG account) opted out of barcode creation —
            // stop after createOrder. Success then rests on the order alone.
            if (!$this->getCreateBarcode()) {
                return new CreateShipmentResponse($this, [
                    'order' => $orderBody,
                    'barcode' => null,
                    'orderHttpStatus' => $orderAlreadyExists ? 200 : $statusCode,
                    'barcodeHttpStatus' => null,
                    'barcodeSkipped' => true,
                ]);
            }

            $barcodeBody = $this->callCreateBarcode();

            return new CreateShipmentResponse($this, [
                'order' => $orderBody,
                'barcode' => $barcodeBody,
                // synthesize a success status when we recovered from "already
                // exists" so isSuccessful() reflects the real outcome
                'orderHttpStatus' => $orderAlreadyExists ? 200 : $statusCode,
                'barcodeHttpStatus' => $barcodeBody === null ? null : 200,
            ]);
        }

        // Step 1 failed for a real reason — return as-is, no barcoding
        return new CreateShipmentResponse($this, [
            'order' => $orderBody,
            'barcode' => null,
            'orderHttpStatus' => $statusCode,
            'barcodeHttpStatus' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function isOrderAlreadyExistsError(array $body): bool
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : null;
        if ($error === null) {
            return false;
        }

        if (($error['Code'] ?? $error['code'] ?? null) === '3002') {
            return true;
        }

        $description = (string) ($error['Description'] ?? $error['description'] ?? '');

        return str_contains($description, 'ZATEN VAR');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callCreateBarcode(): ?array
    {
        $referenceId = $this->normalizeReference((string) $this->getReferenceId());
        $packages = $this->getPackages() ?? [];

        $body = json_encode([
            'referenceId' => $referenceId,
            'billOfLandingId' => $this->getBillOfLandingId() ?? $this->getInvoiceNumber() ?? '',
            'isCOD' => $this->getCashOnDelivery() ? 1 : 0,
            'codAmount' => $this->getCashOnDelivery() ? $this->getCodAmount() : 0,
            'printReferenceBarcodeOnError' => 0,
            'message' => '',
            'additionalContent1' => '',
            'additionalContent2' => '',
            'additionalContent3' => '',
            'additionalContent4' => '',
            'orderPieceList' => $this->buildPieceList($referenceId, $packages),
            'packagingType' => $this->getPackagingType(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $headers = [
            'X-IBM-Client-Id' => $this->getClientId(),
            'X-IBM-Client-Secret' => $this->getClientSecret(),
            'Authorization' => 'Bearer ' . $this->fetchJwt(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $attempt = 0;
        $maxAttempts = count(self::BARCODE_RETRY_DELAYS_SECONDS) + 1;

        while (true) {
            $attempt++;

            $response = $this->sendHttpRequest(
                method: 'POST',
                url: $this->getBaseUrl() . self::CREATE_BARCODE_PATH,
                headers: $headers,
                body: $body,
            );

            $responseBody = (string) $response->getBody();
            $statusCode = $response->getStatusCode();

            // MNG-specific: branch not resolved yet, retry after a delay.
            if ($this->isBranchNotResolvedYet($statusCode, $responseBody) && $attempt < $maxAttempts) {
                $delay = self::BARCODE_RETRY_DELAYS_SECONDS[$attempt - 1];
                $this->sleep($delay);
                continue;
            }

            if ($statusCode >= 400) {
                throw new HttpException(
                    "MNG createBarcode failed with HTTP {$statusCode}: {$responseBody}",
                );
            }

            if ($responseBody === '') {
                return null;
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        }
    }

    /**
     * Detects MNG error 20001 — destination branch not yet resolved on
     * their backend. Identified by either the numeric code or the
     * Turkish error description.
     */
    private function isBranchNotResolvedYet(int $statusCode, string $responseBody): bool
    {
        if ($statusCode < 400 || $responseBody === '') {
            return false;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : null;
        if ($error === null) {
            return false;
        }

        if (($error['Code'] ?? $error['code'] ?? null) === '20001') {
            return true;
        }

        $description = (string) ($error['Description'] ?? $error['description'] ?? '');

        return str_contains($description, 'VARIŞ ŞUBESİ');
    }

    /**
     * Indirected for testability (overridable / mockable).
     */
    protected function sleep(int $seconds): void
    {
        sleep(max(0, $seconds));
    }

    /**
     * @param Package[] $packages
     * @return array<string, mixed>
     */
    private function buildOrder(string $referenceId, array $packages): array
    {
        $contentSummary = $this->contentSummary($packages);

        return [
            'referenceId' => $referenceId,
            'barcode' => $referenceId,
            'billOfLandingId' => $this->getBillOfLandingId() ?? $this->getInvoiceNumber() ?? '',
            'isCOD' => $this->getCashOnDelivery() ? 1 : 0,
            'codAmount' => $this->getCashOnDelivery() ? $this->getCodAmount() : 0,
            'shipmentServiceType' => $this->getShipmentServiceType(),
            'packagingType' => $this->getPackagingType(),
            'content' => $this->getContent() !== '' ? $this->getContent() : $contentSummary,
            'smsPreference1' => $this->getSendSmsRecipientArrival() ? 1 : 0,
            'smsPreference2' => $this->getSendSmsRecipientPrepared() ? 1 : 0,
            'smsPreference3' => $this->getSendSmsShipperDelivered() ? 1 : 0,
            'paymentType' => $this->mapPaymentType($this->getPaymentType()),
            'deliveryType' => $this->getDeliveryType(),
            'description' => $this->getDescription() !== '' ? $this->getDescription() : $contentSummary,
            'marketPlaceShortCode' => $this->getMarketPlaceShortCode(),
            'marketPlaceSaleCode' => $this->getMarketPlaceSaleCode(),
        ];
    }

    /**
     * @param Package[] $packages
     * @return array<int, array<string, mixed>>
     */
    private function buildPieceList(string $referenceId, array $packages): array
    {
        if ($packages === []) {
            return [[
                'barcode' => $referenceId . '_PARCA1',
                'desi' => 0,
                'kg' => 0,
                'content' => '',
            ]];
        }

        $pieces = [];
        $index = 1;

        foreach ($packages as $package) {
            $quantity = max(1, $package->quantity);
            for ($i = 0; $i < $quantity; $i++) {
                $pieces[] = [
                    'barcode' => $referenceId . '_PARCA' . $index,
                    'desi' => (int) round($package->getDesi() ?? 0.0),
                    'kg' => (int) round($package->weight),
                    'content' => $package->description ?? '',
                ];
                $index++;
            }
        }

        return $pieces;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomer(Address $address): array
    {
        return [
            // Omit customerId — MNG rejects when both customerId and fullName
            // are set. We always send fullName/address-style payload.
            'refCustomerId' => '',
            'cityCode' => (int) $this->getRecipientCityCode(),
            'cityName' => $address->city ?? '',
            'districtCode' => (int) $this->getRecipientDistrictCode(),
            'districtName' => $address->district ?? '',
            'address' => $address->street1 ?? '',
            'bussinessPhoneNumber' => '',
            'email' => $address->email ?? '',
            'taxOffice' => $address->taxId !== null ? '' : 'SAHIS',
            'taxNumber' => $this->getRecipientTaxNumber()
                ?? $address->taxId
                ?? $address->nationalId
                ?? '',
            'fullName' => $address->name ?? '',
            'homePhoneNumber' => '',
            'mobilePhoneNumber' => $this->normalizePhone($address->phone ?? ''),
        ];
    }

    /**
     * @param Package[] $packages
     */
    private function contentSummary(array $packages): string
    {
        foreach ($packages as $package) {
            if ($package->description !== null && $package->description !== '') {
                return $package->description;
            }
        }

        return '-';
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone) ?? $phone;

        if (str_starts_with($cleaned, '90') && strlen($cleaned) === 12) {
            $cleaned = substr($cleaned, 2);
        }

        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
            $cleaned = substr($cleaned, 1);
        }

        return $cleaned;
    }
}
