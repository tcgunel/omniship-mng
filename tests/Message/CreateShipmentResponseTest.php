<?php

declare(strict_types=1);

use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\CreateShipmentResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createShipmentResponseWith(array $data): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-001',
        'barcodeText' => 'BARK-001',
    ]);

    return new CreateShipmentResponse($request, $data);
}

it('parses successful response', function () {
    $response = createShipmentResponseWith([
        'Result' => '1',
        'Message' => 'Başarılı',
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getCode())->toBe('1')
        ->and($response->getMessage())->toBe('Başarılı');
});

it('returns tracking number from request order number', function () {
    $response = createShipmentResponseWith([
        'Result' => '1',
        'Message' => 'Başarılı',
    ]);

    expect($response->getTrackingNumber())->toBe('SIP-001')
        ->and($response->getShipmentId())->toBe('SIP-001');
});

it('returns barcode text from request', function () {
    $response = createShipmentResponseWith([
        'Result' => '1',
        'Message' => 'Başarılı',
    ]);

    expect($response->getBarcode())->toBe('BARK-001');
});

it('returns order number as barcode when no barcode text set', function () {
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-002',
    ]);

    $response = new CreateShipmentResponse($request, [
        'Result' => '1',
        'Message' => 'Başarılı',
    ]);

    expect($response->getBarcode())->toBe('SIP-002');
});

it('parses error response', function () {
    $response = createShipmentResponseWith([
        'Result' => 'E003:Kullanıcı adı veya şifresi yanlış',
        'Message' => 'E003:Kullanıcı adı veya şifresi yanlış',
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('E003:Kullanıcı adı veya şifresi yanlış')
        ->and($response->getMessage())->toContain('E003');
});

it('returns null for label', function () {
    $response = createShipmentResponseWith(['Result' => '1', 'Message' => 'OK']);

    expect($response->getLabel())->toBeNull();
});

it('returns null for total charge and currency', function () {
    $response = createShipmentResponseWith(['Result' => '1', 'Message' => 'OK']);

    expect($response->getTotalCharge())->toBeNull()
        ->and($response->getCurrency())->toBeNull();
});

it('provides access to raw data', function () {
    $data = ['Result' => '1', 'Message' => 'Başarılı'];
    $response = createShipmentResponseWith($data);

    expect($response->getData())->toBe($data);
});
