<?php

declare(strict_types=1);

use Omniship\MNG\Carrier;
use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CreateRecipientRequest;
use Omniship\MNG\Message\CreateReturnShipmentRequest;
use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\GetCitiesRequest;
use Omniship\MNG\Message\GetDistrictsRequest;
use Omniship\MNG\Message\GetTrackingStatusRequest;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

beforeEach(function () {
    $this->carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->carrier->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => '12345',
        'password' => 'pwd',
        'testMode' => true,
    ]);
});

it('has the correct name', function () {
    expect($this->carrier->getName())->toBe('MNG Kargo')
        ->and($this->carrier->getShortName())->toBe('MNG');
});

it('uses test base URL in test mode and prod URL otherwise', function () {
    expect($this->carrier->getBaseUrl())->toBe('https://testapi.mngkargo.com.tr');

    $this->carrier->setTestMode(false);
    expect($this->carrier->getBaseUrl())->toBe('https://api.mngkargo.com.tr');
});

it('exposes default parameters with empty credentials', function () {
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $carrier->initialize();

    $defaults = $carrier->getDefaultParameters();

    expect($defaults['clientId'])->toBe('')
        ->and($defaults['customerNumber'])->toBe('')
        ->and($defaults['identityType'])->toBe(1)
        ->and($defaults['testMode'])->toBeFalse();
});

it('supports the standard carrier methods', function () {
    expect($this->carrier->supports('createShipment'))->toBeTrue()
        ->and($this->carrier->supports('createRecipient'))->toBeTrue()
        ->and($this->carrier->supports('createReturnShipment'))->toBeTrue()
        ->and($this->carrier->supports('getTrackingStatus'))->toBeTrue()
        ->and($this->carrier->supports('cancelShipment'))->toBeTrue()
        ->and($this->carrier->supports('getCities'))->toBeTrue()
        ->and($this->carrier->supports('getDistricts'))->toBeTrue();
});

it('returns the right request class per method', function () {
    expect($this->carrier->createShipment())->toBeInstanceOf(CreateShipmentRequest::class)
        ->and($this->carrier->createRecipient())->toBeInstanceOf(CreateRecipientRequest::class)
        ->and($this->carrier->createReturnShipment())->toBeInstanceOf(CreateReturnShipmentRequest::class)
        ->and($this->carrier->cancelShipment())->toBeInstanceOf(CancelShipmentRequest::class)
        ->and($this->carrier->getTrackingStatus())->toBeInstanceOf(GetTrackingStatusRequest::class)
        ->and($this->carrier->getCities())->toBeInstanceOf(GetCitiesRequest::class)
        ->and($this->carrier->getDistricts())->toBeInstanceOf(GetDistrictsRequest::class);
});
