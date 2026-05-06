<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;

/**
 * Plus Command — POST /pluscmdapi/createRecipient
 *
 * Pre-registers a recipient address with DHL eCommerce so the destination
 * branch is resolved BEFORE the merchant calls createOrder/createBarcode.
 * MNG warns that calling createOrder + createbarcode back-to-back without
 * this pre-registration can produce "no destination" errors.
 *
 * Idempotent on MNG's side; safe to retry. Does not return a customerId
 * (per swagger), so we don't store anything from the response — we just
 * stamp our local "recipient registered" flag on success.
 */
class CreateRecipientRequest extends AbstractMngRequest
{
    private const PATH = '/mngapi/api/pluscmdapi/createRecipient';

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
            'shipTo',
            'recipientCityCode',
            'recipientDistrictCode',
        );

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        return [
            'recipient' => [
                'customerId' => 0,
                'refCustomerId' => '',
                'cityCode' => (int) $this->getRecipientCityCode(),
                'cityName' => $shipTo->city ?? '',
                'districtCode' => (int) $this->getRecipientDistrictCode(),
                'districtName' => $shipTo->district ?? '',
                'address' => $shipTo->street1 ?? '',
                'bussinessPhoneNumber' => '',
                'email' => $shipTo->email ?? '',
                'taxOffice' => $shipTo->taxId !== null ? '' : 'SAHIS',
                'taxNumber' => $this->getRecipientTaxNumber()
                    ?? $shipTo->taxId
                    ?? $shipTo->nationalId
                    ?? '',
                'fullName' => $shipTo->name ?? '',
                'homePhoneNumber' => '',
                'mobilePhoneNumber' => $this->normalizePhone($shipTo->phone ?? ''),
            ],
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CreateRecipientResponse($this, $data);
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
