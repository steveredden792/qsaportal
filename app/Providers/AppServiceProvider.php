<?php

namespace App\Providers;

use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use App\Support\Basket;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function () {
            return config('cashier.secret')
                ? new StripeGateway()
                : new FakePaymentGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Carry a guest's session cart into their account when they log in or register.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof \App\Models\User) {
                Basket::mergeIntoUser($event->user);
            }
        });

        // Demo only: auto-verify new registrations so the register -> buy flow
        // works without a real mailbox. Never enable in production.
        if (config('demo.instant_fulfil')) {
            Event::listen(Registered::class, function (Registered $event): void {
                if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {
                    $event->user->markEmailAsVerified();
                }
            });
        }
    }
}
