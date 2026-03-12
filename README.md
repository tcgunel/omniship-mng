# Omniship MNG Kargo

MNG Kargo (now DHL eCommerce Turkey) carrier driver for the Omniship multi-carrier shipping library.

> **Note:** MNG Kargo was acquired by DHL Group in 2023 and rebranded as DHL eCommerce in Turkey (May 2025). The legacy SOAP API at `service.mngkargo.com.tr` is still operational.

## Installation

```bash
composer require tcgunel/omniship-mng
```

## Usage

```php
use Omniship\Omniship;
use Omniship\Common\Address;
use Omniship\Common\Package;

$mng = Omniship::create('MNG');
$mng->initialize([
    'username' => '1234567890',     // MNG müşteri numarası
    'password' => 'ABCDEF123',      // MNG anlaşma şifresi
    'testMode' => true,
]);
```

### Create Shipment

Uses the `SiparisKayit_C2C` SOAP method — the full C2C (Customer to Customer) endpoint for e-commerce platforms.

```php
$response = $mng->createShipment([
    'orderNumber' => 'SIP-001',         // pSiparisNo — unique, used for tracking
    'barcodeText' => 'SIP-001',         // pBarkodText — label barcode (defaults to orderNumber)
    'invoiceNumber' => 'IRS-001',       // pIrsaliyeNo (optional)
    'shipFrom' => new Address(
        name: 'Gönderici Müşteri',
        street1: 'Atatürk Cad. No:42',
        city: 'İstanbul',              // Uppercase dönüşümü otomatik
        district: 'Kadıköy',
        phone: '+905551234567',         // +90 ve başındaki 0 otomatik silinir
    ),
    'shipTo' => new Address(
        name: 'Alıcı Müşteri',
        street1: 'Kızılay Mah. 123. Sok. No:5',
        city: 'Ankara',
        district: 'Çankaya',
        phone: '+905559876543',
    ),
    'packages' => [
        new Package(weight: 3.0, desi: 2.0, quantity: 1, description: 'Kitap'),
    ],
    // 'paymentType' => PaymentType::SENDER,     // default
    // 'cashOnDelivery' => false,                 // default
    // 'codAmount' => 0.0,
])->send();

if ($response->isSuccessful()) {
    echo $response->getTrackingNumber();  // "SIP-001" (= orderNumber)
    echo $response->getBarcode();         // "SIP-001" (= barcodeText)
}
```

### Track Shipment

Uses the `GelecekIadeSiparisKontrol` SOAP method. Returns status codes 0-7.

```php
$response = $mng->getTrackingStatus([
    'trackingNumber' => 'SIP-001',      // pCHSiparisNo
])->send();

if ($response->isSuccessful()) {
    $info = $response->getTrackingInfo();
    echo $info->status->value;           // "delivered", "in_transit", etc.
    echo $info->trackingNumber;          // MNG gönderi numarası
    echo $info->signedBy;               // Teslim alan kişi (if delivered)
    echo $response->getTrackingUrl();    // MNG kargo takip linki
}
```

### Cancel Shipment

Uses the `SiparisIptali_C2C` SOAP method.

```php
$response = $mng->cancelShipment([
    'orderNumber' => 'SIP-001',
])->send();

if ($response->isCancelled()) {
    echo 'Shipment cancelled';
}
```

## Status Mapping

| MNG Code | Description | ShipmentStatus |
|----------|-------------|----------------|
| 0 | Henüz işlem yapılmadı | `PRE_TRANSIT` |
| 1 | Sipariş Kargoya Verildi | `PICKED_UP` |
| 2 | Transfer Aşamasında | `IN_TRANSIT` |
| 3 | Gönderi Teslim Birimine Ulaştı | `IN_TRANSIT` |
| 4 | Gönderi Teslimat Adresine Yönlendirildi | `OUT_FOR_DELIVERY` |
| 5 | Teslim Edildi | `DELIVERED` |
| 6 | Teslim Edilemedi (nedenli) | `FAILURE` |
| 7 | Göndericiye Teslim Edildi | `RETURNED` |

## Payment Types

| PaymentType | MNG Value |
|-------------|-----------|
| `SENDER` | `Gonderici_Odeyecek` |
| `RECEIVER` | `Alici_Odeyecek` |
| `THIRD_PARTY` | `Platform_Odeyecek` |

## API Details

- **Transport**: SOAP-over-HTTP (raw XML via PSR-18, not SoapClient)
- **Create**: `SiparisKayit_C2C` — supports sender + receiver, packages (Kg/Desi/Adet), COD, delivery type
- **Track**: `GelecekIadeSiparisKontrol` — returns .NET DataSet XML with status codes
- **Cancel**: `SiparisIptali_C2C` — returns "1" (success) or "0" (failure)
- **Test endpoint**: `https://service.mngkargo.com.tr/tservis/musterisiparisnew.asmx`
- **Production endpoint**: `https://service.mngkargo.com.tr/musterikargosiparis/musterisiparisnew.asmx`

## Testing

```bash
# Run tests
docker compose run --rm php bash -c "cd omniship-mng && vendor/bin/pest"
```

## License

MIT
