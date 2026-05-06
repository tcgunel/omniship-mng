<?php

declare(strict_types=1);

namespace Omniship\MNG;

use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Message\RequestInterface;
use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CreateRecipientRequest;
use Omniship\MNG\Message\CreateReturnShipmentRequest;
use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\GetCitiesRequest;
use Omniship\MNG\Message\GetDistrictsRequest;
use Omniship\MNG\Message\GetTrackingStatusRequest;

class Carrier extends AbstractHttpCarrier
{
    private const BASE_URL_TEST = 'https://testapi.mngkargo.com.tr';
    private const BASE_URL_PRODUCTION = 'https://api.mngkargo.com.tr';

    public function getName(): string
    {
        return 'MNG Kargo';
    }

    public function getShortName(): string
    {
        return 'MNG';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'customerNumber' => '',
            'password' => '',
            'identityType' => 1,
            'testMode' => false,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createRecipient(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateRecipientRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createReturnShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateReturnShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getTrackingStatus(array $options = []): RequestInterface
    {
        return $this->createRequest(GetTrackingStatusRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cancelShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CancelShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getCities(array $options = []): RequestInterface
    {
        return $this->createRequest(GetCitiesRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getDistricts(array $options = []): RequestInterface
    {
        return $this->createRequest(GetDistrictsRequest::class, $options);
    }

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }
}
