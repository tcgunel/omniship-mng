<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\MNG\Message\CreateRecipientRequest;
use Omniship\MNG\Message\CreateRecipientResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

function recipientTokenResponse(): array
{
    return ['body' => json_encode(['jwt' => 'tok'], JSON_THROW_ON_ERROR), 'status' => 200];
}

function recipientSuccessResponse(): array
{
    return [
        'body' => json_encode([
            'orderInvoiceId' => '456764543',
            'orderInvoiceDetailId' => '25423565',
            'shipperBranchCode' => '1345',
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildCreateRecipientRequest(array $responses, array &$captured = []): CreateRecipientRequest
{
    return new CreateRecipientRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function defaultRecipientParams(): array
{
    return [
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => '2951471412',
        'password' => 'pwd',
        'testMode' => true,
        'shipTo' => new Address(
            name: 'Ahmet Yılmaz',
            street1: 'Test sokak',
            city: 'Adana',
            district: 'Seyhan',
            phone: '+905551234567',
            email: 'a@b.com',
        ),
        'recipientCityCode' => 1,
        'recipientDistrictCode' => 100,
        'recipientTaxNumber' => '12345678901',
    ];
}

it('posts recipient payload with city/district codes and tax number', function () {
    $captured = [];
    $request = buildCreateRecipientRequest(
        [recipientTokenResponse(), recipientSuccessResponse()],
        $captured,
    );
    $request->initialize(defaultRecipientParams());

    $data = $request->getData();

    expect($data['recipient']['cityCode'])->toBe(1)
        ->and($data['recipient']['districtCode'])->toBe(100)
        ->and($data['recipient']['fullName'])->toBe('Ahmet Yılmaz')
        ->and($data['recipient']['mobilePhoneNumber'])->toBe('5551234567')
        ->and($data['recipient']['taxNumber'])->toBe('12345678901')
        ->and($data['recipient']['taxOffice'])->toBe('SAHIS')
        ->and($data['recipient']['email'])->toBe('a@b.com');
});

it('hits /pluscmdapi/createRecipient and returns success', function () {
    $captured = [];
    $request = buildCreateRecipientRequest(
        [recipientTokenResponse(), recipientSuccessResponse()],
        $captured,
    );
    $request->initialize(defaultRecipientParams());

    $response = $request->send();

    expect($response)->toBeInstanceOf(CreateRecipientResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getShipperBranchCode())->toBe('1345');

    $createReq = $captured[1];
    expect($createReq->getMethod())->toBe('POST')
        ->and($createReq->getUri()->getPath())->toEndWith('/pluscmdapi/createRecipient')
        ->and($createReq->getHeaderLine('Authorization'))->toBe('Bearer tok')
        ->and($createReq->getHeaderLine('X-IBM-Client-Id'))->toBe('cid');
});

it('surfaces MNG error description from the new error shape', function () {
    $request = buildCreateRecipientRequest([
        recipientTokenResponse(),
        [
            'body' => json_encode([
                'error' => [
                    'code' => '26056',
                    'message' => 'Bad Request',
                    'description' => "'Recipient. Tax Number', 11 karakterli TC Kimilk numarasi olmalidir.",
                ],
            ]),
            'status' => 400,
        ],
    ]);
    $request->initialize(defaultRecipientParams());

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('400')
        ->and($response->getMessage())->toBe(
            "'Recipient. Tax Number', 11 karakterli TC Kimilk numarasi olmalidir.",
        );
});

it('throws when shipTo is missing', function () {
    $request = buildCreateRecipientRequest([recipientTokenResponse(), recipientSuccessResponse()]);
    $request->initialize([
        'clientId' => 'x',
        'clientSecret' => 'x',
        'customerNumber' => 'x',
        'password' => 'x',
        'recipientCityCode' => 1,
        'recipientDistrictCode' => 100,
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
