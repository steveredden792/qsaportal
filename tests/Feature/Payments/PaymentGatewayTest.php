<?php

use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use App\Payments\WebhookEvent;

it('binds the fake gateway when no stripe secret is configured', function () {
    config(['cashier.secret' => null]);

    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('binds the stripe gateway when a secret is configured', function () {
    config(['cashier.secret' => 'sk_test_dummy']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(StripeGateway::class);
});

it('resolves the same fake instance every time', function () {
    config(['cashier.secret' => null]);

    expect(app(PaymentGateway::class))->toBe(app(PaymentGateway::class));
});

it('returns a hand-crafted webhook event from the fake', function () {
    $fake = new FakePaymentGateway();
    $fake->nextWebhookEvent = new WebhookEvent('checkout.session.completed', 42, 'pi_123');

    $event = $fake->webhookEvent(request());

    expect($event->type)->toBe('checkout.session.completed')
        ->and($event->orderId)->toBe(42)
        ->and($event->paymentIntentId)->toBe('pi_123');
});
