<?php

declare(strict_types=1);

use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CancelShipmentResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createCancelSuccessXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<SiparisIptali_C2CResponse xmlns="http://tempuri.org/">'
        . '<SiparisIptali_C2CResult>1</SiparisIptali_C2CResult>'
        . '</SiparisIptali_C2CResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createCancelFailureXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<SiparisIptali_C2CResponse xmlns="http://tempuri.org/">'
        . '<SiparisIptali_C2CResult>0</SiparisIptali_C2CResult>'
        . '</SiparisIptali_C2CResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createCancelRequest(string $responseXml): CancelShipmentRequest
{
    $request = new CancelShipmentRequest(
        createMockHttpClient($responseXml),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    return $request;
}

it('builds correct cancel data', function () {
    $request = createCancelRequest(createCancelSuccessXml());
    $request->initialize([
        'username' => '1234567890',
        'password' => 'ABCDEF123',
        'orderNumber' => 'SIP-001',
    ]);

    $data = $request->getData();

    expect($data['pkullaniciAdi'])->toBe('1234567890')
        ->and($data['pSifre'])->toBe('ABCDEF123')
        ->and($data['pSiparisNo'])->toBe('SIP-001');
});

it('sends request and returns successful cancel', function () {
    $request = createCancelRequest(createCancelSuccessXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-001',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CancelShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();
});

it('sends request and returns failed cancel', function () {
    $request = createCancelRequest(createCancelFailureXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-FAIL',
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse();
});

it('throws exception when order number is missing', function () {
    $request = createCancelRequest(createCancelSuccessXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
