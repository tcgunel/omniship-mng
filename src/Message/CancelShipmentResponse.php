<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractResponse implements CancelResponse
{
    public function isSuccessful(): bool
    {
        $status = $this->httpStatus();

        return $status !== null && $status >= 200 && $status < 300;
    }

    public function isCancelled(): bool
    {
        return $this->isSuccessful();
    }

    public function getMessage(): ?string
    {
        $body = $this->body();

        if (is_array($body)) {
            return CreateShipmentResponse::extractErrorMessage($body);
        }

        if (is_string($body)) {
            return $body;
        }

        return null;
    }

    public function getCode(): ?string
    {
        $status = $this->httpStatus();

        return $status === null ? null : (string) $status;
    }

    private function httpStatus(): ?int
    {
        if (!is_array($this->data) || !isset($this->data['status'])) {
            return null;
        }

        return is_int($this->data['status']) ? $this->data['status'] : null;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function body(): array|string|null
    {
        if (!is_array($this->data) || !isset($this->data['body'])) {
            return null;
        }

        $body = $this->data['body'];

        if (is_array($body)) {
            return $body;
        }

        return is_string($body) ? $body : null;
    }
}
