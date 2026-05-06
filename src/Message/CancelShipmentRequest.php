<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * PUT /barcodecmdapi/cancelshipment with body {referenceId, shipmentId}.
 *
 * MNG's integration team explicitly recommended this Barcode Command
 * variant over Standard Command's /cancelorder/{ref}. Cancellation is
 * only valid until the parcel is scanned/accepted at an MNG branch and
 * works on the same day the barcode was printed.
 */
class CancelShipmentRequest extends AbstractMngRequest
{
    private const PATH = '/mngapi/api/barcodecmdapi/cancelshipment';

    protected function getEndpoint(): string
    {
        return self::PATH;
    }

    protected function getHttpMethod(): string
    {
        return 'PUT';
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
            'shipmentId',
        );

        return [
            'referenceId' => $this->normalizeReference((string) $this->getReferenceId()),
            'shipmentId' => (string) $this->getShipmentId(),
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CancelShipmentResponse($this, $data);
    }
}
