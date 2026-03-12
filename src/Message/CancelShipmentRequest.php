<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

class CancelShipmentRequest extends AbstractMngRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'orderNumber');

        return [
            'pkullaniciAdi' => $this->getUsername() ?? '',
            'pSifre' => $this->getPassword() ?? '',
            'pSiparisNo' => $this->getOrderNumber() ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildCancelXml($data);
        $body = $this->sendSoapRequest('SiparisIptali_C2C', $soapBody);

        $parsed = $this->parseResponse($body);

        return $this->response = new CancelShipmentResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildCancelXml(array $data): string
    {
        return '<SiparisIptali_C2C xmlns="http://tempuri.org/">'
            . '<pkullaniciAdi>' . $this->xmlEscape((string) $data['pkullaniciAdi']) . '</pkullaniciAdi>'
            . '<pSifre>' . $this->xmlEscape((string) $data['pSifre']) . '</pSifre>'
            . '<pSiparisNo>' . $this->xmlEscape((string) $data['pSiparisNo']) . '</pSiparisNo>'
            . '</SiparisIptali_C2C>';
    }

    /**
     * @return array<string, string>
     */
    private function parseResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        $resultNodes = $body->xpath('.//tns:SiparisIptali_C2CResult');

        if ($resultNodes === false || !isset($resultNodes[0])) {
            return [
                'Result' => '0',
                'Message' => 'Unable to parse response',
            ];
        }

        $result = (string) $resultNodes[0];

        return [
            'Result' => $result,
            'Message' => $result === '1' ? 'Başarılı' : $result,
        ];
    }
}
