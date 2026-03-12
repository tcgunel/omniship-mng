<?php

declare(strict_types=1);

use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CancelShipmentResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createCancelResponseWith(array $data): CancelShipmentResponse
{
    $request = new CancelShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-001',
    ]);

    return new CancelShipmentResponse($request, $data);
}

it('parses successful cancellation', function () {
    $response = createCancelResponseWith([
        'Result' => '1',
        'Message' => 'Başarılı',
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue()
        ->and($response->getCode())->toBe('1')
        ->and($response->getMessage())->toBe('Başarılı');
});

it('parses failed cancellation', function () {
    $response = createCancelResponseWith([
        'Result' => '0',
        'Message' => '0',
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getCode())->toBe('0');
});

it('provides access to raw data', function () {
    $data = ['Result' => '1', 'Message' => 'Başarılı'];
    $response = createCancelResponseWith($data);

    expect($response->getData())->toBe($data);
});
