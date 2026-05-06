<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Package;
use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\CreateShipmentResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

function tokenResponse(string $jwt = 'test.jwt.token'): array
{
    return [
        'body' => json_encode([
            'jwt' => $jwt,
            'refreshToken' => 'rt-1',
            'jwtExpireDate' => '01.01.2099 00:00:00',
            'refreshTokenExpireDate' => '01.01.2099 00:00:00',
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function createOrderSuccess(): array
{
    return [
        'body' => json_encode([
            'orderInvoiceId' => '456764543',
            'orderInvoiceDetailId' => '25423565',
            'shipperBranchCode' => '1345',
            'referenceId' => 'SIPARIS-001',
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function createBarcodeSuccess(): array
{
    return [
        'body' => json_encode([
            'referenceId' => 'SIPARIS-001',
            'invoiceId' => '564645774',
            'shipmentId' => '4536457657',
            'barcodes' => [
                ['pieceNumber' => 1, 'value' => '^XA^FT...^XZ', 'barcode' => 'BARCODE-001'],
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildCreateShipmentRequest(array $responses, array &$captured = []): CreateShipmentRequest
{
    return new CreateShipmentRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function defaultShipmentParams(): array
{
    return [
        'clientId' => 'client-id',
        'clientSecret' => 'client-secret',
        'customerNumber' => '2951471412',
        'password' => 'pwd',
        'testMode' => true,
        'referenceId' => 'siparis-001',
        'shipTo' => new Address(
            name: 'Test Recipient',
            street1: 'Test address',
            city: 'Adana',
            district: 'Seyhan',
            phone: '+905551234567',
        ),
        'recipientCityCode' => 1,
        'recipientDistrictCode' => 100,
        'shipmentServiceType' => 1,
        'packagingType' => 3,
        'deliveryType' => 1,
        'content' => 'TEST',
        'description' => 'desc',
        'paymentType' => PaymentType::SENDER,
        'packages' => [new Package(weight: 2.0, desi: 1.0, quantity: 1, description: 'item')],
    ];
}

it('builds order payload with uppercased referenceId, recipient codes and SMS toggles', function () {
    $captured = [];
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
        $captured,
    );
    $request->initialize(defaultShipmentParams());

    $data = $request->getData();

    expect($data['order']['referenceId'])->toBe('SIPARIS-001')
        ->and($data['order']['barcode'])->toBe('SIPARIS-001')
        ->and($data['order']['paymentType'])->toBe(1)
        ->and($data['order']['shipmentServiceType'])->toBe(1)
        ->and($data['order']['packagingType'])->toBe(3)
        ->and($data['order']['smsPreference1'])->toBe(0)
        ->and($data['recipient']['cityCode'])->toBe(1)
        ->and($data['recipient']['districtCode'])->toBe(100)
        ->and($data['recipient']['fullName'])->toBe('Test Recipient')
        ->and($data['recipient']['mobilePhoneNumber'])->toBe('5551234567')
        ->and($data['orderPieceList'])->toHaveCount(1)
        ->and($data['orderPieceList'][0]['barcode'])->toBe('SIPARIS-001_PARCA1')
        ->and($data['orderPieceList'][0]['kg'])->toBe(2)
        ->and($data['orderPieceList'][0]['desi'])->toBe(1);
});

it('sends two-step createOrder + createBarcode and exposes shipmentId/barcode', function () {
    $captured = [];
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
        $captured,
    );
    $request->initialize(defaultShipmentParams());

    $response = $request->send();

    expect($response)->toBeInstanceOf(CreateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('4536457657')
        ->and($response->getTrackingNumber())->toBe('4536457657')
        ->and($response->getBarcode())->toBe('BARCODE-001')
        ->and($response->getInvoiceId())->toBe('564645774');

    // 4 HTTP calls: token → createOrder → token → createBarcode
    expect(count($captured))->toBe(4);

    $tokenReq = $captured[0];
    $orderReq = $captured[1];
    $barcodeReq = $captured[3];

    expect($tokenReq->getMethod())->toBe('POST')
        ->and($tokenReq->getUri()->getPath())->toContain('/mngapi/api/token')
        ->and($orderReq->getMethod())->toBe('POST')
        ->and($orderReq->getUri()->getPath())->toContain('/standardcmdapi/createOrder')
        ->and($orderReq->getHeaderLine('Authorization'))->toBe('Bearer test.jwt.token')
        ->and($orderReq->getHeaderLine('X-IBM-Client-Id'))->toBe('client-id')
        ->and($barcodeReq->getMethod())->toBe('POST')
        ->and($barcodeReq->getUri()->getPath())->toContain('/barcodecmdapi/createbarcode');
});

it('marks response as not successful when createOrder fails', function () {
    $captured = [];
    $request = buildCreateShipmentRequest(
        [
            tokenResponse(),
            ['body' => json_encode(['title' => 'Bad Request', 'detail' => 'invalid']), 'status' => 400],
        ],
        $captured,
    );
    $request->initialize(defaultShipmentParams());

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('400')
        ->and($response->getMessage())->toBe('invalid');
});

it('hits test endpoint when testMode is true and prod when false', function () {
    $captured = [];
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
        $captured,
    );
    $request->initialize(array_merge(defaultShipmentParams(), ['testMode' => false]));

    $request->send();

    foreach ($captured as $req) {
        expect((string) $req->getUri())->toStartWith('https://api.mngkargo.com.tr');
    }
});

it('throws when required parameters are missing', function () {
    $request = buildCreateShipmentRequest([tokenResponse(), createOrderSuccess()]);
    $request->initialize([
        'clientId' => 'x',
        'clientSecret' => 'x',
        'customerNumber' => 'x',
        'password' => 'x',
        // missing referenceId, shipTo, codes
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

it('builds piece list with one entry per package quantity', function () {
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
    );
    $request->initialize(array_merge(defaultShipmentParams(), [
        'packages' => [
            new Package(weight: 1.0, desi: 1.0, quantity: 3, description: 'three'),
        ],
    ]));

    $data = $request->getData();

    expect($data['orderPieceList'])->toHaveCount(3)
        ->and($data['orderPieceList'][0]['barcode'])->toBe('SIPARIS-001_PARCA1')
        ->and($data['orderPieceList'][2]['barcode'])->toBe('SIPARIS-001_PARCA3');
});

it('maps PaymentType::RECEIVER to 2', function () {
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
    );
    $request->initialize(array_merge(defaultShipmentParams(), ['paymentType' => PaymentType::RECEIVER]));

    expect($request->getData()['order']['paymentType'])->toBe(2);
});

it('retries createBarcode when MNG returns 20001 VARIŞ ŞUBESİ BULUNAMADI', function () {
    $branchNotResolvedError = [
        'body' => json_encode([
            'error' => [
                'Code' => '20001',
                'Message' => 'Error',
                'Description' => "<WERR> OMN-XYZ NO'LU SİPARİŞ KAYDI İÇİN VARIŞ ŞUBESİ BULUNAMADI ! DAHA SONRA TEKRAR DENEYİN ! </WERR>",
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 500,
    ];

    // Subclass that skips the actual sleep so the test runs instantly
    $request = new class (
        \Omniship\MNG\Tests\createSequencedMockHttpClient([
            tokenResponse(),                // for createOrder
            createOrderSuccess(),
            tokenResponse(),                // for first createBarcode attempt (token is re-fetched in two-step)
            $branchNotResolvedError,        // retry 1 — fails
            $branchNotResolvedError,        // retry 2 — fails
            createBarcodeSuccess(),         // retry 3 — succeeds
        ]),
        \Omniship\MNG\Tests\createMockRequestFactory(),
        \Omniship\MNG\Tests\createMockStreamFactory(),
    ) extends \Omniship\MNG\Message\CreateShipmentRequest {
        public int $sleeps = 0;
        protected function sleep(int $seconds): void
        {
            $this->sleeps++;
        }
    };

    $request->initialize(defaultShipmentParams());

    $response = $request->send();

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('4536457657')
        ->and($request->sleeps)->toBe(2); // two retries, two sleeps
});

it('gives up after exhausting retries and surfaces the original 20001 error', function () {
    $branchNotResolvedError = [
        'body' => json_encode([
            'error' => [
                'Code' => '20001',
                'Description' => 'VARIŞ ŞUBESİ BULUNAMADI',
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 500,
    ];

    $request = new class (
        \Omniship\MNG\Tests\createSequencedMockHttpClient([
            tokenResponse(),
            createOrderSuccess(),
            tokenResponse(),
            $branchNotResolvedError,
            $branchNotResolvedError,
            $branchNotResolvedError,
        ]),
        \Omniship\MNG\Tests\createMockRequestFactory(),
        \Omniship\MNG\Tests\createMockStreamFactory(),
    ) extends \Omniship\MNG\Message\CreateShipmentRequest {
        protected function sleep(int $seconds): void {}
    };
    $request->initialize(defaultShipmentParams());

    expect(fn () => $request->send())
        ->toThrow(\Omniship\Common\Exception\HttpException::class);
});

it('handles MNG array-wrapped responses (real-world shape)', function () {
    // MNG returns single-object responses wrapped in [{...}], not {...}
    // as the swagger documents. The response classes must unwrap.
    $orderArrayWrapped = [
        'body' => json_encode([[
            'orderInvoiceId' => '1707264',
            'orderInvoiceDetailId' => '1707777',
            'shipperBranchCode' => '03401700',
            'referenceId' => 'OMN-XYZ',
        ]], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
    $barcodeArrayWrapped = [
        'body' => json_encode([[
            'referenceId' => 'OMN-XYZ',
            'invoiceId' => 'FM378349',
            'shipmentId' => '614118757013',
            'barcodes' => [['pieceNumber' => 1, 'value' => '^XA...^XZ', 'barcode' => 'C@6B@H21FMLRPNAAA6J']],
        ]], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];

    $request = buildCreateShipmentRequest(
        [tokenResponse(), $orderArrayWrapped, tokenResponse(), $barcodeArrayWrapped],
    );
    $request->initialize(defaultShipmentParams());

    $response = $request->send();

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('614118757013')
        ->and($response->getTrackingNumber())->toBe('614118757013')
        ->and($response->getBarcode())->toBe('C@6B@H21FMLRPNAAA6J')
        ->and($response->getInvoiceId())->toBe('FM378349');

    $label = $response->getLabel();
    expect($label)->not->toBeNull()
        ->and($label->content)->toBe('^XA...^XZ')
        ->and($label->format->value)->toBe('ZPL');
});

it('sets isCOD=1 and codAmount when cashOnDelivery is true', function () {
    $request = buildCreateShipmentRequest(
        [tokenResponse(), createOrderSuccess(), tokenResponse(), createBarcodeSuccess()],
    );
    $request->initialize(array_merge(defaultShipmentParams(), [
        'cashOnDelivery' => true,
        'codAmount' => 250.5,
    ]));

    $data = $request->getData();

    expect($data['order']['isCOD'])->toBe(1)
        ->and($data['order']['codAmount'])->toBe(250.5);
});
