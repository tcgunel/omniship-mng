<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Exception\HttpException;
use Omniship\Common\Message\ResponseInterface;

/**
 * Tracking is two queries against /standardqueryapi:
 *   - /trackshipment/{referenceId} → list of shipment movements
 *   - /getshipmentstatus/{referenceId} → headline status (single object)
 *
 * The combined result lets GetTrackingStatusResponse build a TrackingInfo
 * with both the headline status and the event timeline.
 */
class GetTrackingStatusRequest extends AbstractMngRequest
{
    private const TRACK_PATH = '/mngapi/api/standardqueryapi/trackshipment/';
    private const STATUS_PATH = '/mngapi/api/standardqueryapi/getshipmentstatus/';

    protected function getEndpoint(): string
    {
        return self::TRACK_PATH . rawurlencode($this->resolveReference());
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('clientId', 'clientSecret', 'customerNumber', 'password');

        if ($this->getReferenceId() === null && $this->getTrackingNumber() === null) {
            throw new \Omniship\Common\Exception\InvalidRequestException(
                'Either referenceId or trackingNumber must be provided.',
            );
        }

        return [];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        $reference = $this->resolveReference();
        $statusBody = $this->fetchHeadlineStatus($reference);

        $events = is_array($data)
            ? (is_array($data['body'] ?? null) ? $data['body'] : [])
            : [];

        return new GetTrackingStatusResponse($this, [
            'reference' => $reference,
            'events' => $events,
            'status' => $statusBody,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchHeadlineStatus(string $reference): ?array
    {
        $response = $this->sendHttpRequest(
            method: 'GET',
            url: $this->getBaseUrl() . self::STATUS_PATH . rawurlencode($reference),
            headers: [
                'X-IBM-Client-Id' => $this->getClientId(),
                'X-IBM-Client-Secret' => $this->getClientSecret(),
                'Authorization' => 'Bearer ' . $this->fetchJwt(),
                'Accept' => 'application/json',
            ],
        );

        $body = (string) $response->getBody();
        $statusCode = $response->getStatusCode();

        if ($statusCode === 404) {
            return null;
        }

        if ($statusCode >= 400) {
            throw new HttpException(
                "MNG getShipmentStatus failed with HTTP {$statusCode}: {$body}",
            );
        }

        if ($body === '') {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveReference(): string
    {
        $reference = $this->getReferenceId() ?? $this->getTrackingNumber() ?? '';

        return $this->normalizeReference($reference);
    }
}
