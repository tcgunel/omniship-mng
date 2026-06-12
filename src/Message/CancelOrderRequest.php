<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * PUT /standardcmdapi/cancelorder/{referenceId} with no body.
 *
 * Cancels the createOrder order itself. Per MNG's integration team, a fully
 * cancelled shipment requires two steps when a barcode was issued: first
 * cancel the barcode (Barcode Command /cancelshipment), then cancel the
 * order with this endpoint. When no barcode was created, this endpoint
 * alone cancels the order.
 */
class CancelOrderRequest extends AbstractMngRequest
{
    private const PATH = '/mngapi/api/standardcmdapi/cancelorder/';

    protected function getEndpoint(): string
    {
        return self::PATH . rawurlencode(
            $this->normalizeReference((string) $this->getReferenceId()),
        );
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
        );

        return [];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CancelOrderResponse($this, $data);
    }
}
