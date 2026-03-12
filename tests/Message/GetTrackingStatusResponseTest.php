<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\MNG\Message\GetTrackingStatusRequest;
use Omniship\MNG\Message\GetTrackingStatusResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createTrackingResponseWith(array $data): GetTrackingStatusResponse
{
    $request = new GetTrackingStatusRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'trackingNumber' => 'SIP-001',
    ]);

    return new GetTrackingStatusResponse($request, $data);
}

it('parses delivered shipment', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-001',
            'GONDERI_NO' => '89472001590',
            'SIPARIS_STATU' => '5',
            'SIPARIS_STATU_ACIKLAMA' => 'Teslim Edildi',
            'TESLIM_ALAN_ADSOYAD' => 'MEHMET DEMİR',
            'SIPARIS_STATU_TARIHI' => '12.03.2025 16:28:37',
        ],
    ]);

    expect($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('89472001590')
        ->and($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->signedBy)->toBe('MEHMET DEMİR')
        ->and($info->events)->toHaveCount(1)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::DELIVERED);
});

it('parses pre-transit shipment', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-002',
            'SIPARIS_STATU' => '0',
            'SIPARIS_STATU_ACIKLAMA' => 'Henüz işlem yapılmadı',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->signedBy)->toBeNull();
});

it('parses failure status with reason', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-003',
            'GONDERI_NO' => '99999',
            'SIPARIS_STATU' => '6',
            'SIPARIS_STATU_ACIKLAMA' => '[KOD] Teslim Edilemedi - Adres bulunamadı',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::FAILURE)
        ->and($info->events[0]->description)->toContain('Teslim Edilemedi');
});

it('parses returned shipment', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-004',
            'GONDERI_NO' => '88888',
            'SIPARIS_STATU' => '7',
            'SIPARIS_STATU_ACIKLAMA' => 'Göndericiye Teslim Edildi',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::RETURNED);
});

it('returns unknown status when no shipment data', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'No shipment data in response',
        'Shipment' => null,
    ]);

    expect($response->isSuccessful())->toBeFalse();

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::UNKNOWN)
        ->and($info->trackingNumber)->toBe('SIP-001')
        ->and($info->events)->toBeEmpty();
});

it('returns tracking URL', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-005',
            'SIPARIS_STATU' => '2',
            'SIPARIS_STATU_ACIKLAMA' => 'Transfer Aşamasında',
            'KARGO_TAKIP_URL' => 'http://service.mngkargo.com.tr/biactive/takip222.asp?a=g&b=FV&c=925515',
        ],
    ]);

    expect($response->getTrackingUrl())->toBe('http://service.mngkargo.com.tr/biactive/takip222.asp?a=g&b=FV&c=925515');
});

it('returns null tracking URL when not available', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-006',
            'SIPARIS_STATU' => '0',
            'SIPARIS_STATU_ACIKLAMA' => 'Henüz işlem yapılmadı',
        ],
    ]);

    expect($response->getTrackingUrl())->toBeNull();
});

it('uses SIPARIS_KODU as tracking number when GONDERI_NO absent', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-007',
            'SIPARIS_STATU' => '1',
            'SIPARIS_STATU_ACIKLAMA' => 'Sipariş Kargoya Verildi',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('SIP-007');
});

it('maps all status codes correctly', function () {
    expect(GetTrackingStatusResponse::mapStatus(0))->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and(GetTrackingStatusResponse::mapStatus(1))->toBe(ShipmentStatus::PICKED_UP)
        ->and(GetTrackingStatusResponse::mapStatus(2))->toBe(ShipmentStatus::IN_TRANSIT)
        ->and(GetTrackingStatusResponse::mapStatus(3))->toBe(ShipmentStatus::IN_TRANSIT)
        ->and(GetTrackingStatusResponse::mapStatus(4))->toBe(ShipmentStatus::OUT_FOR_DELIVERY)
        ->and(GetTrackingStatusResponse::mapStatus(5))->toBe(ShipmentStatus::DELIVERED)
        ->and(GetTrackingStatusResponse::mapStatus(6))->toBe(ShipmentStatus::FAILURE)
        ->and(GetTrackingStatusResponse::mapStatus(7))->toBe(ShipmentStatus::RETURNED)
        ->and(GetTrackingStatusResponse::mapStatus(99))->toBe(ShipmentStatus::UNKNOWN);
});

it('handles error response', function () {
    $response = createTrackingResponseWith([
        'Success' => false,
        'Message' => 'Auth failed',
        'Shipment' => null,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Auth failed');
});

it('trims empty signed by field', function () {
    $response = createTrackingResponseWith([
        'Success' => true,
        'Message' => 'OK',
        'Shipment' => [
            'SIPARIS_KODU' => 'SIP-008',
            'SIPARIS_STATU' => '5',
            'SIPARIS_STATU_ACIKLAMA' => 'Teslim Edildi',
            'TESLIM_ALAN_ADSOYAD' => '  ',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->signedBy)->toBeNull();
});
