<?php

namespace App\Payments;

/**
 * Internal outcome produced only after an external provider's signed,
 * server-to-server transaction check. Never hydrate this from user input.
 * No production adapter is currently installed.
 */
final readonly class VerifiedProviderReceipt
{
    public function __construct(
        public string $provider,
        public string $reference,
        public string $currency,
        public string $amount,
        public string $orderNumber,
    ) {}
}
