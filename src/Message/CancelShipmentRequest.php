<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * PUT /standardcmdapi/cancelorder/{referenceId}
 * Empty request body; the reference is sent as a path parameter.
 */
class CancelShipmentRequest extends AbstractMngRequest
{
    private const PATH_PREFIX = '/mngapi/api/standardcmdapi/cancelorder/';

    protected function getEndpoint(): string
    {
        $referenceId = $this->normalizeReference((string) $this->getReferenceId());

        return self::PATH_PREFIX . rawurlencode($referenceId);
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
        $this->validate('clientId', 'clientSecret', 'customerNumber', 'password', 'referenceId');

        return [];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CancelShipmentResponse($this, $data);
    }
}
