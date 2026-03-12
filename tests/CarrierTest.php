<?php

declare(strict_types=1);

use Omniship\MNG\Carrier;
use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CreateShipmentRequest;
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
        'username' => 'testuser',
        'password' => 'testpass',
        'testMode' => true,
    ]);
});

it('has the correct name', function () {
    expect($this->carrier->getName())->toBe('MNG Kargo')
        ->and($this->carrier->getShortName())->toBe('MNG');
});

it('has correct default parameters', function () {
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $carrier->initialize();

    expect($carrier->getUsername())->toBe('')
        ->and($carrier->getPassword())->toBe('')
        ->and($carrier->getTestMode())->toBeFalse();
});

it('initializes with custom parameters', function () {
    expect($this->carrier->getUsername())->toBe('testuser')
        ->and($this->carrier->getPassword())->toBe('testpass')
        ->and($this->carrier->getTestMode())->toBeTrue();
});

it('returns test base URL in test mode', function () {
    expect($this->carrier->getBaseUrl())->toContain('tservis');
});

it('returns production base URL in production mode', function () {
    $this->carrier->setTestMode(false);
    expect($this->carrier->getBaseUrl())->toContain('musterikargosiparis')
        ->and($this->carrier->getBaseUrl())->not->toContain('tservis');
});

it('supports createShipment method', function () {
    expect($this->carrier->supports('createShipment'))->toBeTrue();
});

it('supports getTrackingStatus method', function () {
    expect($this->carrier->supports('getTrackingStatus'))->toBeTrue();
});

it('supports cancelShipment method', function () {
    expect($this->carrier->supports('cancelShipment'))->toBeTrue();
});

it('creates a CreateShipmentRequest', function () {
    $request = $this->carrier->createShipment([
        'orderNumber' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(CreateShipmentRequest::class);
});

it('creates a GetTrackingStatusRequest', function () {
    $request = $this->carrier->getTrackingStatus([
        'trackingNumber' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(GetTrackingStatusRequest::class);
});

it('creates a CancelShipmentRequest', function () {
    $request = $this->carrier->cancelShipment([
        'orderNumber' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(CancelShipmentRequest::class);
});
