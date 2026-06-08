<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Exception\HttpException;
use Omniship\Common\Message\AbstractHttpRequest;
use Omniship\Common\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

abstract class AbstractMngRequest extends AbstractHttpRequest
{
    private const BASE_URL_TEST = 'https://testapi.mngkargo.com.tr';
    private const BASE_URL_PRODUCTION = 'https://api.mngkargo.com.tr';
    private const TOKEN_PATH = '/mngapi/api/token';

    /**
     * MNG JWTs are valid for 8 hours. Cache them for 7 to leave a safety
     * buffer for in-flight requests.
     */
    private const TOKEN_CACHE_TTL_SECONDS = 7 * 3600;

    abstract protected function getEndpoint(): string;

    abstract protected function getHttpMethod(): string;

    /**
     * @param array<string, mixed> $data
     */
    abstract protected function createResponse(mixed $data): ResponseInterface;

    public function getClientId(): string
    {
        return (string) $this->getParameter('clientId');
    }

    public function setClientId(string $clientId): static
    {
        return $this->setParameter('clientId', $clientId);
    }

    public function getClientSecret(): string
    {
        return (string) $this->getParameter('clientSecret');
    }

    public function setClientSecret(string $clientSecret): static
    {
        return $this->setParameter('clientSecret', $clientSecret);
    }

    public function getCustomerNumber(): string
    {
        return (string) $this->getParameter('customerNumber');
    }

    public function setCustomerNumber(string $customerNumber): static
    {
        return $this->setParameter('customerNumber', $customerNumber);
    }

    public function getPassword(): string
    {
        return (string) $this->getParameter('password');
    }

    public function setPassword(string $password): static
    {
        return $this->setParameter('password', $password);
    }

    public function getIdentityType(): int
    {
        return (int) ($this->getParameter('identityType') ?? 1);
    }

    public function setIdentityType(int $identityType): static
    {
        return $this->setParameter('identityType', $identityType);
    }

    public function getTokenCache(): ?CacheInterface
    {
        $cache = $this->getParameter('tokenCache');

        return $cache instanceof CacheInterface ? $cache : null;
    }

    public function setTokenCache(?CacheInterface $cache): static
    {
        return $this->setParameter('tokenCache', $cache);
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->getParameter('paymentType');
    }

    public function setPaymentType(PaymentType $paymentType): static
    {
        return $this->setParameter('paymentType', $paymentType);
    }

    public function getCashOnDelivery(): bool
    {
        return (bool) ($this->getParameter('cashOnDelivery') ?? false);
    }

    public function setCashOnDelivery(bool $cashOnDelivery): static
    {
        return $this->setParameter('cashOnDelivery', $cashOnDelivery);
    }

    public function getCodAmount(): float
    {
        return (float) ($this->getParameter('codAmount') ?? 0.0);
    }

    public function setCodAmount(float|int|string $codAmount): static
    {
        return $this->setParameter('codAmount', (float) $codAmount);
    }

    public function getReferenceId(): ?string
    {
        return $this->getParameter('referenceId');
    }

    public function setReferenceId(string $referenceId): static
    {
        return $this->setParameter('referenceId', $referenceId);
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->getParameter('invoiceNumber');
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        return $this->setParameter('invoiceNumber', $invoiceNumber);
    }

    public function getRecipientTaxNumber(): ?string
    {
        return $this->getParameter('recipientTaxNumber');
    }

    public function setRecipientTaxNumber(?string $value): static
    {
        return $this->setParameter('recipientTaxNumber', $value);
    }

    public function getRecipientCityCode(): ?int
    {
        $value = $this->getParameter('recipientCityCode');

        return $value === null ? null : (int) $value;
    }

    public function setRecipientCityCode(int $code): static
    {
        return $this->setParameter('recipientCityCode', $code);
    }

    public function getRecipientDistrictCode(): ?int
    {
        $value = $this->getParameter('recipientDistrictCode');

        return $value === null ? null : (int) $value;
    }

    public function setRecipientDistrictCode(int $code): static
    {
        return $this->setParameter('recipientDistrictCode', $code);
    }

    public function getShipmentServiceType(): int
    {
        return (int) ($this->getParameter('shipmentServiceType') ?? 1);
    }

    public function setShipmentServiceType(int $type): static
    {
        return $this->setParameter('shipmentServiceType', $type);
    }

    public function getPackagingType(): int
    {
        return (int) ($this->getParameter('packagingType') ?? 3);
    }

    public function setPackagingType(int $type): static
    {
        return $this->setParameter('packagingType', $type);
    }

    public function getDeliveryType(): int
    {
        return (int) ($this->getParameter('deliveryType') ?? 1);
    }

    public function setDeliveryType(int $type): static
    {
        return $this->setParameter('deliveryType', $type);
    }

    public function getContent(): string
    {
        return (string) ($this->getParameter('content') ?? '');
    }

    public function setContent(string $content): static
    {
        return $this->setParameter('content', $content);
    }

    public function getDescription(): string
    {
        return (string) ($this->getParameter('description') ?? '');
    }

    public function setDescription(string $description): static
    {
        return $this->setParameter('description', $description);
    }

    public function getBillOfLandingId(): ?string
    {
        return $this->getParameter('billOfLandingId');
    }

    public function setBillOfLandingId(string $value): static
    {
        return $this->setParameter('billOfLandingId', $value);
    }

