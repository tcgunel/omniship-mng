<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\ResponseInterface;

class GetTrackingStatusRequest extends AbstractMngRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'trackingNumber');

        return [
            'pRfSipGnMusteriNo' => $this->getUsername() ?? '',
            'pRfSipGnMusteriSifre' => $this->getPassword() ?? '',
            'pCHBarkod' => '',
            'pCHFaturaSeri' => '',
            'pCHFaturaNo' => '',
            'pMngGonderiNo' => '',
            'pCHSiparisNo' => $this->getTrackingNumber() ?? '',
            'pGonderiCikisTarihi' => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildTrackingXml($data);
        $body = $this->sendSoapRequest('GelecekIadeSiparisKontrol', $soapBody);

        $parsed = $this->parseResponse($body);

        return $this->response = new GetTrackingStatusResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildTrackingXml(array $data): string
    {
        return '<GelecekIadeSiparisKontrol xmlns="http://tempuri.org/">'
            . '<pRfSipGnMusteriNo>' . $this->xmlEscape((string) $data['pRfSipGnMusteriNo']) . '</pRfSipGnMusteriNo>'
            . '<pRfSipGnMusteriSifre>' . $this->xmlEscape((string) $data['pRfSipGnMusteriSifre']) . '</pRfSipGnMusteriSifre>'
            . '<pCHBarkod>' . $this->xmlEscape((string) $data['pCHBarkod']) . '</pCHBarkod>'
            . '<pCHFaturaSeri>' . $this->xmlEscape((string) $data['pCHFaturaSeri']) . '</pCHFaturaSeri>'
            . '<pCHFaturaNo>' . $this->xmlEscape((string) $data['pCHFaturaNo']) . '</pCHFaturaNo>'
            . '<pMngGonderiNo>' . $this->xmlEscape((string) $data['pMngGonderiNo']) . '</pMngGonderiNo>'
            . '<pCHSiparisNo>' . $this->xmlEscape((string) $data['pCHSiparisNo']) . '</pCHSiparisNo>'
            . '<pGonderiCikisTarihi>' . $this->xmlEscape((string) $data['pGonderiCikisTarihi']) . '</pGonderiCikisTarihi>'
            . '</GelecekIadeSiparisKontrol>';
    }

    /**
     * Parse the GelecekIadeSiparisKontrolResponse SOAP body.
     *
     * The response contains a .NET DataSet in diffgram format.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        // Check for SOAP fault
        $faults = $body->xpath('.//faultstring');
        if ($faults !== false && isset($faults[0])) {
            return [
                'Success' => false,
                'Message' => (string) $faults[0],
                'Shipment' => null,
            ];
        }

        $resultNodes = $body->xpath('.//tns:GelecekIadeSiparisKontrolResult');

        if ($resultNodes === false || !isset($resultNodes[0])) {
            return [
                'Success' => false,
                'Message' => 'No tracking data found',
                'Shipment' => null,
            ];
        }

        $result = $resultNodes[0];

        // Parse the diffgram DataSet — find Table1 element
        $result->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Try multiple paths for the Table1 element within the DataSet
        $table = $result->xpath('.//Table1');

        if ($table === false || !isset($table[0])) {
            // Try within diffgram namespace
            $diffgram = $result->xpath('.//diffgr:diffgram');
            if ($diffgram !== false && isset($diffgram[0])) {
                $children = $diffgram[0]->children();
                foreach ($children as $dataSet) {
                    foreach ($dataSet->children() as $row) {
                        $table = [$row];
                        break 2;
                    }
                }
            }
        }

        if (!isset($table[0])) {
            return [
                'Success' => true,
                'Message' => 'No shipment data in response',
                'Shipment' => null,
            ];
        }

        $row = $table[0];
        $shipment = [];
        foreach ($row->children() as $child) {
            $shipment[$child->getName()] = (string) $child;
        }

        return [
            'Success' => true,
            'Message' => 'OK',
            'Shipment' => $shipment,
        ];
    }
}
