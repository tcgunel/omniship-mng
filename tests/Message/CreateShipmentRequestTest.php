<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Package;
use Omniship\MNG\Message\CreateShipmentRequest;
use Omniship\MNG\Message\CreateShipmentResponse;

use function Omniship\MNG\Tests\createMockHttpClient;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;

function createSiparisKayitSuccessXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<SiparisKayit_C2CResponse xmlns="http://tempuri.org/">'
        . '<SiparisKayit_C2CResult>1</SiparisKayit_C2CResult>'
        . '</SiparisKayit_C2CResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createSiparisKayitErrorXml(string $error = 'E003:Kullanıcı adı veya şifresi yanlış'): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
        . '<soap:Body>'
        . '<SiparisKayit_C2CResponse xmlns="http://tempuri.org/">'
        . '<SiparisKayit_C2CResult>' . $error . '</SiparisKayit_C2CResult>'
        . '</SiparisKayit_C2CResponse>'
        . '</soap:Body>'
        . '</soap:Envelope>';
}

function createShipmentRequest(string $responseXml = ''): CreateShipmentRequest
{
    if ($responseXml === '') {
        $responseXml = createSiparisKayitSuccessXml();
    }

    $request = new CreateShipmentRequest(
        createMockHttpClient($responseXml),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    return $request;
}

it('builds correct data from shipment parameters', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '1234567890',
        'password' => 'ABCDEF123',
        'orderNumber' => 'SIP-001',
        'testMode' => true,
        'shipFrom' => new Address(
            name: 'Gönderici Adı',
            street1: 'Atatürk Cad. No:42',
            city: 'İstanbul',
            district: 'Kadıköy',
            phone: '+905551234567',
        ),
        'shipTo' => new Address(
            name: 'Alıcı Adı',
            street1: 'Kızılay Mah. 123. Sok. No:5',
            city: 'Ankara',
            district: 'Çankaya',
            phone: '+905559876543',
        ),
        'packages' => [
            new Package(weight: 3.0, desi: 2.0, quantity: 1, description: 'Kitap'),
        ],
    ]);

    $data = $request->getData();

    expect($data['pKullaniciAdi'])->toBe('1234567890')
        ->and($data['PSifre'])->toBe('ABCDEF123')
        ->and($data['pSiparisNo'])->toBe('SIP-001')
        ->and($data['pBarkodText'])->toBe('SIP-001')
        ->and($data['pGonMusteriAdi'])->toBe('Gönderici Adı')
        ->and($data['pGonIlAdi'])->toBe('İSTANBUL')
        ->and($data['pGonIlceAdi'])->toBe('KADIKÖY')
        ->and($data['pGonAdresText'])->toBe('Atatürk Cad. No:42')
        ->and($data['pGonTelCep'])->toBe('5551234567')
        ->and($data['pAliciMusteriAdi'])->toBe('Alıcı Adı')
        ->and($data['pAliciIlAdi'])->toBe('ANKARA')
        ->and($data['pAliciilceAdi'])->toBe('ÇANKAYA')
        ->and($data['pAliciAdresText'])->toBe('Kızılay Mah. 123. Sok. No:5')
        ->and($data['pAliciTelCep'])->toBe('5559876543')
        ->and($data['pOdemeSekli'])->toBe('Gonderici_Odeyecek')
        ->and($data['pKapidaTahsilat'])->toBe('Mal_Bedeli_Tahsil_Edilmesin')
        ->and($data['pAciklama'])->toBe('Kitap');
});

it('builds package list correctly', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-002',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
        'packages' => [
            new Package(weight: 5.0, desi: 3.0, quantity: 2, description: 'Elektronik'),
            new Package(weight: 1.0, desi: 1.0, quantity: 1, description: 'Aksesuar'),
        ],
    ]);

    $data = $request->getData();
    $parcaList = $data['pGonderiParcaList'];

    expect($parcaList)->toHaveCount(2)
        ->and($parcaList[0]['Kg'])->toBe(5)
        ->and($parcaList[0]['Desi'])->toBe(3)
        ->and($parcaList[0]['Adet'])->toBe(2)
        ->and($parcaList[0]['Icerik'])->toBe('Elektronik')
        ->and($parcaList[1]['Kg'])->toBe(1)
        ->and($parcaList[1]['Desi'])->toBe(1)
        ->and($parcaList[1]['Adet'])->toBe(1)
        ->and($parcaList[1]['Icerik'])->toBe('Aksesuar');
});

