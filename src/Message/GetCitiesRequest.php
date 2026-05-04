<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * GET /cbsinfoapi/getcities — list of {code, name} for Turkish provinces.
 * No JWT required; only IBM client headers.
 */
class GetCitiesRequest extends AbstractMngRequest
{
    private const PATH = '/mngapi/api/cbsinfoapi/getcities';

    protected function getEndpoint(): string
    {
        return self::PATH;
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function requiresJwt(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('clientId', 'clientSecret');

        return [];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetCitiesResponse($this, $data);
    }
}
