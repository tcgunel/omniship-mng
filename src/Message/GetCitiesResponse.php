<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ResponseInterface;

class GetCitiesResponse extends AbstractResponse implements ResponseInterface
{
    public function isSuccessful(): bool
    {
        $status = $this->httpStatus();

        return $status !== null && $status >= 200 && $status < 300;
    }

    public function getMessage(): ?string
    {
        return null;
    }

    public function getCode(): ?string
    {
        $status = $this->httpStatus();

        return $status === null ? null : (string) $status;
    }

    /**
     * @return list<array{code: int, name: string}>
     */
    public function getCities(): array
    {
        $body = $this->body();
        if (!is_array($body)) {
            return [];
        }

        $cities = [];
        foreach ($body as $entry) {
            if (!is_array($entry) || !isset($entry['code'], $entry['name'])) {
                continue;
            }
            $cities[] = [
                'code' => (int) $entry['code'],
                'name' => (string) $entry['name'],
            ];
        }

        return $cities;
    }

    /**
     * @return array<int|string, mixed>|null
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
