<?php

namespace App\Payments;

final class WebhookEvent
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $orderId,
        public readonly ?string $paymentIntentId,
    ) {
    }
}
