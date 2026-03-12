<?php

declare(strict_types=1);

use Omniship\MNG\Message\GetTrackingStatusRequest;
use Omniship\MNG\Message\GetTrackingStatusResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createTrackingSuccessXml(int $status = 5, string $statusDescription = 'Teslim Edildi'): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<GelecekIadeSiparisKontrolResponse xmlns="http://tempuri.org/">'
        . '<GelecekIadeSiparisKontrolResult>'
        . '<xs:schema id="NewDataSet" xmlns="" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata">'
        . '<xs:element name="NewDataSet" msdata:IsDataSet="true"></xs:element>'
        . '</xs:schema>'
        . '<diffgr:diffgram xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1">'
        . '<NewDataSet xmlns="">'
        . '<Table1 diffgr:id="Table11" msdata:rowOrder="0">'
        . '<SIPARIS_KAYIT_TARIHI>2025-03-10T00:00:00+03:00</SIPARIS_KAYIT_TARIHI>'
        . '<SIPARIS_KODU>SIP-001</SIPARIS_KODU>'
        . '<GONDERICI_MUSTERI>TEST MÜŞTERI</GONDERICI_MUSTERI>'
        . '<GONDERI_NO>89472001590</GONDERI_NO>'
        . '<SIPARIS_STATU>' . $status . '</SIPARIS_STATU>'
        . '<SIPARIS_STATU_ACIKLAMA>' . $statusDescription . '</SIPARIS_STATU_ACIKLAMA>'
        . '<KARGO_TAKIP_URL>http://service.mngkargo.com.tr/biactive/takip222.asp?a=g&amp;b=FV&amp;c=925515</KARGO_TAKIP_URL>'
        . '<SIPARIS_STATU_TARIHI>10.03.2025 14:30:00</SIPARIS_STATU_TARIHI>'
        . '</Table1>'
        . '</NewDataSet>'
        . '</diffgr:diffgram>'
        . '</GelecekIadeSiparisKontrolResult>'
        . '</GelecekIadeSiparisKontrolResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createTrackingDeliveredXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<GelecekIadeSiparisKontrolResponse xmlns="http://tempuri.org/">'
        . '<GelecekIadeSiparisKontrolResult>'
        . '<xs:schema id="NewDataSet" xmlns="" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata">'
        . '<xs:element name="NewDataSet" msdata:IsDataSet="true"></xs:element>'
        . '</xs:schema>'
        . '<diffgr:diffgram xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1">'
        . '<NewDataSet xmlns="">'
        . '<Table1 diffgr:id="Table11" msdata:rowOrder="0">'
        . '<SIPARIS_KAYIT_TARIHI>2025-03-10T00:00:00+03:00</SIPARIS_KAYIT_TARIHI>'
        . '<SIPARIS_KODU>SIP-001</SIPARIS_KODU>'
        . '<GONDERICI_MUSTERI>TEST MÜŞTERI</GONDERICI_MUSTERI>'
        . '<GONDERI_NO>89472001590</GONDERI_NO>'
        . '<TESLIM_TARIHI>2025-03-12T00:00:00+03:00</TESLIM_TARIHI>'
        . '<TESLIM_ALAN_ADSOYAD>MEHMET DEMİR</TESLIM_ALAN_ADSOYAD>'
        . '<KARGO_SON_HAREKET>ALICIYA TESLİM EDİLDİ (12/03/2025)</KARGO_SON_HAREKET>'
        . '<SIPARIS_STATU>5</SIPARIS_STATU>'
        . '<SIPARIS_STATU_ACIKLAMA>Teslim Edildi</SIPARIS_STATU_ACIKLAMA>'
        . '<KARGO_TAKIP_URL>http://service.mngkargo.com.tr/biactive/takip222.asp?a=g&amp;b=FV&amp;c=925515</KARGO_TAKIP_URL>'
        . '<SIPARIS_STATU_TARIHI>12.03.2025 16:28:37</SIPARIS_STATU_TARIHI>'
        . '</Table1>'
        . '</NewDataSet>'
        . '</diffgr:diffgram>'
        . '</GelecekIadeSiparisKontrolResult>'
        . '</GelecekIadeSiparisKontrolResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createTrackingEmptyXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<GelecekIadeSiparisKontrolResponse xmlns="http://tempuri.org/">'
        . '<GelecekIadeSiparisKontrolResult>'
        . '<xs:schema id="NewDataSet" xmlns="" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata">'
        . '<xs:element name="NewDataSet" msdata:IsDataSet="true"></xs:element>'
        . '</xs:schema>'
        . '<diffgr:diffgram xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1" />'
        . '</GelecekIadeSiparisKontrolResult>'
        . '</GelecekIadeSiparisKontrolResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createTrackingRequest(string $responseXml): GetTrackingStatusRequest
{
    $request = new GetTrackingStatusRequest(
        createMockHttpClient($responseXml),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    return $request;
}

it('builds correct tracking data', function () {
    $request = createTrackingRequest(createTrackingSuccessXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'trackingNumber' => 'SIP-001',
    ]);

    $data = $request->getData();

    expect($data['pRfSipGnMusteriNo'])->toBe('123')
        ->and($data['pRfSipGnMusteriSifre'])->toBe('abc')
        ->and($data['pCHSiparisNo'])->toBe('SIP-001')
        ->and($data['pCHBarkod'])->toBe('')
        ->and($data['pCHFaturaSeri'])->toBe('')
        ->and($data['pCHFaturaNo'])->toBe('');
});

it('sends request and returns delivered response', function () {
    $request = createTrackingRequest(createTrackingDeliveredXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'trackingNumber' => 'SIP-001',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class)
        ->and($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('89472001590')
        ->and($info->status)->toBe(\Omniship\Common\Enum\ShipmentStatus::DELIVERED)
        ->and($info->carrier)->toBe('MNG Kargo')
        ->and($info->signedBy)->toBe('MEHMET DEMİR')
        ->and($info->events)->toHaveCount(1)
        ->and($info->events[0]->description)->toBe('Teslim Edildi');
});

it('sends request and returns in-transit response', function () {
    $request = createTrackingRequest(createTrackingSuccessXml(2, 'Transfer Aşamasında'));
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'trackingNumber' => 'SIP-001',
    ]);

    $response = $request->send();
    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(\Omniship\Common\Enum\ShipmentStatus::IN_TRANSIT)
        ->and($info->events[0]->description)->toBe('Transfer Aşamasında');
});

it('handles empty response with no shipment data', function () {
    $request = createTrackingRequest(createTrackingEmptyXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'trackingNumber' => 'SIP-NOTFOUND',
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('SIP-NOTFOUND')
        ->and($info->status)->toBe(\Omniship\Common\Enum\ShipmentStatus::UNKNOWN)
        ->and($info->events)->toBeEmpty();
});

it('throws exception when tracking number is missing', function () {
    $request = createTrackingRequest(createTrackingSuccessXml());
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
