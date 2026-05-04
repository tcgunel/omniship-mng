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
     * MNG shipmentStatusCode mapping (per Standard Query swagger):
     *   1 → Gönderi_Hazırlandı
     *   2 → Transfer_Aşamasında
     *   3 → Teslimat_Birimine_Ulaştı
     *   4 → Alıcı_Adresine_Yönlendirildi
     *   5 → Teslim_Edildi
     *   6 → Teslim_Edilemedi
     *   7 → Geri_Geliyor
     *   8 → Destek_Gerekiyor
     */
    private const STATUS_MAP = [
        1 => ShipmentStatus::PRE_TRANSIT,
        2 => ShipmentStatus::IN_TRANSIT,
        3 => ShipmentStatus::IN_TRANSIT,
        4 => ShipmentStatus::OUT_FOR_DELIVERY,
        5 => ShipmentStatus::DELIVERED,
        6 => ShipmentStatus::FAILURE,
        7 => ShipmentStatus::RETURNED,
        8 => ShipmentStatus::FAILURE,
    ];

    public function isSuccessful(): bool
    {
        return $this->headlineStatus() !== null || $this->events() !== [];
    }

    public function getMessage(): ?string
    {
        $status = $this->headlineStatus();

        if (is_array($status) && isset($status['shipmentStatus']) && is_string($status['shipmentStatus'])) {
            return $status['shipmentStatus'];
        }

        return null;
    }

    public function getCode(): ?string
    {
        $status = $this->headlineStatus();

        if (is_array($status) && isset($status['shipmentStatusCode'])) {
            return (string) $status['shipmentStatusCode'];
        }

        return null;
    }

    public function getTrackingInfo(): TrackingInfo
    {
        $reference = is_array($this->data) && isset($this->data['reference'])
            ? (string) $this->data['reference']
            : '';

        $status = $this->headlineStatus();
        $events = $this->events();

        $trackingNumber = $reference;
        if (is_array($status) && isset($status['shipmentId'])) {
            $trackingNumber = (string) $status['shipmentId'];
        }

        $statusEnum = ShipmentStatus::UNKNOWN;
        if (is_array($status) && isset($status['shipmentStatusCode'])) {
            $statusEnum = self::mapStatus((int) $status['shipmentStatusCode']);
        }

        $signedBy = null;
        if (
            is_array($status)
            && ($status['isDelivered'] ?? null) === 1
            && isset($status['deliveryTo'])
        ) {
            $deliveryTo = trim((string) $status['deliveryTo']);
            $signedBy = $deliveryTo !== '' ? $deliveryTo : null;
        }

        $trackingEvents = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $trackingEvents[] = $this->buildEvent($event);
        }

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $statusEnum,
            events: $trackingEvents,
            carrier: 'MNG Kargo',
            signedBy: $signedBy,
        );
    }

    public function getTrackingUrl(): ?string
    {
        $status = $this->headlineStatus();

        if (is_array($status) && isset($status['trackingUrl']) && is_string($status['trackingUrl'])) {
            $url = trim($status['trackingUrl']);

            return $url !== '' ? $url : null;
        }

        return null;
    }

    public static function mapStatus(int $code): ShipmentStatus
    {
        return self::STATUS_MAP[$code] ?? ShipmentStatus::UNKNOWN;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function buildEvent(array $event): TrackingEvent
    {
        $statusName = is_string($event['eventStatus'] ?? null) ? (string) $event['eventStatus'] : '';
        $description = is_string($event['eventStatusEn'] ?? null) ? (string) $event['eventStatusEn'] : $statusName;

        $occurredAt = $this->parseDate($event['eventDateTime2'] ?? null)
            ?? $this->parseDate($event['eventDateTime'] ?? null)
            ?? new \DateTimeImmutable();

        $location = is_string($event['location'] ?? null) ? (string) $event['location'] : null;
        $city = is_string($event['locationAddress'] ?? null) ? (string) $event['locationAddress'] : null;
        $country = is_string($event['country'] ?? null) ? (string) $event['country'] : null;

        return new TrackingEvent(
            status: $this->statusFromEventName($statusName),
            description: $description,
            occurredAt: $occurredAt,
            location: $location,
            city: $city,
            country: $country,
        );
    }

    private function statusFromEventName(string $name): ShipmentStatus
    {
        $normalized = mb_strtolower($name, 'UTF-8');

        return match (true) {
            str_contains($normalized, 'hazırlandı') || str_contains($normalized, 'created') => ShipmentStatus::PRE_TRANSIT,
            str_contains($normalized, 'transit') || str_contains($normalized, 'transfer') => ShipmentStatus::IN_TRANSIT,
            str_contains($normalized, 'yönlendirildi') || str_contains($normalized, 'delivery') => ShipmentStatus::OUT_FOR_DELIVERY,
            str_contains($normalized, 'teslim_edildi') || str_contains($normalized, 'teslim edildi') || str_contains($normalized, 'delivered') => ShipmentStatus::DELIVERED,
            str_contains($normalized, 'iade') || str_contains($normalized, 'geri') || str_contains($normalized, 'returned') => ShipmentStatus::RETURNED,
            str_contains($normalized, 'edilemedi') || str_contains($normalized, 'fail') => ShipmentStatus::FAILURE,
            default => ShipmentStatus::UNKNOWN,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function headlineStatus(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['status'])) {
            return null;
        }

        return is_array($this->data['status']) ? $this->data['status'] : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(): array
    {
        if (!is_array($this->data) || !isset($this->data['events'])) {
            return [];
        }

        if (!is_array($this->data['events'])) {
            return [];
        }

        $out = [];
        foreach ($this->data['events'] as $entry) {
            if (is_array($entry)) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // Standard Query gives both formats. Prefer the ISO-style one.
        $formats = [
            'Y-m-d H:i:s',         // eventDateTime2
            'd-m-Y H:i:s',         // eventDateTime
            'd-m-Y H:i',           // shipmentStatus deliveryDateTime
            'd-m-Y',               // estimatedDeliveryDate
            \DateTimeInterface::ATOM,
        ];

        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        return null;
    }
}