    public function getMarketPlaceShortCode(): string
    {
        return (string) ($this->getParameter('marketPlaceShortCode') ?? '');
    }

    public function setMarketPlaceShortCode(string $code): static
    {
        return $this->setParameter('marketPlaceShortCode', $code);
    }

    public function getMarketPlaceSaleCode(): string
    {
        return (string) ($this->getParameter('marketPlaceSaleCode') ?? '');
    }

    public function setMarketPlaceSaleCode(string $code): static
    {
        return $this->setParameter('marketPlaceSaleCode', $code);
    }

    public function getSendSmsRecipientArrival(): bool
    {
        return (bool) ($this->getParameter('sendSmsRecipientArrival') ?? false);
    }

    public function setSendSmsRecipientArrival(bool $value): static
    {
        return $this->setParameter('sendSmsRecipientArrival', $value);
    }

    public function getSendSmsRecipientPrepared(): bool
    {
        return (bool) ($this->getParameter('sendSmsRecipientPrepared') ?? false);
    }

    public function setSendSmsRecipientPrepared(bool $value): static
    {
        return $this->setParameter('sendSmsRecipientPrepared', $value);
    }

    public function getSendSmsShipperDelivered(): bool
    {
        return (bool) ($this->getParameter('sendSmsShipperDelivered') ?? false);
    }

    public function setSendSmsShipperDelivered(bool $value): static
    {
        return $this->setParameter('sendSmsShipperDelivered', $value);
    }

    /**
     * Whether this request needs a JWT (Identity API). CBS Info endpoints
     * don't require it; everything else does.
     */
    protected function requiresJwt(): bool
    {
        return true;
    }

    protected function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . $this->getEndpoint();
        $method = $this->getHttpMethod();

        $headers = [
            'X-IBM-Client-Id' => $this->getClientId(),
            'X-IBM-Client-Secret' => $this->getClientSecret(),
            'Accept' => 'application/json',
        ];

        if ($this->requiresJwt()) {
            $headers['Authorization'] = 'Bearer ' . $this->fetchJwt();
        }

        $body = null;

        if ($method !== 'GET' && $data !== []) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $response = $this->sendHttpRequest(
            method: $method,
            url: $url,
            headers: $headers,
            body: $body,
        );

        $responseBody = (string) $response->getBody();
        $statusCode = $response->getStatusCode();

        $decoded = $responseBody === ''
            ? null
            : json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        if ($statusCode >= 400 && !is_array($decoded)) {
            throw new HttpException(
                "MNG API request to {$url} failed with HTTP {$statusCode}: {$responseBody}",
            );
        }

        return $this->response = $this->createResponse([
            'status' => $statusCode,
            'body' => $decoded,
        ]);
    }

    protected function fetchJwt(): string
    {
        $cache = $this->getTokenCache();
        $cacheKey = $this->buildTokenCacheKey();

        if ($cache !== null) {
            try {
                $cached = $cache->get($cacheKey);
            } catch (\Psr\SimpleCache\InvalidArgumentException) {
                $cached = null;
            }
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $url = $this->getBaseUrl() . self::TOKEN_PATH;

        $body = json_encode([
            'customerNumber' => $this->getCustomerNumber(),
            'password' => $this->getPassword(),
            'identityType' => $this->getIdentityType(),
        ], JSON_THROW_ON_ERROR);

        $response = $this->sendHttpRequest(
            method: 'POST',
            url: $url,
            headers: [
                'X-IBM-Client-Id' => $this->getClientId(),
                'X-IBM-Client-Secret' => $this->getClientSecret(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            body: $body,
        );

        $responseBody = (string) $response->getBody();
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new HttpException(
                "MNG Identity API failed with HTTP {$statusCode}: {$responseBody}",
            );
        }

        /** @var array{jwt?: string} $decoded */
        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        if (!is_string($decoded['jwt'] ?? null) || $decoded['jwt'] === '') {
            throw new HttpException("MNG Identity API returned no JWT: {$responseBody}");
        }

        if ($cache !== null) {
            try {
                $cache->set($cacheKey, $decoded['jwt'], self::TOKEN_CACHE_TTL_SECONDS);
            } catch (\Psr\SimpleCache\InvalidArgumentException) {
                // Cache write failures are non-fatal — we still return the JWT.
            }
        }

        return $decoded['jwt'];
    }

    /**
     * Cache key is scoped per (env + clientId + customerNumber) so multiple
     * shops on the same cache backend can't see each other's tokens, and
     * test/prod tokens never collide.
     */
    private function buildTokenCacheKey(): string
    {
        $env = $this->getTestMode() ? 'test' : 'prod';
        $hash = sha1($this->getClientId() . '|' . $this->getCustomerNumber());

        return "omniship_mng_jwt_{$env}_{$hash}";
    }

    /**
     * Map Omniship PaymentType to MNG numeric paymentType.
     * 1=GONDERICI_ODER, 2=ALICI_ODER, 3=PLATFORM_ODER (PLATFORM not valid for createOrder).
     */
    protected function mapPaymentType(?PaymentType $paymentType): int
    {
        return match ($paymentType) {
            PaymentType::RECEIVER => 2,
            PaymentType::THIRD_PARTY => 3,
            default => 1,
        };
    }

    protected function normalizeReference(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }
}
