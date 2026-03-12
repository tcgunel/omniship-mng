<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractResponse implements CancelResponse
{
    public function isSuccessful(): bool
    {
        return $this->getResult() === '1';
    }

    public function isCancelled(): bool
    {
        return $this->isSuccessful();
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
        return $this->getResult();
    }

    private function getResult(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['Result'])) {
            return null;
        }

        return (string) $this->data['Result'];
    }
}
