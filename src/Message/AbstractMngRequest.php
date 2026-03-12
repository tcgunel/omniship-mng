<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Message\AbstractHttpRequest;

abstract class AbstractMngRequest extends AbstractHttpRequest
{
    private const SOAP_URL_TEST = 'https://service.mngkargo.com.tr/tservis/musterisiparisnew.asmx';
    private const SOAP_URL_PRODUCTION = 'https://service.mngkargo.com.tr/musterikargosiparis/musterisiparisnew.asmx';

    public function getUsername(): ?string
    {
        return $this->getParameter('username');
    }

    public function setUsername(string $username): static
    {
        return $this->setParameter('username', $username);
    }

    public function getPassword(): ?string
    {
        return $this->getParameter('password');
    }

    public function setPassword(string $password): static
    {
        return $this->setParameter('password', $password);
    }

    public function getOrderNumber(): ?string
    {
        return $this->getParameter('orderNumber');
    }

    public function setOrderNumber(string $orderNumber): static
    {
        return $this->setParameter('orderNumber', $orderNumber);
    }

    public function getBarcodeText(): ?string
    {
        return $this->getParameter('barcodeText');
    }

    public function setBarcodeText(string $barcodeText): static
    {
        return $this->setParameter('barcodeText', $barcodeText);
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->getParameter('invoiceNumber');
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        return $this->setParameter('invoiceNumber', $invoiceNumber);
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

    public function getCodAmount(): ?float
    {
        return $this->getParameter('codAmount');
    }

    public function setCodAmount(float $codAmount): static
    {
        return $this->setParameter('codAmount', $codAmount);
    }

    protected function getSoapUrl(): string
    {
        return $this->getTestMode() ? self::SOAP_URL_TEST : self::SOAP_URL_PRODUCTION;
    }

    /**
     * Build a complete SOAP envelope wrapping the given body XML.
     */
    protected function buildSoapEnvelope(string $bodyXml): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . $bodyXml
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    /**
     * Send a SOAP request to the MNG ASMX endpoint.
     *
     * @return \SimpleXMLElement The parsed SOAP body content
     */
    protected function sendSoapRequest(string $soapAction, string $soapBody): \SimpleXMLElement
    {
        $envelope = $this->buildSoapEnvelope($soapBody);

        $response = $this->sendHttpRequest(
            method: 'POST',
            url: $this->getSoapUrl(),
            headers: [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/' . $soapAction,
            ],
            body: $envelope,
        );

        $xml = (string) $response->getBody();

        return $this->parseSoapResponse($xml);
    }

    /**
     * Parse a SOAP response XML string and return the body content.
     */
    protected function parseSoapResponse(string $xml): \SimpleXMLElement
    {
        $doc = new \SimpleXMLElement($xml);
        $doc->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $doc->registerXPathNamespace('tns', 'http://tempuri.org/');

        $body = $doc->xpath('//soap:Body');

        if ($body === false || !isset($body[0])) {
            return $doc;
        }

        return $body[0];
    }

    /**
     * Escape a string for safe XML content.
     */
    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Map Omniship PaymentType to MNG payment type string.
     */
    protected function mapPaymentType(?PaymentType $paymentType): string
    {
        return match ($paymentType) {
            PaymentType::RECEIVER => 'Alici_Odeyecek',
            PaymentType::THIRD_PARTY => 'Platform_Odeyecek',
            default => 'Gonderici_Odeyecek',
        };
    }
}
