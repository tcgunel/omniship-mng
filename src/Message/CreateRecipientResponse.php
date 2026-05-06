<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ResponseInterface;

class CreateRecipientResponse extends AbstractResponse implements ResponseInterface
{
    public function isSuccessful(): bool
    {
        $status = $this->httpStatus();

        return $status !== null && $status >= 200 && $status < 300;
    }

    public function getMessage(): ?string
    {
        $body = $this->body();

        return is_array($body) ? CreateShipmentResponse::extractErrorMessage($body) : null;
    }

    public function getCode(): ?string
    {
        $status = $this->httpStatus();

        return $status === null ? null : (string) $status;
    }

    public function getShipperBranchCode(): ?string
    {
        $body = $this->body();

        if (is_array($body) && isset($body['shipperBranchCode'])) {
            return (string) $body['shipperBranchCode'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function body(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['body'])) {
            return null;
        }

        return is_array($this->data['body']) ? $this->data['body'] : null;
    }

    private function httpStatus(): ?int
    {
        if (!is_array($this->data) || !isset($this->data['status'])) {
            return null;
        }

        return is_int($this->data['status']) ? $this->data['status'] : null;
    }
}
