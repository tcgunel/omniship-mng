<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

/**
 * Single-call return-order creation. The response includes a label URL the
 * shipper hands to the consumer to drop off the parcel.
 */
class CreateReturnShipmentRequest extends AbstractMngRequest
{
    private const PATH = '/mngapi/api/standardcmdapi/createReturnOrder';

    protected function getEndpoint(): string
    {
        return self::PATH;
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
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
            'shipFrom',
            'recipientCityCode',
            'recipientDistrictCode',
        );

        $shipFrom = $this->getShipFrom();
        assert($shipFrom instanceof Address);

        $packages = $this->getPackages() ?? [];
        $referenceId = $this->normalizeReference((string) $this->getReferenceId());

        return [
            'order' => $this->buildOrder($referenceId, $packages),
            'orderPieceList' => $this->buildPieceList($referenceId, $packages),
            'shipper' => $this->buildCustomer($shipFrom),
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CreateReturnShipmentResponse($this, $data);
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
            'customerId' => 0,
            'refCustomerId' => '',
            'cityCode' => (int) $this->getRecipientCityCode(),
            'cityName' => $address->city ?? '',
            'districtCode' => (int) $this->getRecipientDistrictCode(),
            'districtName' => $address->district ?? '',
            'address' => $address->street1 ?? '',
            'bussinessPhoneNumber' => '',
            'email' => $address->email ?? '',
            'taxOffice' => $address->taxId !== null ? '' : 'SAHIS',
            'taxNumber' => $address->taxId ?? $address->nationalId ?? '',
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
