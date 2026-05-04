<?php

declare(strict_types=1);

use Omniship\MNG\Message\CancelShipmentRequest;
use Omniship\MNG\Message\CancelShipmentResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

function tokenSuccess(): array
{
    return [
        'body' => json_encode(['jwt' => 'tok'], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildCancelRequest(array $responses, array &$captured = []): CancelShipmentRequest
{
    return new CancelShipmentRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function defaultCancelParams(): array
{
    return [
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'referenceId' => 'siparis-001',
    ];
}

it('issues PUT to /cancelorder with uppercased referenceId in path', function () {
    $captured = [];
    $request = buildCancelRequest(
        [tokenSuccess(), ['body' => '"OK"', 'status' => 200]],
        $captured,
    );
    $request->initialize(defaultCancelParams());

    $response = $request->send();

    expect($response)->toBeInstanceOf(CancelShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue();

    $cancelReq = $captured[1];

    expect($cancelReq->getMethod())->toBe('PUT')
        ->and($cancelReq->getUri()->getPath())->toEndWith('/cancelorder/SIPARIS-001')
        ->and($cancelReq->getHeaderLine('Authorization'))->toBe('Bearer tok');
});

it('marks failed cancel as not successful with status code as code', function () {
    $request = buildCancelRequest([
        tokenSuccess(),
        ['body' => json_encode(['title' => 'Not Found']), 'status' => 404],
    ]);
    $request->initialize(defaultCancelParams());

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getCode())->toBe('404')
        ->and($response->getMessage())->toBe('Not Found');
});

it('throws when referenceId is missing', function () {
    $request = buildCancelRequest([tokenSuccess(), ['body' => '"OK"', 'status' => 200]]);
    $request->initialize([
        'clientId' => 'x',
        'clientSecret' => 'x',
        'customerNumber' => 'x',
        'password' => 'x',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
