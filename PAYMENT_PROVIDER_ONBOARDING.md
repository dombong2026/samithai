# Samith AI — verified payment integration gate

No real merchant provider, payment webhook or live checkout exists in this release. The internal VerifiedPaymentFulfillment service is not proof of provider verification.

Before enabling payments, obtain authorized merchant eligibility and a provider's *official* API and signed webhook documentation. Implement provider-specific signature, timestamp, order-reference, merchant-ID, amount and currency verification server-to-server, then call the internal fulfillment service only after settlement confirmation. Reject wrong signatures, stale/replayed events, partial/failed payments and incorrect totals. Record idempotent transactions, taxes, refunds, disputes, payouts, reconciliation, and audit events. Never store OTPs, raw card data or banking credentials.

Acceptance must demonstrate: invalid signatures rejected, incorrect amounts rejected, a verified settlement grants entitlement once, duplicates grant no additional access, out-of-order webhooks do not grant access, refunds/chargebacks follow reviewed policy, and creator payouts reconcile. Provider onboarding, sandbox test evidence, taxation and legal compliance must be signed off separately.

SAMITH_PAYMENTS_ENABLED defaults to false. Do not turn it on merely because unit tests pass.
