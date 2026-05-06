# Omniship MNG Kargo (DHL eCommerce Turkey)

PHP 8.2+ carrier driver for the **MNG Kargo / DHL eCommerce REST API**, built for the [Omniship](https://github.com/tcgunel/omniship) multi-carrier shipping library.

> **History.** MNG Kargo was acquired by DHL Group in 2023 and rebranded as DHL eCommerce in Turkey (May 2025). The legacy SOAP API at `service.mngkargo.com.tr/musterikargosiparis/musterisiparisnew.asmx` is being phased out — versions of this package up to `v0.1.x` targeted that endpoint and are deprecated. **From `v0.2.0` onward this package targets the new REST API on `apizone`, fronted by IBM API Connect.**

---

## Table of contents

- [Setup checklist](#setup-checklist)
- [Quick start](#quick-start)
- [3-stage shipment flow](#3-stage-shipment-flow)
- [API reference](#api-reference)
  - [createRecipient](#createrecipient)
  - [createShipment](#createshipment)
  - [createReturnShipment](#createreturnshipment)
  - [cancelShipment](#cancelshipment)
  - [getTrackingStatus](#gettrackingstatus)
  - [getCities / getDistricts (CBS Info)](#getcities--getdistricts-cbs-info)
- [JWT caching](#jwt-caching)
- [Status mapping](#status-mapping)
- [Sandbox vs production](#sandbox-vs-production)
- [Gotchas, traps, and lessons learned](#gotchas-traps-and-lessons-learned)
- [Testing](#testing)

---

## Setup checklist

Before you write any code, you need to provision the API access. This is a one-time-per-environment job that has to happen on MNG's side; without it the package will just throw `401 Unauthorized — Cannot find valid subscription`.

**1. Register on the apizone portal.**
Sandbox: <https://sandbox.mngkargo.com.tr> · Production: <https://apizone.mngkargo.com.tr>. Same flow for both — they're separate accounts.

**2. Create an "Application" in the portal.**
You'll get back two keys: `X-IBM-Client-Id` and `X-IBM-Client-Secret`. These identify your **integrating platform's app**, not the merchant. Store them in `.env` — every merchant on your platform shares this same key pair.

**3. Subscribe the app to all six API products** under "API Ürünleri":

| Product | Used for | Required? |
|---|---|---|
| Identity 1.0.1 | minting JWTs | ✅ yes |
| Standard Command 1.0.0 | `createOrder` | ✅ yes |
| Standard Query 1.0.0 | tracking | ✅ yes |
| Barcode Command 1.0.0 | `createbarcode`, `cancelshipment` | ✅ yes |
| Plus Command 1.0.0 | `createRecipient` (3-stage flow) | ✅ yes |
| CBS Info 1.0.0 | city/district codes | ✅ yes |

**4. Production migration ritual.**
After repeating the apizone steps on the production portal, email `entegrasyon@mngkargo.com.tr` with your **app name + DHL eCommerce customer number + outbound static IP** to whitelist. Production subscriptions need MNG approval per-API.

**5. Per-merchant credentials.**
Each merchant gives you their MNG **customerNumber** + **password** (their own panel login, not a temporary password — see Gotchas). These plus your platform IBM keys are everything you need.

---

## Quick start

```php
use Omniship\Omniship;
use Omniship\Common\Address;
use Omniship\Common\Package;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */ // any PSR-16 cache works (Laravel's
                                  // Cache::store(), Symfony Psr16Cache, etc.)

$mng = Omniship::create('MNG');
$mng->initialize([
    // platform-wide IBM keys (from your apizone app)
    'clientId'       => env('MNG_CLIENT_ID'),
    'clientSecret'   => env('MNG_CLIENT_SECRET'),
    // per-merchant MNG account
    'customerNumber' => '1234567890',     // merchant's MNG müşteri numarası
    'password'       => 'permanent-panel-pwd',
    'identityType'   => 1,                // always 1
    'testMode'       => true,             // sandbox vs production
    'tokenCache'     => $cache,           // optional, see "JWT caching"
]);

$response = $mng->createShipment([
    'referenceId' => 'OMN-12345',         // unique, uppercased, ≤30 chars
    'shipTo' => new Address(
        name:    'Ad Soyad',
        street1: 'Örnek mah. 123. sok. No:5 Daire:7',
        city:    'Adana',
        district:'Seyhan',
        phone:   '+905555555555',
        email:   'alici@example.com',
    ),
    'recipientCityCode'     => 1,         // from CBS Info — see below
    'recipientDistrictCode' => 100,
    'recipientTaxNumber'    => '11111111110', // TC Kimlik (or placeholder)
    'packages' => [
        new Package(weight: 1.0, desi: 1.0, quantity: 1, description: 'Test'),
    ],
])->send();

if ($response->isSuccessful()) {
    echo $response->getShipmentId();   // "614118757013" — MNG's shipment id
    echo $response->getTrackingNumber(); // same as shipmentId
    echo $response->getBarcode();      // "C@6B@H21FMLRPNAAA6J" — scannable
    $label = $response->getLabel();    // ZPL string for Zebra printer
    file_put_contents('label.zpl', $label->content);
} else {
    echo $response->getMessage(); // human-readable MNG error description
    echo $response->getCode();    // HTTP status
}
```

---

## 3-stage shipment flow

MNG's integration team explicitly recommends a **three-stage** flow rather than the obvious two-step one. Calling `createOrder` and `createbarcode` back-to-back can fail with "no destination" errors because MNG hasn't had time to resolve the destination branch from the recipient address.

The recommended flow:

```text
┌─────────────────────────────────────────────────────────────────────┐
│ Stage 1: Plus Command → createRecipient                             │
│   When: as soon as the order arrives in your system                 │
│   Why:  pre-registers the recipient address so DHL eCommerce can    │
│         resolve the destination branch in the background            │
└─────────────────────────────────────────────────────────────────────┘
                                 │ ... time passes ...
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Stage 2: Standard Command → createOrder                             │
│   When: merchant decides to ship                                    │
│   What: send order metadata (referenceId, recipient, weight/desi    │
│         can be fixed/estimated at this point)                       │
└─────────────────────────────────────────────────────────────────────┘
                                 │ (typically immediately after)
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Stage 3: Barcode Command → createbarcode                            │
│   When: parcel is packed, real kg/desi known                        │
│   What: invoice the order and receive the printable ZPL label       │
└─────────────────────────────────────────────────────────────────────┘
```

**This package's `createShipment()` collapses stages 2 and 3 into one method** for callers that don't want to manage the lifecycle. It calls `createOrder` then `createbarcode` back-to-back. As long as `createRecipient` was called earlier (stage 1), this works reliably.

If you must do it without the recipient pre-registration step, expect occasional failures and either retry or insert a delay between createOrder and createbarcode.

The host application is responsible for stage 1. A typical wiring is to dispatch a queued job from the order-placed event:

```php
// in your order lifecycle hook
RegisterMngRecipientJob::dispatch($order);

// the job
public function handle(): void
{
    $mng = $this->buildCarrier($this->order->shop);
    $mng->createRecipient([
        'shipTo'                => $this->buildAddress($this->order),
        'recipientCityCode'     => $this->lookupCityCode(...),
        'recipientDistrictCode' => $this->lookupDistrictCode(...),
        'recipientTaxNumber'    => $this->order->tc_no ?: '11111111110',
    ])->send();

    $this->order->forceFill(['mng_recipient_registered_at' => now()])->save();
}
```

---

## API reference

### createRecipient

`POST /mngapi/api/pluscmdapi/createRecipient`

Pre-registers a recipient with DHL eCommerce. Idempotent on MNG's side; safe to retry. The response carries `shipperBranchCode` but no `customerId`, so you don't need to store anything from it — just track "did this fire successfully" locally.

```php
$mng->createRecipient([
    'shipTo'                => $address,    // Omniship\Common\Address
    'recipientCityCode'     => 34,
    'recipientDistrictCode' => 956,
    'recipientTaxNumber'    => '12345678901', // 11-digit TC or 10-digit Vergi
])->send();
```

### createShipment

Two HTTP calls under the hood:
1. `POST /mngapi/api/standardcmdapi/createOrder`
2. `POST /mngapi/api/barcodecmdapi/createbarcode`

Both must succeed for `isSuccessful()` to return true. Each call does one Identity token fetch unless caching is configured.

Required fields: `clientId`, `clientSecret`, `customerNumber`, `password`, `referenceId`, `shipTo`, `recipientCityCode`, `recipientDistrictCode`.

Optional fields with sensible defaults: `shipmentServiceType` (1=STANDART), `packagingType` (3=PAKET), `deliveryType` (1=ADRESE_TESLIM), `paymentType` (PaymentType::SENDER → 1), `cashOnDelivery` (false), `codAmount` (0), SMS preferences (all off), `marketPlaceShortCode` / `marketPlaceSaleCode` (empty), `billOfLandingId` / `invoiceNumber` (empty), `content` (falls back to first package description), `description` (same fallback), `recipientTaxNumber` (falls back to address `taxId` then `nationalId`).

`referenceId` is auto-uppercased before sending. MNG enforces uniqueness per-customer.

Response:

```php
$response->getShipmentId();    // MNG shipment ID (12-digit numeric)
$response->getTrackingNumber(); // alias for getShipmentId()
$response->getBarcode();       // first piece's scannable barcode value
$response->getBarcodes();      // all pieces' barcode values
$response->getInvoiceId();     // MNG invoice number ("FM378349")
$response->getLabel();         // Omniship\Common\Label with ZPL content
```

### createReturnShipment

`POST /mngapi/api/standardcmdapi/createReturnOrder`

Creates a return order — the consumer is the shipper, the merchant (your account) is the recipient. Same field shape as `createShipment` but takes `shipFrom` (the consumer) instead of `shipTo`. Returns a `returnOrderLabelURL` the consumer can use to drop off the parcel.

```php
$mng->createReturnShipment([
    'referenceId'           => 'RTN-' . $orderId,
    'shipFrom'              => $consumerAddress,
    'recipientCityCode'     => $consumerCityCode,
    'recipientDistrictCode' => $consumerDistrictCode,
    'packages'              => [...],
])->send();

echo $response->getReturnLabelUrl(); // https://mn.tc?rtnid=...
```

### cancelShipment

`PUT /mngapi/api/barcodecmdapi/cancelshipment` with body `{referenceId, shipmentId}`.

Per MNG's integration team, this is the **preferred** cancel method (over Standard Command's `/cancelorder/{ref}`). Cancellation is only valid until the parcel is scanned/accepted at an MNG branch, on the same day the barcode was printed.

```php
$mng->cancelShipment([
    'referenceId' => 'OMN-12345',     // your reference
    'shipmentId'  => '614118757013',  // MNG's shipment id from createShipment
])->send();
```

### getTrackingStatus

Two HTTP calls:
1. `GET /mngapi/api/standardqueryapi/trackshipment{ByShipmentId}/{id}` — list of events
2. `GET /mngapi/api/standardqueryapi/getshipmentstatus{ByShipmentId}/{id}` — headline status

The package picks between the two endpoint variants based on the input format: all-digit value → `ByShipmentId`, otherwise → `referenceId`. So you can pass either:

```php
// by your reference
$mng->getTrackingStatus(['referenceId' => 'OMN-12345'])->send();

// by MNG shipment id (12-digit numeric)
$mng->getTrackingStatus(['trackingNumber' => '614118757013'])->send();
```

Returns an `Omniship\Common\TrackingInfo` with status, signedBy, events, and a `trackingUrl` accessor on the response.

### getCities / getDistricts (CBS Info)

`GET /mngapi/api/cbsinfoapi/getcities` and `GET /mngapi/api/cbsinfoapi/getdistricts/{cityCode}`.

These don't require a JWT — only the IBM headers — so they're cheaper to call. The data is essentially static (Turkish geography). **Cache it locally**: typical pattern is a one-shot artisan command that seeds local `mng_cities` / `mng_districts` tables you then look up at shipment time.

```php
$cities = $mng->getCities()->send()->getCities();
// [['code' => 1, 'name' => 'Adana'], ['code' => 34, 'name' => 'İstanbul'], ...]

$districts = $mng->getDistricts(['cityCode' => 1])->send()->getDistricts();
// [['cityCode' => 1, 'cityName' => 'Adana', 'code' => 85, 'name' => 'Çukurova'], ...]
```

⚠️ **City names come back with a plate-code suffix** — `"ADANA  11"` rather than `"Adana"`. When matching against your local order data, normalize both sides (lowercase, transliterate Turkish chars, strip trailing whitespace+digits, collapse internal whitespace).

---

## JWT caching

MNG JWTs are valid for **8 hours**. Without caching, each `send()` mints a new one — that's 3-4 wasted token calls per shipment. To enable caching, pass any [PSR-16](https://www.php-fig.org/psr/psr-16/) `CacheInterface` as `tokenCache`:

```php
$mng->initialize([
    // ...
    'tokenCache' => Cache::store(),  // Laravel
    // 'tokenCache' => new Symfony\Component\Cache\Psr16Cache($adapter), // Symfony
]);
```

Cache key is `omniship_mng_jwt_{env}_{sha1(clientId|customerNumber)}` so:
- Multiple shops on the same backend never see each other's tokens
- Test/prod tokens never collide

TTL is hardcoded to 7 hours (1h safety buffer under MNG's 8h validity).

Cache write failures are non-fatal — the JWT is still returned.

---

## Status mapping

`shipmentStatusCode` from MNG → `Omniship\Common\Enum\ShipmentStatus`:

| MNG | Description | ShipmentStatus |
|---|---|---|
| 1 | Gönderi Hazırlandı | `PRE_TRANSIT` |
| 2 | Transfer Aşamasında | `IN_TRANSIT` |
| 3 | Teslimat Birimine Ulaştı | `IN_TRANSIT` |
| 4 | Alıcı Adresine Yönlendirildi | `OUT_FOR_DELIVERY` |
| 5 | Teslim Edildi | `DELIVERED` |
| 6 | Teslim Edilemedi | `FAILURE` |
| 7 | Geri Geliyor | `RETURNED` |
| 8 | Destek Gerekiyor | `FAILURE` |

`PaymentType` mapping (used by `paymentType` option):

| Omniship | MNG numeric |
|---|---|
| `SENDER` | 1 (Gönderici Öder) |
| `RECEIVER` | 2 (Alıcı Öder) |
| `THIRD_PARTY` | 3 (Platform Öder — note: invalid for `createOrder`, only for `createDetailedOrder`) |

---

## Sandbox vs production

| | Sandbox | Production |
|---|---|---|
| Portal | sandbox.mngkargo.com.tr | apizone.mngkargo.com.tr |
| Host | testapi.mngkargo.com.tr | api.mngkargo.com.tr |
| `testMode` | `true` | `false` |
| Account | separate registration | separate registration |
| API subscription approval | automatic | manual via entegrasyon@mngkargo.com.tr |
| Test credentials | provided by integration team | merchant's real MNG account |

The package picks the host automatically from the `testMode` flag.

---

## Gotchas, traps, and lessons learned

These are the things you only find out by hitting them. Saved here so the next person doesn't have to.

### 1. Responses are wrapped in arrays, not objects
The official swagger documents single-object responses for `createOrder`, `createbarcode`, `getshipmentstatus`. **MNG actually returns one-element arrays**: `[{...}]` instead of `{...}`. The package unwraps; if you're hitting the API directly, prepare for it.

### 2. `barcodes[i]` has BOTH `value` AND `barcode`
`value` is the ZPL label content (entire `^XA...^XZ` blob — a few KB). `barcode` is the actual scannable barcode string. Don't confuse them. The package exposes them via `getLabel()` and `getBarcode()` respectively.

### 3. `customerId` and `fullName` are mutually exclusive on Recipient
MNG validates: "if `Recipient.CustomerId` is filled, `Recipient.FullName` must be empty." Since we always send the address-style payload (with fullName), the package omits `customerId` entirely from the JSON. Don't add it back.

### 4. TC Kimlik / Vergi number is enforced
`recipientTaxNumber` must be 11-digit TC Kimlik (individual) or 10-digit Vergi numarası (company). Empty values produce error 26056. For consumer orders without a real TC, `11111111110` (which passes the TC Kimlik checksum) is a safe placeholder. `11111111111` works against the format check but fails the checksum — try the second-digit `1110` form first.

### 5. `recipient.email` is required
Despite being conceptually optional, MNG's createOrder rejects empty emails. Always provide a fallback (shop email, platform sentinel like `noreply@yoursite.com`).

### 6. `mobilePhoneNumber` format is strict
10 digits, **no leading 0, no country code**. `5551234567` works; `05551234567`, `+905551234567`, `90 555 123 45 67` don't. The package normalizes (strips +90 / 90 / leading 0).

### 7. Don't use the temporary password
When MNG provisions a new account they email a temporary password. **It expires fast**. The merchant must log into the panel at least once and set a permanent password before using it via the API. If the merchant later changes their panel password, the API password must be updated to match — they're the same credential.

### 8. Sandbox keys don't work against production host (and vice versa)
You'll get HTTP 500 from the Identity API with code `20013` and a useless message. Double-check `testMode` matches which apizone account the keys came from.

### 9. ZPL labels need a Zebra printer
The label content from `getLabel()` is ZPL — Zebra command language. Browsers can't render it natively. You either need:
- A Zebra-compatible label printer (drop the `.zpl` file directly to it, or `lp` it on Linux/macOS)
- A ZPL → PNG converter at print time (e.g. [Labelary](http://labelary.com/service.html) — free for low volume)

### 10. Subscriptions are per-API
Subscribing your app to "Identity" doesn't give you access to "Standard Command". Each one is separate. If you forget any, the missing one returns `401 Unauthorized — Cannot find valid subscription for the incoming API request`.

### 11. CBS Info has city plate-code suffixes
City names from `getCities()` come back like `"ADANA  11"` (with the plate code and double-space). Normalize by stripping trailing whitespace+digits before matching local data.

---

## Testing

```bash
# All tests
docker compose run --rm php bash -c "cd omniship-mng && vendor/bin/pest"

# Specific file
docker compose run --rm php bash -c "cd omniship-mng && vendor/bin/pest tests/Message/CreateShipmentRequestTest.php"
```

Mock HTTP fixtures live in `tests/Helpers.php`. The `createSequencedMockHttpClient(array $responses, array &$captured)` helper returns one response per call and captures every PSR request so tests can assert URL / method / headers / body after the fact. Use `createInMemoryCache()` to test the JWT caching path.

## License

MIT
