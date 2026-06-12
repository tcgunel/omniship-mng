<?php

declare(strict_types=1);

use Omniship\MNG\Message\CancelOrderRequest;
use Omniship\MNG\Message\CancelOrderResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

function cancelOrderTokenSuccess(): array
{
    return [
        'body' => json_encode(['jwt' => 'tok'], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildCancelOrderRequest(array $responses, array &$captured = []): CancelOrderRequest
{
    return new CancelOrderRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('issues PUT to /standardcmdapi/cancelorder/{referenceId} with no body', function () {
    $captured = [];
    $request = buildCancelOrderRequest(
        [cancelOrderTokenSuccess(), ['body' => '"OK"', 'status' => 200]],
        $captured,
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'referenceId' => 'omn-6a25b4f035207',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CancelOrderResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();

    $cancelReq = $captured[1];

    expect($cancelReq->getMethod())->toBe('PUT')
        ->and($cancelReq->getUri()->getPath())->toEndWith('/standardcmdapi/cancelorder/OMN-6A25B4F035207')
        ->and($cancelReq->getHeaderLine('Authorization'))->toBe('Bearer tok')
        ->and((string) $cancelReq->getBody())->toBe('');
});

it('marks failed cancelorder as not successful with the error message', function () {
    $request = buildCancelOrderRequest([
        cancelOrderTokenSuccess(),
        ['body' => json_encode(['title' => 'Not Found']), 'status' => 404],
    ]);
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'referenceId' => 'SIPARIS-1',
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getCode())->toBe('404')
        ->and($response->getMessage())->toBe('Not Found');
});

it('throws when referenceId is missing for cancelorder', function () {
    $request = buildCancelOrderRequest([cancelOrderTokenSuccess()]);
    $request->initialize([
        'clientId' => 'x',
        'clientSecret' => 'x',
        'customerNumber' => 'x',
        'password' => 'x',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