it('defaults to empty package when no packages provided', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-003',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
    ]);

    $data = $request->getData();
    $parcaList = $data['pGonderiParcaList'];

    expect($parcaList)->toHaveCount(1)
        ->and($parcaList[0]['Kg'])->toBe(0)
        ->and($parcaList[0]['Adet'])->toBe(1);
});

it('maps receiver payment type correctly', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-004',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
        'paymentType' => PaymentType::RECEIVER,
    ]);

    $data = $request->getData();

    expect($data['pOdemeSekli'])->toBe('Alici_Odeyecek');
});

it('maps third party payment type to platform', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-005',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
        'paymentType' => PaymentType::THIRD_PARTY,
    ]);

    $data = $request->getData();

    expect($data['pOdemeSekli'])->toBe('Platform_Odeyecek');
});

it('enables COD with amount', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-006',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
        'cashOnDelivery' => true,
        'codAmount' => 150.50,
    ]);

    $data = $request->getData();

    expect($data['pKapidaTahsilat'])->toBe('Mal_Bedeli_Tahsil_Edilsin')
        ->and($data['pUrunBedeli'])->toBe('150.5');
});

it('uses custom barcode text when provided', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-007',
        'barcodeText' => 'BARKOD123',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
    ]);

    $data = $request->getData();

    expect($data['pBarkodText'])->toBe('BARKOD123');
});

it('sends request and returns successful response', function () {
    $request = createShipmentRequest(createSiparisKayitSuccessXml());
    $request->initialize([
        'username' => '1234567890',
        'password' => 'ABCDEF123',
        'orderNumber' => 'SIP-001',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Kadıköy', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(CreateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getTrackingNumber())->toBe('SIP-001')
        ->and($response->getShipmentId())->toBe('SIP-001')
        ->and($response->getBarcode())->toBe('SIP-001');
});

it('sends request and returns error response', function () {
    $request = createShipmentRequest(createSiparisKayitErrorXml());
    $request->initialize([
        'username' => 'wrong',
        'password' => 'wrong',
        'orderNumber' => 'SIP-ERR',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Kadıköy', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toContain('Kullanıcı adı veya şifresi yanlış');
});

it('throws exception when required parameters are missing', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        // Missing orderNumber, shipFrom, shipTo
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

it('strips country code from phone numbers', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-PHONE',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr', phone: '+90 555 123 4567'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr', phone: '05559876543'),
    ]);

    $data = $request->getData();

    expect($data['pGonTelCep'])->toBe('5551234567')
        ->and($data['pAliciTelCep'])->toBe('5559876543');
});

it('converts city and district to uppercase Turkish', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-UPPER',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'istanbul', district: 'şişli', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'izmir', district: 'güzelbahçe', street1: 'Addr'),
    ]);

    $data = $request->getData();

    expect($data['pGonIlAdi'])->toBe('İSTANBUL')
        ->and($data['pGonIlceAdi'])->toBe('ŞİŞLİ')
        ->and($data['pAliciIlAdi'])->toBe('İZMİR')
        ->and($data['pAliciilceAdi'])->toBe('GÜZELBAHÇE');
});

it('calculates desi from dimensions when not explicit', function () {
    $request = createShipmentRequest();
    $request->initialize([
        'username' => '123',
        'password' => 'abc',
        'orderNumber' => 'SIP-DESI',
        'testMode' => true,
        'shipFrom' => new Address(name: 'Sender', city: 'İstanbul', district: 'Beşiktaş', street1: 'Addr'),
        'shipTo' => new Address(name: 'Receiver', city: 'Ankara', district: 'Çankaya', street1: 'Addr'),
        'packages' => [
            new Package(weight: 2.0, length: 30.0, width: 20.0, height: 15.0),
        ],
    ]);

    $data = $request->getData();
    $parcaList = $data['pGonderiParcaList'];

    // Desi = 30 * 20 * 15 / 3000 = 3
    expect($parcaList[0]['Desi'])->toBe(3);
});
