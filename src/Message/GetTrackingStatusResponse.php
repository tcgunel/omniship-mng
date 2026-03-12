<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

class GetTrackingStatusResponse extends AbstractResponse implements TrackingResponse
{
    /**
     * MNG status code to ShipmentStatus mapping.
     *
     * 0 = Henüz işlem yapılmadı
     * 1 = Sipariş Kargoya Verildi
     * 2 = Transfer Aşamasında
     * 3 = Gönderi Teslim Birimine Ulaştı
     * 4 = Gönderi Teslimat Adresine Yönlendirildi
     * 5 = Teslim Edildi
     * 6 = [kod] Teslim Edilemedi
     * 7 = Göndericiye Teslim Edildi
     */
    private const STATUS_MAP = [
        0 => ShipmentStatus::PRE_TRANSIT,
        1 => ShipmentStatus::PICKED_UP,
        2 => ShipmentStatus::IN_TRANSIT,
        3 => ShipmentStatus::IN_TRANSIT,
        4 => ShipmentStatus::OUT_FOR_DELIVERY,
        5 => ShipmentStatus::DELIVERED,
        6 => ShipmentStatus::FAILURE,
        7 => ShipmentStatus::RETURNED,
    ];

    public function isSuccessful(): bool
    {
        if (!is_array($this->data)) {
            return false;
        }

        return ($this->data['Success'] ?? false) === true && $this->getShipment() !== null;
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['Message'])) {
            return null;
        }

        return (string) $this->data['Message'];
    }

    public function getCode(): ?string
    {
        $shipment = $this->getShipment();
        if ($shipment !== null && isset($shipment['SIPARIS_STATU'])) {
            return (string) $shipment['SIPARIS_STATU'];
        }

        return null;
    }

    public function getTrackingInfo(): TrackingInfo
    {
        $trackingNumber = '';
        $request = $this->getRequest();
        if ($request instanceof GetTrackingStatusRequest) {
            $trackingNumber = $request->getTrackingNumber() ?? '';
        }

        $shipment = $this->getShipment();

        if ($shipment === null) {
            return new TrackingInfo(
                trackingNumber: $trackingNumber,
                status: ShipmentStatus::UNKNOWN,
                events: [],
                carrier: 'MNG Kargo',
            );
        }

        $statusCode = isset($shipment['SIPARIS_STATU']) ? (int) $shipment['SIPARIS_STATU'] : 0;
        $status = self::mapStatus($statusCode);
        $description = $shipment['SIPARIS_STATU_ACIKLAMA'] ?? $shipment['KARGO_SON_HAREKET'] ?? '';

        $occurredAt = $this->parseDate($shipment['SIPARIS_STATU_TARIHI'] ?? null)
            ?? $this->parseDate($shipment['TESLIM_TARIHI'] ?? null)
            ?? new \DateTimeImmutable();

        $signedBy = null;
        if ($status === ShipmentStatus::DELIVERED && isset($shipment['TESLIM_ALAN_ADSOYAD'])) {
            $signedBy = trim($shipment['TESLIM_ALAN_ADSOYAD']);
            if ($signedBy === '') {
                $signedBy = null;
            }
        }

        $event = new TrackingEvent(
            status: $status,
            description: $description,
            occurredAt: $occurredAt,
        );

        $gonderiNo = $shipment['GONDERI_NO'] ?? null;
        $trackingUrl = $shipment['KARGO_TAKIP_URL'] ?? null;

        return new TrackingInfo(
            trackingNumber: $gonderiNo ?? $shipment['SIPARIS_KODU'] ?? $trackingNumber,
            status: $status,
            events: [$event],
            carrier: 'MNG Kargo',
            signedBy: $signedBy,
        );
    }

    /**
     * Get the tracking URL from the response.
     */
    public function getTrackingUrl(): ?string
    {
        $shipment = $this->getShipment();

        if ($shipment === null || !isset($shipment['KARGO_TAKIP_URL'])) {
            return null;
        }

        $url = trim($shipment['KARGO_TAKIP_URL']);

        return $url !== '' ? $url : null;
    }

    public static function mapStatus(int $code): ShipmentStatus
    {
        return self::STATUS_MAP[$code] ?? ShipmentStatus::UNKNOWN;
    }

    /**
     * @return array<string, string>|null
     */
    private function getShipment(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['Shipment'])) {
            return null;
        }

        if (!is_array($this->data['Shipment'])) {
            return null;
        }

        /** @var array<string, string> */
        return $this->data['Shipment'];
    }

    private function parseDate(?string $dateStr): ?\DateTimeImmutable
    {
        if ($dateStr === null || $dateStr === '') {
            return null;
        }

        // Try ISO 8601 format: 2015-03-25T00:00:00+02:00
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateStr);
        if ($date !== false) {
            return $date;
        }

        // Try Turkish date format: 16.02.2015 16:28:37
        $date = \DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $dateStr);
        if ($date !== false) {
            return $date;
        }

        // Try ISO without timezone
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateStr);
        if ($date !== false) {
            return $date;
        }

        return null;
    }
}
