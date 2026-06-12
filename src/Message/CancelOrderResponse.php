<?php

declare(strict_types=1);

namespace Omniship\MNG\Message;

/**
 * Response semantics are identical to CancelShipmentResponse: any 2xx means
 * cancelled, otherwise the body carries an error message.
 */
class CancelOrderResponse extends CancelShipmentResponse
{
}
