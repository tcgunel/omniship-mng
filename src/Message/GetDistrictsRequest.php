<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * GET /cbsinfoapi/getdistricts/{cityCode}
 * No JWT required.
 */
class GetDistrictsRequest extends AbstractMngRequest
{
    private const PATH_PREFIX = '/mngapi/api/cbsinfoapi/getdistricts/';

    public function getCityCode(): ?int
    {
        $value = $this->getParameter('cityCode');

        return $value === null ? null : (int) $value;
    }

    public function setCityCode(int $cityCode): static
    {
        return $this->setParameter('cityCode', $cityCode);
    }

    protected function getEndpoint(): string
    {
        $cityCode = (int) $this->getCityCode();

        // MNG city codes are 1–81; pad to two digits to match their format ("01"…"81")
        $padded = str_pad((string) $cityCode, 2, '0', STR_PAD_LEFT);

        return self::PATH_PREFIX . rawurlencode($padded);
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
        $this->validate('clientId', 'clientSecret', 'cityCode');

        return [];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetDistrictsResponse($this, $data);
    }
}
