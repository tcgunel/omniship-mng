<?php

declare(strict_types=1);

namespace Omniship\MNG;

use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Auth\UsernamePasswordTrait;
use Omniship\Common\Message\RequestInterface;
use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\GetTrackingStatusRequest;

class Carrier extends AbstractHttpCarrier
{
    use UsernamePasswordTrait;

    private const BASE_URL_TEST = 'https://service.mngkargo.com.tr/tservis/musterisiparisnew.asmx';
    private const BASE_URL_PRODUCTION = 'https://service.mngkargo.com.tr/musterikargosiparis/musterisiparisnew.asmx';

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
            'username' => '',
            'password' => '',
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

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }
}
