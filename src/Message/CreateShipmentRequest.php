<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Address;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class CreateShipmentRequest extends AbstractMngRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'orderNumber', 'shipFrom', 'shipTo');

        $shipFrom = $this->getShipFrom();
        assert($shipFrom instanceof Address);

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        $packages = $this->getPackages() ?? [];

        return [
            'pKullaniciAdi' => $this->getUsername() ?? '',
            'PSifre' => $this->getPassword() ?? '',
            'pSiparisNo' => $this->getOrderNumber() ?? '',
            'pBarkodText' => $this->getBarcodeText() ?? $this->getOrderNumber() ?? '',
            'pIrsaliyeNo' => $this->getInvoiceNumber() ?? '',
            'pUrunBedeli' => $this->getCashOnDelivery() ? $this->formatNumber($this->getCodAmount() ?? 0.0) : '',
            'pGonderiParcaList' => $this->buildParcaList($packages),
            // Sender
            'pGonMusteriMngNo' => '',
            'pGonMusteriBayiNo' => '',
            'pGonMusteriSiparisNo' => '',
            'pGonMusteriAdi' => $shipFrom->name ?? '',
            'pGonMusAdresFarkli' => '1',
            'pGonIlAdi' => $this->toUpperTurkish($shipFrom->city ?? ''),
            'pGonIlceAdi' => $this->toUpperTurkish($shipFrom->district ?? ''),
            'pGonAdresText' => $shipFrom->street1 ?? '',
            'pGonTelCep' => $this->formatPhone($shipFrom->phone ?? ''),
            'pGonEmail' => $shipFrom->email ?? '',
            'pGonVergiDairesi' => $shipFrom->taxId !== null ? '' : 'SAHIS',
            'pGonVergiNumarasi' => $shipFrom->taxId ?? $shipFrom->nationalId ?? '',
            // Receiver
            'pAliciMusteriMngNo' => '',
            'pAliciMusteriBayiNo' => '',
            'pAliciMusteriAdi' => $shipTo->name ?? '',
            'pAliciMusAdresFarkli' => '1',
            'pAliciIlAdi' => $this->toUpperTurkish($shipTo->city ?? ''),
            'pAliciilceAdi' => $this->toUpperTurkish($shipTo->district ?? ''),
            'pAliciAdresText' => $shipTo->street1 ?? '',
            'pAliciTelCep' => $this->formatPhone($shipTo->phone ?? ''),
            'pAliciEmail' => $shipTo->email ?? '',
            'pAliciVergiDairesi' => $shipTo->taxId !== null ? '' : 'SAHIS',
            'pAliciVergiNumarasi' => $shipTo->taxId ?? $shipTo->nationalId ?? '',
            // Options
            'pOdemeSekli' => $this->mapPaymentType($this->getPaymentType()),
            'pTeslimSekli' => 'Adrese_Teslim',
            'pKargoCinsi' => 'PAKET',
            'pGonSms' => 0,
            'pAliciSms' => 0,
            'pKapidaTahsilat' => $this->getCashOnDelivery() ? 'Mal_Bedeli_Tahsil_Edilsin' : 'Mal_Bedeli_Tahsil_Edilmesin',
            'pAciklama' => $this->getShipmentDescription($packages),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildSiparisKayitC2CXml($data);
        $body = $this->sendSoapRequest('SiparisKayit_C2C', $soapBody);

        $parsed = $this->parseResponse($body);

        return $this->response = new CreateShipmentResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSiparisKayitC2CXml(array $data): string
    {
        /** @var array<int, array<string, mixed>> $parcaList */
        $parcaList = $data['pGonderiParcaList'];
        $parcaXml = '<pGonderiParcaList>';
        foreach ($parcaList as $parca) {
            $parcaXml .= '<GonderiParca>'
                . '<Kg>' . (int) $parca['Kg'] . '</Kg>'
                . '<Desi>' . (int) $parca['Desi'] . '</Desi>'
                . '<Adet>' . (int) $parca['Adet'] . '</Adet>'
                . '<Icerik>' . $this->xmlEscape((string) $parca['Icerik']) . '</Icerik>'
                . '</GonderiParca>';
        }
        $parcaXml .= '</pGonderiParcaList>';

        $xml = '<SiparisKayit_C2C xmlns="http://tempuri.org/">'
            . '<pKullaniciAdi>' . $this->xmlEscape((string) $data['pKullaniciAdi']) . '</pKullaniciAdi>'
            . '<pSifre>' . $this->xmlEscape((string) $data['PSifre']) . '</pSifre>'
            . '<pSiparisNo>' . $this->xmlEscape((string) $data['pSiparisNo']) . '</pSiparisNo>'
            . '<pBarkodText>' . $this->xmlEscape((string) $data['pBarkodText']) . '</pBarkodText>'
            . '<pIrsaliyeNo>' . $this->xmlEscape((string) $data['pIrsaliyeNo']) . '</pIrsaliyeNo>'
            . '<pUrunBedeli>' . $this->xmlEscape((string) $data['pUrunBedeli']) . '</pUrunBedeli>'
            . $parcaXml
            . '<pGonMusteriMngNo>' . $this->xmlEscape((string) $data['pGonMusteriMngNo']) . '</pGonMusteriMngNo>'
            . '<pGonMusteriBayiNo>' . $this->xmlEscape((string) $data['pGonMusteriBayiNo']) . '</pGonMusteriBayiNo>'
            . '<pGonMusteriSiparisNo>' . $this->xmlEscape((string) $data['pGonMusteriSiparisNo']) . '</pGonMusteriSiparisNo>'
            . '<pGonMusteriAdi>' . $this->xmlEscape((string) $data['pGonMusteriAdi']) . '</pGonMusteriAdi>'
            . '<pGonMusAdresFarkli>' . $this->xmlEscape((string) $data['pGonMusAdresFarkli']) . '</pGonMusAdresFarkli>'
            . '<pGonIlAdi>' . $this->xmlEscape((string) $data['pGonIlAdi']) . '</pGonIlAdi>'
            . '<pGonIlceAdi>' . $this->xmlEscape((string) $data['pGonIlceAdi']) . '</pGonIlceAdi>'
            . '<pGonAdresText>' . $this->xmlEscape((string) $data['pGonAdresText']) . '</pGonAdresText>'
            . '<pGonSemt></pGonSemt>'
            . '<pGonMahalle></pGonMahalle>'
            . '<pGonMeydanBulvar></pGonMeydanBulvar>'
            . '<pGonCadde></pGonCadde>'
            . '<pGonSokak></pGonSokak>'
            . '<pGonTelIs></pGonTelIs>'
            . '<pGonTelEv></pGonTelEv>'
            . '<pGonTelCep>' . $this->xmlEscape((string) $data['pGonTelCep']) . '</pGonTelCep>'
            . '<pGonFax></pGonFax>'
            . '<pGonEmail>' . $this->xmlEscape((string) $data['pGonEmail']) . '</pGonEmail>'
            . '<pGonVergiDairesi>' . $this->xmlEscape((string) $data['pGonVergiDairesi']) . '</pGonVergiDairesi>'
            . '<pGonVergiNumarasi>' . $this->xmlEscape((string) $data['pGonVergiNumarasi']) . '</pGonVergiNumarasi>'
            . '<pAliciMusteriMngNo>' . $this->xmlEscape((string) $data['pAliciMusteriMngNo']) . '</pAliciMusteriMngNo>'
            . '<pAliciMusteriBayiNo>' . $this->xmlEscape((string) $data['pAliciMusteriBayiNo']) . '</pAliciMusteriBayiNo>'
            . '<pAliciMusteriAdi>' . $this->xmlEscape((string) $data['pAliciMusteriAdi']) . '</pAliciMusteriAdi>'
            . '<pAliciMusAdresFarkli>' . $this->xmlEscape((string) $data['pAliciMusAdresFarkli']) . '</pAliciMusAdresFarkli>'
            . '<pAliciIlAdi>' . $this->xmlEscape((string) $data['pAliciIlAdi']) . '</pAliciIlAdi>'
            . '<pAliciilceAdi>' . $this->xmlEscape((string) $data['pAliciilceAdi']) . '</pAliciilceAdi>'
            . '<pAliciAdresText>' . $this->xmlEscape((string) $data['pAliciAdresText']) . '</pAliciAdresText>'
            . '<pAliciSemt></pAliciSemt>'
            . '<pAliciMahalle></pAliciMahalle>'
            . '<pAliciMeydanBulvar></pAliciMeydanBulvar>'
            . '<pAliciCadde></pAliciCadde>'
            . '<pAliciSokak></pAliciSokak>'
            . '<pAliciTelIs></pAliciTelIs>'
            . '<pAliciTelEv></pAliciTelEv>'
            . '<pAliciTelCep>' . $this->xmlEscape((string) $data['pAliciTelCep']) . '</pAliciTelCep>'
            . '<pAliciFax></pAliciFax>'
            . '<pAliciEmail>' . $this->xmlEscape((string) $data['pAliciEmail']) . '</pAliciEmail>'
            . '<pAliciVergiDairesi>' . $this->xmlEscape((string) $data['pAliciVergiDairesi']) . '</pAliciVergiDairesi>'
            . '<pAliciVergiNumarasi>' . $this->xmlEscape((string) $data['pAliciVergiNumarasi']) . '</pAliciVergiNumarasi>'
            . '<pOdemeSekli>' . $this->xmlEscape((string) $data['pOdemeSekli']) . '</pOdemeSekli>'
            . '<pTeslimSekli>' . $this->xmlEscape((string) $data['pTeslimSekli']) . '</pTeslimSekli>'
            . '<pKargoCinsi>' . $this->xmlEscape((string) $data['pKargoCinsi']) . '</pKargoCinsi>'
            . '<pGonSms>' . (int) $data['pGonSms'] . '</pGonSms>'
            . '<pAliciSms>' . (int) $data['pAliciSms'] . '</pAliciSms>'
            . '<pKapidaTahsilat>' . $this->xmlEscape((string) $data['pKapidaTahsilat']) . '</pKapidaTahsilat>'
            . '<pAciklama>' . $this->xmlEscape((string) $data['pAciklama']) . '</pAciklama>'
            . '</SiparisKayit_C2C>';

        return $xml;
    }

    /**
     * @return array<string, string>
     */
    private function parseResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        $resultNodes = $body->xpath('.//tns:SiparisKayit_C2CResult');

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

    /**
     * @param Package[] $packages
     * @return array<int, array<string, mixed>>
     */
    private function buildParcaList(array $packages): array
    {
        if ($packages === []) {
            return [[
                'Kg' => 0,
                'Desi' => 0,
                'Adet' => 1,
                'Icerik' => '',
            ]];
        }

        $parcalar = [];

        foreach ($packages as $package) {
            $desi = $package->getDesi() ?? 0.0;
            $parcalar[] = [
                'Kg' => (int) round($package->weight),
                'Desi' => (int) round($desi),
                'Adet' => $package->quantity,
                'Icerik' => $package->description ?? '',
            ];
        }

        return $parcalar;
    }

    /**
     * @param Package[] $packages
     */
    private function getShipmentDescription(array $packages): string
    {
        foreach ($packages as $package) {
            if ($package->description !== null && $package->description !== '') {
                return $package->description;
            }
        }

        return '';
    }

    private function formatNumber(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted;
    }

    private function toUpperTurkish(string $value): string
    {
        $search = ['ı', 'i', 'ğ', 'ü', 'ş', 'ö', 'ç'];
        $replace = ['I', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];

        return mb_strtoupper(str_replace($search, $replace, $value), 'UTF-8');
    }

    private function formatPhone(string $phone): string
    {
        // Strip non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if ($cleaned === null) {
            return $phone;
        }

        // Remove country code prefix if present (90 + 10 digits)
        if (str_starts_with($cleaned, '90') && strlen($cleaned) === 12) {
            $cleaned = substr($cleaned, 2);
        }

        // Remove leading zero from domestic numbers (0 + 10 digits)
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
            $cleaned = substr($cleaned, 1);
        }

        return $cleaned;
    }
}
