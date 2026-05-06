<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\MNG\Message\GetTrackingStatusRequest;
use Omniship\MNG\Message\GetTrackingStatusResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

function trackingTokenResponse(): array
{
    return ['body' => json_encode(['jwt' => 'tok'], JSON_THROW_ON_ERROR), 'status' => 200];
}

function eventsResponse(): array
{
    $events = [
        [
            'referenceId' => 'SIPARIS-001',
            'eventSequence' => '1',
            'eventStatus' => 'Gönderi Hazırlandı',
            'eventStatusEn' => 'Shipment Created',
            'eventDateTime' => '12-02-2019 20:30:45',
            'eventDateTime2' => '2019-02-12 20:30:45',
            'location' => 'Atalar Şube',
            'country' => 'TR',
            'locationAddress' => 'İSTANBUL',
        ],
        [
            'referenceId' => 'SIPARIS-001',
            'eventSequence' => '2',
            'eventStatus' => 'Teslim Edildi',
            'eventStatusEn' => 'Delivered',
            'eventDateTime' => '13-02-2019 14:56:00',
            'eventDateTime2' => '2019-02-13 14:56:00',
            'location' => 'Adana Şube',
            'country' => 'TR',
            'locationAddress' => 'ADANA',
        ],
    ];

    return ['body' => json_encode($events, JSON_THROW_ON_ERROR), 'status' => 200];
}

function statusResponse(): array
{
    return [
        'body' => json_encode([
            'shipmentStatusCode' => 5,
            'orderId' => '54321',
            'referenceId' => 'SIPARIS-001',
            'shipmentId' => '14556546',
            'shipmentStatus' => 'Teslim_Edildi',
            'isDelivered' => 1,
            'deliveryTo' => 'Sema Kudu',
            'trackingUrl' => 'https://www.mngkargo.com.tr/track/14556546',
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildTrackingRequest(array $responses, array &$captured = []): GetTrackingStatusRequest
{
    return new GetTrackingStatusRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('combines events + headline status into TrackingInfo', function () {
    $captured = [];
    $request = buildTrackingRequest(
        [trackingTokenResponse(), eventsResponse(), trackingTokenResponse(), statusResponse()],
        $captured,
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'referenceId' => 'siparis-001',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class)
        ->and($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('14556546')
        ->and($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->signedBy)->toBe('Sema Kudu')
        ->and($info->events)->toHaveCount(2)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->events[0]->description)->toBe('Shipment Created')
        ->and($info->events[1]->status)->toBe(ShipmentStatus::DELIVERED);

    expect($response->getTrackingUrl())->toBe('https://www.mngkargo.com.tr/track/14556546');
});

it('hits trackshipment with referenceId in path', function () {
    $captured = [];
    $request = buildTrackingRequest(
        [trackingTokenResponse(), eventsResponse(), trackingTokenResponse(), statusResponse()],
        $captured,
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'referenceId' => 'abc-123',
    ]);

    $request->send();

    $eventsReq = $captured[1];
    $statusReq = $captured[3];

    expect($eventsReq->getUri()->getPath())->toEndWith('/trackshipment/ABC-123')
        ->and($statusReq->getUri()->getPath())->toEndWith('/getshipmentstatus/ABC-123');
});

it('falls back to trackingNumber when referenceId is absent', function () {
    $captured = [];
    $request = buildTrackingRequest(
        [trackingTokenResponse(), eventsResponse(), trackingTokenResponse(), statusResponse()],
        $captured,
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'trackingNumber' => 'fallback-ref',
    ]);

    $request->send();

    expect($captured[1]->getUri()->getPath())->toEndWith('/trackshipment/FALLBACK-REF');
});

it('uses ByShipmentId paths when the value is all-digit (MNG shipmentId)', function () {
    $captured = [];
    $request = buildTrackingRequest(
        [trackingTokenResponse(), eventsResponse(), trackingTokenResponse(), statusResponse()],
        $captured,
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'trackingNumber' => '614118757013', // shipmentId from MNG
    ]);

    $request->send();

    expect($captured[1]->getUri()->getPath())->toEndWith('/trackshipmentByShipmentId/614118757013')
        ->and($captured[3]->getUri()->getPath())->toEndWith('/getshipmentstatusByShipmentId/614118757013');
});

it('throws when neither referenceId nor trackingNumber provided', function () {
    $request = buildTrackingRequest([trackingTokenResponse()]);
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

it('maps MNG status codes to ShipmentStatus enum', function () {
    expect(GetTrackingStatusResponse::mapStatus(1))->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and(GetTrackingStatusResponse::mapStatus(2))->toBe(ShipmentStatus::IN_TRANSIT)
        ->and(GetTrackingStatusResponse::mapStatus(4))->toBe(ShipmentStatus::OUT_FOR_DELIVERY)
        ->and(GetTrackingStatusResponse::mapStatus(5))->toBe(ShipmentStatus::DELIVERED)
        ->and(GetTrackingStatusResponse::mapStatus(7))->toBe(ShipmentStatus::RETURNED)
        ->and(GetTrackingStatusResponse::mapStatus(99))->toBe(ShipmentStatus::UNKNOWN);
});
