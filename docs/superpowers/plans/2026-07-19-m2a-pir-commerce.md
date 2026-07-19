# M2a PIR Commerce Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** PIR single purchases with a basket: add-to-basket, one hosted Stripe checkout per basket, webhook-driven fulfilment into 12-month entitlements, entitlement-gated downloads, a "My reports" dashboard, and per-item admin refunds — per `docs/superpowers/specs/2026-07-19-m2-pir-commerce-design.md`.

**Architecture:** Laravel Cashier behind an `App\Payments\PaymentGateway` interface (`StripeGateway` when `cashier.secret` is configured, `FakePaymentGateway` otherwise — no Stripe keys exist yet, so the fake also powers local dev). New models `BasketItem`, `Order`, `OrderItem`, `Entitlement`; fulfilment happens **only** in the webhook handler, idempotently, one entitlement per order item. `AccessService` (the M1 seam) gains the entitlement rule.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Livewire 3, Filament v4, laravel/cashier (installed in Task 1), MySQL, S3 via flysystem (`Storage::fake('s3')` in tests).

## Global Constraints

- Work on branch `m2a-pir-commerce` (create from `main` before Task 1).
- Test DB is `qsa_portal_test` (phpunit.xml). Run the suite with `php artisan test` — expect ~2–3 min on the `/mnt/c` mount.
- All prices in pence (integers). PIR single price comes from `Pricing::for('pir', 'single')` (`config/pricing.php`, currently 2500 — dummy pending client confirmation).
- Order statuses: `pending → paid → fulfilled`, or `refunded` (only when every item is refunded). `paid` is transient inside the fulfilment transaction. No other status strings.
- **Active** entitlement = `revoked_at IS NULL AND expires_at > now()`. Entitlement window = fulfilment time + 12 months.
- Fulfilment happens only via the webhook; the success page never grants anything.
- The full suite must pass with **no Stripe credentials configured**.
- Every task ends with the **full suite green** before its commit.

## File Structure (end state)

```
app/Payments/{PaymentGateway,WebhookEvent,StripeGateway,FakePaymentGateway}.php
app/Models/{BasketItem,Order,OrderItem,Entitlement}.php   (+ User gains Billable + relations)
app/Services/{AccessService,FulfilOrder,RefundOrderItem}.php
app/Http/Controllers/{CheckoutController,StripeWebhookController,ReportController}.php
app/Livewire/{AddToBasket,BasketBadge,BasketPage,MyReports}.php
app/Filament/Resources/OrderResource.php (+ Pages/{ListOrders,ViewOrder}, RelationManagers/ItemsRelationManager)
database/migrations/2026_07_19_0000{01..04}_create_{basket_items,orders,order_items,entitlements}_table.php
resources/views/livewire/{add-to-basket,basket-badge,basket-page,my-reports}.blade.php
resources/views/checkout/success.blade.php
routes/web.php                       basket.show, checkout.store, checkout.success, my-reports, webhooks.stripe
bootstrap/app.php                    CSRF exemption for webhooks/stripe
```

---

### Task 1: Cashier install + payment gateway boundary

Installs Cashier (needed for Stripe SDK + M3 subscriptions), adds `Billable` to `User`, and creates the gateway interface with both implementations. The container binds `FakePaymentGateway` whenever `cashier.secret` is empty — which is every environment today.

**Files:**
- Create: `app/Payments/PaymentGateway.php`, `app/Payments/WebhookEvent.php`, `app/Payments/StripeGateway.php`, `app/Payments/FakePaymentGateway.php`
- Modify: `composer.json` (via composer), `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `.env.example`
- Test: `tests/Feature/Payments/PaymentGatewayTest.php`

**Interfaces:**
- Produces: `App\Payments\PaymentGateway` with `checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string`, `webhookEvent(Request $request): ?WebhookEvent`, `refundItem(OrderItem $item): void`; `WebhookEvent` DTO (`type: string`, `orderId: ?int`, `paymentIntentId: ?string`); `FakePaymentGateway` public test hooks: `array $checkoutSessions`, `array $refundedItems`, `bool $failRefunds`, `?WebhookEvent $nextWebhookEvent`. Tasks 5, 6 and 8 consume exactly these. (`Order`/`OrderItem` type hints reference Task 2's models — the two tasks must be merged at the type level: Task 1's gateway files compile because PHP resolves the class names lazily; the gateway tests that touch orders live in later tasks.)

- [ ] **Step 1: Install Cashier and publish its migrations**

```bash
composer require laravel/cashier
php artisan vendor:publish --tag="cashier-migrations"
```

Append to `.env.example`:

```
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

- [ ] **Step 2: Write the failing gateway tests**

`tests/Feature/Payments/PaymentGatewayTest.php`:

```php
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
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test tests/Feature/Payments/PaymentGatewayTest.php`
Expected: FAIL — `App\Payments\PaymentGateway` not found.

- [ ] **Step 4: Implement the DTO, interface and both gateways**

`app/Payments/WebhookEvent.php`:

```php
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
```

`app/Payments/PaymentGateway.php`:

```php
<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Create a hosted checkout session for the order (one line per order item); returns the redirect URL. */
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string;

    /** Verify and parse an incoming webhook request; null when the signature or payload is invalid. */
    public function webhookEvent(Request $request): ?WebhookEvent;

    /** Refund one order item's amount against the order's payment. Throws on gateway failure. */
    public function refundItem(OrderItem $item): void;
}
```

`app/Payments/FakePaymentGateway.php`:

```php
<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use RuntimeException;

class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, Order> */
    public array $checkoutSessions = [];

    /** @var array<int, OrderItem> */
    public array $refundedItems = [];

    public bool $failRefunds = false;

    public ?WebhookEvent $nextWebhookEvent = null;

    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string
    {
        $this->checkoutSessions[] = $order;
        $order->update(['stripe_checkout_session_id' => 'cs_fake_'.$order->id]);

        return 'https://checkout.stripe.test/session/cs_fake_'.$order->id;
    }

    public function webhookEvent(Request $request): ?WebhookEvent
    {
        return $this->nextWebhookEvent;
    }

    public function refundItem(OrderItem $item): void
    {
        if ($this->failRefunds) {
            throw new RuntimeException('Fake gateway: refund failed');
        }

        $this->refundedItems[] = $item;
    }
}
```

`app/Payments/StripeGateway.php` (exercised only when real keys exist; kept thin):

```php
<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string
    {
        $session = Cashier::stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $order->user->createOrGetStripeCustomer()->id,
            'line_items' => $order->items->map(fn (OrderItem $item) => [
                'price_data' => [
                    'currency' => $order->currency,
                    'unit_amount' => $item->amount_pence,
                    'product_data' => ['name' => $item->issue->report->name],
                ],
                'quantity' => 1,
            ])->values()->all(),
            'metadata' => ['order_id' => $order->id],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    public function webhookEvent(Request $request): ?WebhookEvent
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('cashier.webhook.secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }

        $object = $event->data->object;

        return new WebhookEvent(
            type: $event->type,
            orderId: isset($object->metadata->order_id) ? (int) $object->metadata->order_id : null,
            paymentIntentId: $object->payment_intent ?? null,
        );
    }

    public function refundItem(OrderItem $item): void
    {
        Cashier::stripe()->refunds->create([
            'payment_intent' => $item->order->stripe_payment_intent_id,
            'amount' => $item->amount_pence,
        ]);
    }
}
```

`app/Providers/AppServiceProvider.php` — full new content:

```php
<?php

namespace App\Providers;

use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
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
        //
    }
}
```

`app/Models/User.php` — add the trait (imports + use line):

```php
use Laravel\Cashier\Billable;
```

and change the class body's trait line to:

```php
    use Billable, HasFactory, Notifiable;
```

- [ ] **Step 5: Migrate (Cashier columns) and run the tests**

Run: `php artisan migrate && php artisan test tests/Feature/Payments/PaymentGatewayTest.php`
Expected: PASS.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: install Cashier and add the payment gateway boundary with fake for keyless envs"
```

---

### Task 2: Commerce models, migrations and factories

`BasketItem`, `Order`, `OrderItem`, `Entitlement` with factories and model tests. No behaviour beyond relations, scopes and the entitlement status helper.

**Files:**
- Create: `database/migrations/2026_07_19_000001_create_basket_items_table.php`, `database/migrations/2026_07_19_000002_create_orders_table.php`, `database/migrations/2026_07_19_000003_create_order_items_table.php`, `database/migrations/2026_07_19_000004_create_entitlements_table.php`, `app/Models/BasketItem.php`, `app/Models/Order.php`, `app/Models/OrderItem.php`, `app/Models/Entitlement.php`, `database/factories/BasketItemFactory.php`, `database/factories/OrderFactory.php`, `database/factories/OrderItemFactory.php`, `database/factories/EntitlementFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Models/CommerceModelsTest.php`

**Interfaces:**
- Consumes: `Report`/`Issue`/`User` models (existing), `ReportFactory::pir()`.
- Produces: `BasketItem` (`user_id`, `report_id`; unique pair); `Order` (`user_id`, `total_pence`, `currency` default `'gbp'`, `status` default `'pending'`, `stripe_checkout_session_id`, `stripe_payment_intent_id`; `user(): BelongsTo`, `items(): HasMany`); `OrderItem` (`order_id`, `issue_id`, `amount_pence`, `refunded_at`; `order(): BelongsTo`, `issue(): BelongsTo`, `entitlement(): HasOne`); `Entitlement` (`user_id`, `issue_id`, `order_item_id`, `expires_at`, `revoked_at`; `scopeActive(Builder): Builder`, `isActive(): bool`, `status(): string` returning `'active'|'expiring'|'expired'`); `User::basketItems()/orders()/entitlements(): HasMany`; `EntitlementFactory` states `expired()`, `revoked()`, `expiring()`. All later tasks rely on exactly these names.

- [ ] **Step 1: Write the failing model tests**

`tests/Feature/Models/CommerceModelsTest.php`:

```php
<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces one basket line per user and report', function () {
    $user = User::factory()->create();
    $report = Report::factory()->pir()->create();

    BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);

    expect(fn () => BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]))
        ->toThrow(QueryException::class);
});

it('creates an order with pending status and gbp currency by default', function () {
    $order = Order::factory()->create();

    expect($order->status)->toBe('pending')
        ->and($order->currency)->toBe('gbp')
        ->and($order->user)->toBeInstanceOf(User::class);
});

it('links order items to their order, issue and entitlement', function () {
    $item = OrderItem::factory()->create();
    $entitlement = Entitlement::factory()->create(['order_item_id' => $item->id]);

    expect($item->order)->toBeInstanceOf(Order::class)
        ->and($item->issue->id)->toBe($item->issue_id)
        ->and($item->entitlement->id)->toBe($entitlement->id);
});

it('scopes active entitlements excluding revoked and expired ones', function () {
    $user = User::factory()->create();
    $active = Entitlement::factory()->create(['user_id' => $user->id]);
    Entitlement::factory()->expired()->create(['user_id' => $user->id]);
    Entitlement::factory()->revoked()->create(['user_id' => $user->id]);

    expect($user->entitlements()->active()->pluck('id')->all())->toBe([$active->id]);
});

it('reports entitlement status with a 30-day expiring boundary', function () {
    expect(Entitlement::factory()->create()->status())->toBe('active')
        ->and(Entitlement::factory()->create(['expires_at' => now()->addDays(31)])->status())->toBe('active')
        ->and(Entitlement::factory()->create(['expires_at' => now()->addDays(30)])->status())->toBe('expiring')
        ->and(Entitlement::factory()->expiring()->create()->status())->toBe('expiring')
        ->and(Entitlement::factory()->expired()->create()->status())->toBe('expired')
        ->and(Entitlement::factory()->revoked()->create()->status())->toBe('expired');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/Models/CommerceModelsTest.php`
Expected: FAIL — `BasketItem` not found.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_19_000001_create_basket_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('basket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_items');
    }
};
```

`database/migrations/2026_07_19_000002_create_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_pence');
            $table->string('currency')->default('gbp');
            $table->string('status')->default('pending'); // pending|paid|fulfilled|refunded
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

`database/migrations/2026_07_19_000003_create_order_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained();
            $table->unsignedInteger('amount_pence');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

`database/migrations/2026_07_19_000004_create_entitlements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained();
            $table->foreignId('order_item_id')->constrained();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
```

- [ ] **Step 4: Create the models**

`app/Models/BasketItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketItem extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'report_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
```

`app/Models/Order.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'total_pence', 'currency', 'status',
        'stripe_checkout_session_id', 'stripe_payment_intent_id',
    ];

    protected $attributes = [
        'currency' => 'gbp',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return ['total_pence' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

`app/Models/OrderItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'issue_id', 'amount_pence', 'refunded_at'];

    protected function casts(): array
    {
        return [
            'amount_pence' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }
}
```

`app/Models/Entitlement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entitlement extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'issue_id', 'order_item_id', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** Customer-facing status chip: active | expiring (≤30 days left) | expired (incl. revoked). */
    public function status(): string
    {
        if (! $this->isActive()) {
            return 'expired';
        }

        return $this->expires_at->lte(now()->addDays(30)) ? 'expiring' : 'active';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
```

`app/Models/User.php` — add relation methods (plus `use Illuminate\Database\Eloquent\Relations\HasMany;`):

```php
    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
```

- [ ] **Step 5: Create the factories**

`database/factories/BasketItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BasketItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'report_id' => Report::factory()->pir(),
        ];
    }
}
```

`database/factories/OrderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_pence' => 2500,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn () => [
            'status' => 'fulfilled',
            'stripe_payment_intent_id' => 'pi_fake_'.fake()->unique()->numberBetween(1000, 999999),
        ]);
    }
}
```

`database/factories/OrderItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'issue_id' => Issue::factory(),
            'amount_pence' => 2500,
        ];
    }
}
```

`database/factories/EntitlementFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntitlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issue_id' => Issue::factory(),
            'order_item_id' => OrderItem::factory(),
            'expires_at' => now()->addMonths(12),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expiring(): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays(10)]);
    }
}
```

- [ ] **Step 6: Migrate and run the tests, then the full suite**

Run: `php artisan migrate && php artisan test tests/Feature/Models/CommerceModelsTest.php && php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add basket, order, order item and entitlement models"
```

---

### Task 3: Entitlement-gated downloads (AccessService)

The M1 seam: `ReportPdf`/`Dataset` assets open only with an active entitlement for the asset's issue. Teasers unchanged.

**Files:**
- Modify: `app/Services/AccessService.php`
- Test: `tests/Feature/AccessServiceTest.php` (extend the existing file; if the current tests live under a different name, find them with `grep -rln AccessService tests/` and extend that file)

**Interfaces:**
- Consumes: `Entitlement::scopeActive()` (Task 2), `User::entitlements()` (Task 2).
- Produces: `AccessService::canAccess(?User $user, Asset $asset): bool` — signature unchanged; Tasks 4/7 rely on the rule for download links.

- [ ] **Step 1: Write the failing tests**

Add to the AccessService test file:

```php
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\User;
use App\Services\AccessService;

it('grants report pdf access with an active entitlement for the issue', function () {
    $user = User::factory()->create();
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);
    Entitlement::factory()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);

    expect((new AccessService)->canAccess($user, $asset))->toBeTrue();
});

it('denies report pdf access without an entitlement, or with an expired or revoked one', function () {
    $user = User::factory()->create();
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();

    Entitlement::factory()->expired()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();

    Entitlement::factory()->revoked()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();
});

it('denies access to an entitlement for a different issue', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['type' => AssetType::ReportPdf]);
    Entitlement::factory()->create(['user_id' => $user->id]);

    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=AccessService`
Expected: FAIL — ReportPdf access currently always false, so the *grants* test fails.

- [ ] **Step 3: Implement the entitlement rule**

`app/Services/AccessService.php` — full new content:

```php
<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;

class AccessService
{
    /**
     * Whether the user may open the asset.
     *
     * - Teaser/sample assets are free but registration-gated: any authenticated user.
     * - Report PDFs and datasets require an active entitlement for the asset's issue.
     */
    public function canAccess(?User $user, Asset $asset): bool
    {
        if ($user === null) {
            return false;
        }

        if ($asset->type === AssetType::Teaser) {
            return true;
        }

        return $user->entitlements()
            ->active()
            ->where('issue_id', $asset->issue_id)
            ->exists();
    }
}
```

- [ ] **Step 4: Run the tests, then the full suite**

Run: `php artisan test --filter=AccessService && php artisan test`
Expected: PASS (existing teaser tests keep passing).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: gate report pdf downloads behind active entitlements"
```

---

### Task 4: Basket UI — add-to-basket, badge, basket page

Livewire components for the basket, wired into the catalogue, detail page and nav. The basket page's Checkout button is a disabled placeholder until Task 5 adds the route.

**Files:**
- Create: `app/Livewire/AddToBasket.php`, `app/Livewire/BasketBadge.php`, `app/Livewire/BasketPage.php`, `resources/views/livewire/add-to-basket.blade.php`, `resources/views/livewire/basket-badge.blade.php`, `resources/views/livewire/basket-page.blade.php`
- Modify: `routes/web.php`, `resources/views/components/public.blade.php`, `resources/views/livewire/pir-catalogue.blade.php`, `app/Livewire/PirCatalogue.php` (eager-load), `resources/views/reports/pir-detail.blade.php`, `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/BasketTest.php`

**Interfaces:**
- Consumes: `BasketItem`, `Entitlement::scopeActive()` (Task 2), `Pricing::for('pir', 'single')`, `Money::format()` (existing).
- Produces: route `basket.show` at `/basket` (`auth`+`verified`); Livewire components `App\Livewire\AddToBasket` (prop `Report $report`, action `add()`), `App\Livewire\BasketBadge` (listens `basket-updated`), `App\Livewire\BasketPage` (action `remove(int $basketItemId)`). All dispatch/listen the `basket-updated` Livewire event. Task 5 replaces the placeholder Checkout button with a real form.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/BasketTest.php`:

```php
<?php

use App\Livewire\AddToBasket;
use App\Livewire\BasketPage;
use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function pirWithIssue(): Report
{
    $report = Report::factory()->pir()->create();
    Issue::factory()->for($report)->create(['is_current' => true]);

    return $report->fresh();
}

it('adds a report to the basket idempotently', function () {
    $report = pirWithIssue();

    Livewire::test(AddToBasket::class, ['report' => $report])->call('add');
    Livewire::test(AddToBasket::class, ['report' => $report])->call('add');

    expect(BasketItem::where('user_id', $this->user->id)->count())->toBe(1);
});

it('shows In basket after adding and hides the button when the current issue is owned', function () {
    $report = pirWithIssue();

    Livewire::test(AddToBasket::class, ['report' => $report])
        ->assertSee('Add to basket')
        ->call('add')
        ->assertSee('In basket');

    Entitlement::factory()->create([
        'user_id' => $this->user->id,
        'issue_id' => $report->currentIssue->id,
    ]);

    Livewire::test(AddToBasket::class, ['report' => $report])
        ->assertDontSee('Add to basket')
        ->assertDontSee('In basket');
});

it('lists basket lines with total and removes lines', function () {
    $reports = collect([pirWithIssue(), pirWithIssue()]);
    $reports->each(fn (Report $r) => BasketItem::create([
        'user_id' => $this->user->id,
        'report_id' => $r->id,
    ]));

    $line = BasketItem::where('report_id', $reports[0]->id)->first();

    Livewire::test(BasketPage::class)
        ->assertSee($reports[0]->name)
        ->assertSee($reports[1]->name)
        ->assertSee('£50.00')
        ->call('remove', $line->id)
        ->assertDontSee($reports[0]->name)
        ->assertSee('£25.00');
});

it('cannot remove another users basket line', function () {
    $other = BasketItem::factory()->create();

    Livewire::test(BasketPage::class)->call('remove', $other->id);

    expect(BasketItem::whereKey($other->id)->exists())->toBeTrue();
});

it('redirects guests away from the basket page', function () {
    auth()->logout();

    $this->get(route('basket.show'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/BasketTest.php`
Expected: FAIL — `App\Livewire\AddToBasket` not found.

- [ ] **Step 3: Implement the components**

`app/Livewire/AddToBasket.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\AssetType;
use App\Models\BasketItem;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AddToBasket extends Component
{
    public Report $report;

    public function add(): void
    {
        BasketItem::firstOrCreate([
            'user_id' => auth()->id(),
            'report_id' => $this->report->id,
        ]);

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $issue = $this->report->currentIssue()->with('assets')->first();

        $ownedPdf = null;
        if ($issue !== null && auth()->user()->entitlements()->active()->where('issue_id', $issue->id)->exists()) {
            $ownedPdf = $issue->assets->firstWhere('type', AssetType::ReportPdf);
        }

        return view('livewire.add-to-basket', [
            'owned' => $issue !== null && $ownedPdf !== null,
            'ownedPdf' => $ownedPdf,
            'inBasket' => BasketItem::where('user_id', auth()->id())
                ->where('report_id', $this->report->id)
                ->exists(),
            'purchasable' => $issue !== null,
        ]);
    }
}
```

`resources/views/livewire/add-to-basket.blade.php`:

```blade
<span>
    @if ($owned)
        <a href="{{ route('assets.download', $ownedPdf) }}" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white">Download</a>
    @elseif ($inBasket)
        <a href="{{ route('basket.show') }}" class="rounded border border-brand px-4 py-2 text-sm font-medium text-brand">In basket</a>
    @elseif ($purchasable)
        <button wire:click="add" class="rounded bg-brand px-4 py-2 text-sm font-medium text-white">Add to basket</button>
    @endif
</span>
```

(When the current issue is owned but its ReportPdf asset is missing, nothing renders — an import always attaches the PDF, so this is a defensive blank, not a state.)

`app/Livewire/BasketBadge.php`:

```php
<?php

namespace App\Livewire;

use App\Models\BasketItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class BasketBadge extends Component
{
    #[On('basket-updated')]
    public function refresh(): void
    {
        // Re-render with a fresh count.
    }

    public function render(): View
    {
        return view('livewire.basket-badge', [
            'count' => BasketItem::where('user_id', auth()->id())->count(),
        ]);
    }
}
```

`resources/views/livewire/basket-badge.blade.php`:

```blade
<a href="{{ route('basket.show') }}" class="relative hover:underline">
    Basket
    @if ($count > 0)
        <span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-white">{{ $count }}</span>
    @endif
</a>
```

`app/Livewire/BasketPage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\BasketItem;
use App\Support\Pricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.public')]
class BasketPage extends Component
{
    public function remove(int $basketItemId): void
    {
        BasketItem::where('user_id', auth()->id())->whereKey($basketItemId)->delete();

        $this->dispatch('basket-updated');
    }

    public function render(): View
    {
        $items = BasketItem::with('report')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        $price = Pricing::for('pir', 'single');

        return view('livewire.basket-page', [
            'items' => $items,
            'price' => $price,
            'total' => $price * $items->count(),
        ]);
    }
}
```

`resources/views/livewire/basket-page.blade.php`:

```blade
<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">Your basket</h1>

    @if (session('error'))
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($items->isEmpty())
        <p class="text-gray-600">Your basket is empty.
            <a href="{{ route('catalogue.pir') }}" class="text-brand hover:underline">Browse the PIR database</a>.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($items as $item)
                        <tr wire:key="basket-{{ $item->id }}">
                            <td class="px-4 py-3 font-medium">{{ $item->report->name }}</td>
                            <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($price) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="remove({{ $item->id }})" class="text-sm text-red-600 hover:underline">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">{{ \App\Support\Money::format($total) }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6">
            {{-- Replaced with the real checkout form in the checkout task --}}
            <button class="rounded bg-brand px-5 py-2 font-medium text-white" disabled title="Checkout arrives next">Checkout</button>
        </div>
    @endif
</div>
```

- [ ] **Step 4: Wire routes, nav, catalogue and detail page**

`routes/web.php` — add inside the existing `['auth', 'verified']` group:

```php
    Route::get('/basket', \App\Livewire\BasketPage::class)->name('basket.show');
```

`resources/views/components/public.blade.php` — inside the `@auth` block of the nav, before the Dashboard link:

```blade
                    <livewire:basket-badge />
```

`resources/views/livewire/pir-catalogue.blade.php` — in the actions cell (the `<td class="px-4 py-2 text-right">` holding "View report"), add after the anchor:

```blade
                            <livewire:add-to-basket :report="$charity->report" :key="'atb-'.$charity->id" />
```

`app/Livewire/PirCatalogue.php` — change the eager-load so the component has what it needs:

```php
            ->with('report:id,charity_id,type,slug,name')
```

`app/Http/Controllers/ReportController.php` — full new `show()`:

```php
    public function show(Request $request, Report $report): View
    {
        $report->load('charity', 'currentIssue.assets');
        $teaser = $report->currentIssue?->assets->firstWhere('type', AssetType::Teaser);

        if ($report->type !== ReportType::PIR) {
            abort(404);
        }

        $ownedEntitlements = $request->user()->entitlements()
            ->active()
            ->whereHas('issue', fn ($q) => $q->where('report_id', $report->id))
            ->with('issue.assets')
            ->get();

        return view('reports.pir-detail', [
            'report' => $report,
            'charity' => $report->charity,
            'issue' => $report->currentIssue,
            'teaser' => $teaser,
            'price' => Pricing::for('pir', 'single'),
            'ownedEntitlements' => $ownedEntitlements,
        ]);
    }
```

(Add `use Illuminate\Http\Request;` to the imports.)

`resources/views/reports/pir-detail.blade.php` — replace the buy block (the `<div class="mt-6 flex items-center gap-4">…</div>`) with:

```blade
    <div class="mt-6 flex items-center gap-4">
        <span class="text-2xl font-bold">{{ \App\Support\Money::format($price) }}</span>
        <livewire:add-to-basket :report="$report" />
    </div>

    @if ($ownedEntitlements->isNotEmpty())
        <div class="mt-4 text-sm text-gray-700">
            <h2 class="font-semibold">Your purchased issues</h2>
            <ul class="mt-1 space-y-1">
                @foreach ($ownedEntitlements as $entitlement)
                    @php $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf); @endphp
                    <li>
                        {{ $entitlement->issue->version_label }} —
                        @if ($pdf)
                            <a href="{{ route('assets.download', $pdf) }}" class="text-brand hover:underline">Download</a>
                        @endif
                        <span class="text-gray-500">(expires {{ $entitlement->expires_at->format('j M Y') }})</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
```

(The old `@auth` guard around the buy button goes — the whole page already sits behind `auth`+`verified`.)

- [ ] **Step 5: Run the tests, then the full suite**

Run: `php artisan test tests/Feature/BasketTest.php && php artisan test`
Expected: PASS — including the existing PirCatalogue/ReportDetail tests against the modified views.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add basket with add-to-basket buttons, nav badge and basket page"
```

---

### Task 5: Checkout — order creation and gateway redirect

`POST /checkout` turns the basket into a `pending` Order (items frozen to current issues) and redirects to the gateway's hosted checkout URL. Stale lines bounce back with a notice.

**Files:**
- Create: `app/Http/Controllers/CheckoutController.php`
- Modify: `routes/web.php`, `resources/views/livewire/basket-page.blade.php` (real Checkout form)
- Test: `tests/Feature/CheckoutTest.php`

**Interfaces:**
- Consumes: `PaymentGateway::checkoutUrl()` (Task 1), `BasketItem`/`Order`/`OrderItem`/`Entitlement::scopeActive()` (Task 2), `Pricing::for('pir', 'single')`.
- Produces: routes `checkout.store` (`POST /checkout`) and `checkout.success` (`GET /checkout/success/{order}`, view added in Task 6 — this task registers only `checkout.store`; the success route name is referenced by the controller so both are registered here, with the success action returning the Task 6 view). Task 6 consumes `Order` rows in status `pending`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/CheckoutTest.php`:

```php
<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function basketReport(User $user): Report
{
    $report = Report::factory()->pir()->create();
    Issue::factory()->for($report)->create(['is_current' => true]);
    BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);

    return $report->fresh();
}

it('creates a pending order frozen to current issues and redirects to the gateway', function () {
    $a = basketReport($this->user);
    $b = basketReport($this->user);

    $response = $this->post(route('checkout.store'));

    $order = Order::sole();
    $response->assertRedirect('https://checkout.stripe.test/session/cs_fake_'.$order->id);

    expect($order->status)->toBe('pending')
        ->and($order->total_pence)->toBe(5000)
        ->and($order->items()->pluck('issue_id')->sort()->values()->all())
        ->toBe(collect([$a->currentIssue->id, $b->currentIssue->id])->sort()->values()->all())
        ->and($this->gateway->checkoutSessions)->toHaveCount(1);

    // Basket is NOT cleared at checkout — only on fulfilment.
    expect(BasketItem::where('user_id', $this->user->id)->count())->toBe(2);
});

it('rejects an empty basket', function () {
    $this->post(route('checkout.store'))
        ->assertRedirect(route('basket.show'));

    expect(Order::count())->toBe(0);
});

it('bounces back naming stale lines without creating anything', function () {
    $owned = basketReport($this->user);
    Entitlement::factory()->create([
        'user_id' => $this->user->id,
        'issue_id' => $owned->currentIssue->id,
    ]);
    basketReport($this->user);

    $response = $this->post(route('checkout.store'));

    $response->assertRedirect(route('basket.show'))
        ->assertSessionHas('error', fn (string $msg) => str_contains($msg, $owned->name));

    expect(Order::count())->toBe(0)
        ->and($this->gateway->checkoutSessions)->toBe([]);
});

it('treats a report without a current issue as stale', function () {
    $report = basketReport($this->user);
    $report->currentIssue->update(['is_current' => false]);

    $this->post(route('checkout.store'))->assertRedirect(route('basket.show'));

    expect(Order::count())->toBe(0);
});

it('requires authentication', function () {
    auth()->logout();

    $this->post(route('checkout.store'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/CheckoutTest.php`
Expected: FAIL — route `checkout.store` not defined.

- [ ] **Step 3: Implement the controller and routes**

`app/Http/Controllers/CheckoutController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\PaymentGateway;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function store(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $user = $request->user();
        $basketItems = $user->basketItems()->with('report.currentIssue')->get();

        if ($basketItems->isEmpty()) {
            return redirect()->route('basket.show')->with('error', 'Your basket is empty.');
        }

        $price = Pricing::for('pir', 'single');
        $stale = [];
        $issues = [];

        foreach ($basketItems as $basketItem) {
            $report = $basketItem->report;
            $issue = $report?->currentIssue;

            if ($report === null || $report->type !== ReportType::PIR || $issue === null) {
                $stale[] = $report?->name ?? 'A removed report';

                continue;
            }

            if ($user->entitlements()->active()->where('issue_id', $issue->id)->exists()) {
                $stale[] = $report->name;

                continue;
            }

            $issues[] = $issue;
        }

        if ($stale !== []) {
            return redirect()->route('basket.show')->with(
                'error',
                'Some basket items need attention: '.implode(', ', $stale).'. Remove them to continue.',
            );
        }

        $order = DB::transaction(function () use ($user, $issues, $price) {
            $order = Order::create([
                'user_id' => $user->id,
                'total_pence' => $price * count($issues),
            ]);

            foreach ($issues as $issue) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'issue_id' => $issue->id,
                    'amount_pence' => $price,
                ]);
            }

            return $order;
        });

        return redirect()->away($gateway->checkoutUrl(
            $order->load('items.issue.report'),
            route('checkout.success', $order),
            route('basket.show'),
        ));
    }

    public function success(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('checkout.success', ['order' => $order]);
    }
}
```

`routes/web.php` — add inside the `['auth', 'verified']` group:

```php
    Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success/{order}', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
```

Create `resources/views/checkout/success.blade.php` (plain text for "My reports" — the route only exists from Task 7, which swaps the text for a link):

```blade
<x-public title="Checkout">
    <h1 class="text-2xl font-bold text-brand">Thank you</h1>
    @if ($order->status === 'fulfilled')
        <p class="mt-4">Payment confirmed — your reports are available in My reports.</p>
    @else
        <p class="mt-4">Payment processing — check back shortly. Your reports will appear in My reports once payment is confirmed.</p>
    @endif
</x-public>
```

(Task 7 swaps the plain text for a link once the route exists.)

`resources/views/livewire/basket-page.blade.php` — replace the placeholder button block with:

```blade
        <div class="mt-6">
            <form method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <button type="submit" class="rounded bg-brand px-5 py-2 font-medium text-white">Checkout</button>
            </form>
        </div>
```

- [ ] **Step 4: Run the tests, then the full suite**

Run: `php artisan test tests/Feature/CheckoutTest.php && php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: create pending orders from the basket and redirect to hosted checkout"
```

---

### Task 6: Webhook fulfilment

The only path that grants access: `POST /webhooks/stripe` → verify → `FulfilOrder` (idempotent, transactional): entitlements per item, basket lines cleared, order `fulfilled`.

**Files:**
- Create: `app/Services/FulfilOrder.php`, `app/Http/Controllers/StripeWebhookController.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (CSRF exemption)
- Test: `tests/Feature/WebhookFulfilmentTest.php`

**Interfaces:**
- Consumes: `PaymentGateway::webhookEvent()` + `FakePaymentGateway::$nextWebhookEvent` (Task 1), `Order`/`OrderItem`/`Entitlement`/`BasketItem` (Task 2), `checkout.success` view (Task 5).
- Produces: `App\Services\FulfilOrder::handle(int $orderId, ?string $paymentIntentId): void`; route `webhooks.stripe` (`POST /webhooks/stripe`, CSRF-exempt). Task 8's refund flow relies on orders reaching `fulfilled` with `stripe_payment_intent_id` set.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/WebhookFulfilmentTest.php`:

```php
<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function pendingOrder(User $user, int $items = 2): Order
{
    $order = Order::factory()->create(['user_id' => $user->id, 'total_pence' => 2500 * $items]);

    for ($i = 0; $i < $items; $i++) {
        $report = Report::factory()->pir()->create();
        $issue = Issue::factory()->for($report)->create(['is_current' => true]);
        OrderItem::factory()->create(['order_id' => $order->id, 'issue_id' => $issue->id]);
        BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);
    }

    return $order->fresh('items');
}

it('fulfils a pending order: entitlements per item, basket cleared, status fulfilled', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    $order->refresh();
    expect($order->status)->toBe('fulfilled')
        ->and($order->stripe_payment_intent_id)->toBe('pi_123')
        ->and(Entitlement::where('user_id', $user->id)->count())->toBe(2)
        ->and(BasketItem::where('user_id', $user->id)->count())->toBe(0);

    $entitlement = Entitlement::first();
    expect($entitlement->expires_at->isAfter(now()->addMonths(11)))->toBeTrue()
        ->and($entitlement->order_item_id)->not->toBeNull();
});

it('ignores duplicate deliveries without creating duplicate entitlements', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');
    $this->postJson(route('webhooks.stripe'))->assertOk();
    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect(Entitlement::count())->toBe(2)
        ->and($order->fresh()->status)->toBe('fulfilled');
});

it('rejects an invalid payload with 400 and writes nothing', function () {
    $this->gateway->nextWebhookEvent = null;

    $this->postJson(route('webhooks.stripe'))->assertStatus(400);

    expect(Entitlement::count())->toBe(0);
});

it('no-ops on an unknown order id with 200', function () {
    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', 999999, 'pi_x');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect(Entitlement::count())->toBe(0);
});

it('ignores unrelated event types', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('invoice.paid', $order->id, 'pi_123');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect($order->fresh()->status)->toBe('pending')
        ->and(Entitlement::count())->toBe(0);
});

it('shows live status on the success page without granting anything', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->actingAs($user)
        ->get(route('checkout.success', $order))
        ->assertOk()
        ->assertSee('Payment processing');

    expect(Entitlement::count())->toBe(0);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');
    $this->postJson(route('webhooks.stripe'));

    $this->actingAs($user)
        ->get(route('checkout.success', $order))
        ->assertSee('Payment confirmed');
});

it('blocks other users from the success page', function () {
    $order = pendingOrder(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('checkout.success', $order))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/WebhookFulfilmentTest.php`
Expected: FAIL — route `webhooks.stripe` not defined.

- [ ] **Step 3: Implement FulfilOrder, the controller, route and CSRF exemption**

`app/Services/FulfilOrder.php`:

```php
<?php

namespace App\Services;

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FulfilOrder
{
    /**
     * Idempotently fulfil a paid order: one entitlement per item (12-month
     * window), clear the purchased reports from the buyer's basket, mark the
     * order fulfilled. Anything but a pending order is a no-op.
     */
    public function handle(int $orderId, ?string $paymentIntentId): void
    {
        DB::transaction(function () use ($orderId, $paymentIntentId) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null || $order->status !== 'pending') {
                return;
            }

            $order->update(['status' => 'paid', 'stripe_payment_intent_id' => $paymentIntentId]);

            $order->load('items.issue');

            foreach ($order->items as $item) {
                Entitlement::create([
                    'user_id' => $order->user_id,
                    'issue_id' => $item->issue_id,
                    'order_item_id' => $item->id,
                    'expires_at' => now()->addMonths(12),
                ]);
            }

            BasketItem::where('user_id', $order->user_id)
                ->whereIn('report_id', $order->items->pluck('issue.report_id'))
                ->delete();

            $order->update(['status' => 'fulfilled']);
        });
    }
}
```

`app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Payments\PaymentGateway;
use App\Services\FulfilOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, FulfilOrder $fulfil): Response
    {
        $event = $gateway->webhookEvent($request);

        if ($event === null) {
            return response('Invalid payload', 400);
        }

        if ($event->type === 'checkout.session.completed' && $event->orderId !== null) {
            $fulfil->handle($event->orderId, $event->paymentIntentId);
        }

        return response('OK');
    }
}
```

`routes/web.php` — add outside every middleware group (webhooks are unauthenticated; the signature is the auth):

```php
Route::post('/webhooks/stripe', \App\Http\Controllers\StripeWebhookController::class)->name('webhooks.stripe');
```

`bootstrap/app.php` — fill the `withMiddleware` closure:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
        ]);
    })
```

- [ ] **Step 4: Run the tests, then the full suite**

Run: `php artisan test tests/Feature/WebhookFulfilmentTest.php && php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: fulfil orders idempotently from the stripe webhook"
```

---

### Task 7: My reports dashboard

`/my-reports`: the user's entitlements with status chips and in-window download buttons. Nav link added; success page links here.

**Files:**
- Create: `app/Livewire/MyReports.php`, `resources/views/livewire/my-reports.blade.php`
- Modify: `routes/web.php`, `resources/views/components/public.blade.php`, `resources/views/checkout/success.blade.php`
- Test: `tests/Feature/MyReportsTest.php`

**Interfaces:**
- Consumes: `Entitlement::status()/isActive()` (Task 2), `assets.download` route + `AccessService` rule (Task 3).
- Produces: route `my-reports` (`GET /my-reports`, `auth`+`verified`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/MyReportsTest.php`:

```php
<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entitledIssue(User $user, array $entitlementAttrs = []): Entitlement
{
    $report = Report::factory()->pir()->create();
    $issue = Issue::factory()->for($report)->create(['is_current' => true]);
    Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    return Entitlement::factory()->create(array_merge([
        'user_id' => $user->id,
        'issue_id' => $issue->id,
    ], $entitlementAttrs));
}

it('lists entitlements with status chips and download links', function () {
    $user = User::factory()->create();
    $active = entitledIssue($user);
    $expiring = entitledIssue($user, ['expires_at' => now()->addDays(10)]);
    $expired = entitledIssue($user, ['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertSee($active->issue->report->name)
        ->assertSee($expiring->issue->report->name)
        ->assertSee($expired->issue->report->name)
        ->assertSee('Active')
        ->assertSee('Expiring soon')
        ->assertSee('Expired');
});

it('only shows the signed-in users entitlements', function () {
    $user = User::factory()->create();
    $other = entitledIssue(User::factory()->create());

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertDontSee($other->issue->report->name);
});

it('redirects guests to login', function () {
    $this->get(route('my-reports'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/MyReportsTest.php`
Expected: FAIL — route `my-reports` not defined.

- [ ] **Step 3: Implement the page**

`app/Livewire/MyReports.php`:

```php
<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.public')]
class MyReports extends Component
{
    public function render(): View
    {
        return view('livewire.my-reports', [
            'entitlements' => auth()->user()->entitlements()
                ->with('issue.report', 'issue.assets')
                ->latest()
                ->get(),
        ]);
    }
}
```

`resources/views/livewire/my-reports.blade.php`:

```blade
<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">My reports</h1>

    @if ($entitlements->isEmpty())
        <p class="text-gray-600">You haven't purchased any reports yet.
            <a href="{{ route('catalogue.pir') }}" class="text-brand hover:underline">Browse the PIR database</a>.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Report</th>
                        <th class="px-4 py-2">Issue</th>
                        <th class="px-4 py-2">Purchased</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($entitlements as $entitlement)
                        @php
                            $pdf = $entitlement->issue->assets->firstWhere('type', \App\Enums\AssetType::ReportPdf);
                            $status = $entitlement->status();
                        @endphp
                        <tr wire:key="ent-{{ $entitlement->id }}">
                            <td class="px-4 py-2 font-medium">{{ $entitlement->issue->report->name }}</td>
                            <td class="px-4 py-2">{{ $entitlement->issue->version_label }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $entitlement->created_at->format('j M Y') }}</td>
                            <td class="px-4 py-2">
                                @if ($status === 'active')
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                @elseif ($status === 'expiring')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Expiring soon</span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Expired</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if ($entitlement->isActive() && $pdf)
                                    <a href="{{ route('assets.download', $pdf) }}" class="text-brand hover:underline">Download</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
```

`routes/web.php` — add inside the `['auth', 'verified']` group:

```php
    Route::get('/my-reports', \App\Livewire\MyReports::class)->name('my-reports');
```

`resources/views/components/public.blade.php` — in the `@auth` nav block, before the basket badge:

```blade
                    <a href="{{ route('my-reports') }}" class="hover:underline">My reports</a>
```

`resources/views/checkout/success.blade.php` — swap the fulfilled line's plain text for the link:

```blade
        <p class="mt-4">Payment confirmed — your reports are in
            <a href="{{ route('my-reports') }}" class="text-brand hover:underline">My reports</a>.</p>
```

- [ ] **Step 4: Run the tests, then the full suite**

Run: `php artisan test tests/Feature/MyReportsTest.php && php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add my reports dashboard with entitlement status and downloads"
```

---

### Task 8: Admin refunds — service + Filament Orders resource

Per-item refunds behind a tested service; Filament provides the surface. Refund logic is fully covered by service tests, so the Filament layer only needs a render test (same pattern as `ImportPirIndexPageTest`).

**Files:**
- Create: `app/Services/RefundOrderItem.php`, `app/Filament/Resources/OrderResource.php`, `app/Filament/Resources/OrderResource/Pages/ListOrders.php`, `app/Filament/Resources/OrderResource/Pages/ViewOrder.php`, `app/Filament/Resources/OrderResource/RelationManagers/ItemsRelationManager.php`
- Test: `tests/Feature/RefundOrderItemTest.php`, `tests/Feature/Admin/OrderResourceTest.php`

**Interfaces:**
- Consumes: `PaymentGateway::refundItem()` + `FakePaymentGateway::$failRefunds/$refundedItems` (Task 1), `Order`/`OrderItem`/`Entitlement` (Task 2), fulfilled orders (Task 6).
- Produces: `App\Services\RefundOrderItem::handle(OrderItem $item): void` (throws `InvalidArgumentException` for non-refundable items; propagates gateway exceptions untouched with nothing revoked).

- [ ] **Step 1: Write the failing service tests**

`tests/Feature/RefundOrderItemTest.php`:

```php
<?php

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Services\RefundOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function fulfilledOrderWithItems(int $count = 2): Order
{
    $order = Order::factory()->fulfilled()->create(['total_pence' => 2500 * $count]);

    OrderItem::factory($count)->create(['order_id' => $order->id])
        ->each(fn (OrderItem $item) => Entitlement::factory()->create([
            'user_id' => $order->user_id,
            'issue_id' => $item->issue_id,
            'order_item_id' => $item->id,
        ]));

    return $order->fresh('items');
}

it('refunds one item, revoking only its entitlement', function () {
    $order = fulfilledOrderWithItems(2);
    [$first, $second] = $order->items;

    app(RefundOrderItem::class)->handle($first);

    expect($first->fresh()->refunded_at)->not->toBeNull()
        ->and($first->entitlement->fresh()->revoked_at)->not->toBeNull()
        ->and($second->fresh()->refunded_at)->toBeNull()
        ->and($second->entitlement->fresh()->revoked_at)->toBeNull()
        ->and($order->fresh()->status)->toBe('fulfilled')
        ->and($this->gateway->refundedItems)->toHaveCount(1);
});

it('flips the order to refunded when the last item is refunded', function () {
    $order = fulfilledOrderWithItems(2);

    $order->items->each(fn (OrderItem $item) => app(RefundOrderItem::class)->handle($item));

    expect($order->fresh()->status)->toBe('refunded');
});

it('leaves everything intact when the gateway throws', function () {
    $order = fulfilledOrderWithItems(1);
    $this->gateway->failRefunds = true;
    $item = $order->items->first();

    expect(fn () => app(RefundOrderItem::class)->handle($item))->toThrow(RuntimeException::class);

    expect($item->fresh()->refunded_at)->toBeNull()
        ->and($item->entitlement->fresh()->revoked_at)->toBeNull()
        ->and($order->fresh()->status)->toBe('fulfilled');
});

it('rejects already-refunded items and non-fulfilled orders', function () {
    $order = fulfilledOrderWithItems(1);
    $item = $order->items->first();
    app(RefundOrderItem::class)->handle($item);

    expect(fn () => app(RefundOrderItem::class)->handle($item->fresh()))
        ->toThrow(InvalidArgumentException::class);

    $pendingItem = OrderItem::factory()->create();
    expect(fn () => app(RefundOrderItem::class)->handle($pendingItem))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/RefundOrderItemTest.php`
Expected: FAIL — `RefundOrderItem` not found.

- [ ] **Step 3: Implement the refund service**

`app/Services/RefundOrderItem.php`:

```php
<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundOrderItem
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * Refund one item of a fulfilled order and revoke its entitlement.
     * The gateway call happens first — if it throws, nothing is revoked.
     * Refunding the last unrefunded item flips the order to `refunded`.
     */
    public function handle(OrderItem $item): void
    {
        if ($item->refunded_at !== null || $item->order->status !== 'fulfilled') {
            throw new InvalidArgumentException('Only unrefunded items of fulfilled orders can be refunded.');
        }

        $this->gateway->refundItem($item);

        DB::transaction(function () use ($item) {
            $item->update(['refunded_at' => now()]);
            $item->entitlement?->update(['revoked_at' => now()]);

            $order = $item->order->fresh('items');
            if ($order->items->every(fn (OrderItem $i) => $i->refunded_at !== null)) {
                $order->update(['status' => 'refunded']);
            }
        });
    }
}
```

Run: `php artisan test tests/Feature/RefundOrderItemTest.php` — Expected: PASS.

- [ ] **Step 4: Write the failing Filament render test**

`tests/Feature/Admin/OrderResourceTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the orders list and detail pages for an authenticated admin', function () {
    // Filament's Authenticate middleware allows all users in the local
    // environment when the User model doesn't implement FilamentUser.
    config(['app.env' => 'local']);

    $order = Order::factory()->fulfilled()->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get("/admin/orders/{$order->id}")
        ->assertOk();
});
```

Run: `php artisan test tests/Feature/Admin/OrderResourceTest.php`
Expected: FAIL — 404, resource not registered.

- [ ] **Step 5: Implement the Filament resource**

`app/Filament/Resources/OrderResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Shop';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Order #')->sortable(),
                TextColumn::make('user.email')->label('Buyer')->searchable(),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('total_pence')->label('Total')
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
```

`app/Filament/Resources/OrderResource/Pages/ListOrders.php`:

```php
<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
```

`app/Filament/Resources/OrderResource/Pages/ViewOrder.php`:

```php
<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
```

`app/Filament/Resources/OrderResource/RelationManagers/ItemsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RefundOrderItem;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issue.report.name')->label('Report'),
                TextColumn::make('issue.version_label')->label('Issue'),
                TextColumn::make('amount_pence')->label('Amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('refunded_at')->label('Refunded')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('refund')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (OrderItem $record): bool => $record->refunded_at === null
                        && $record->order->status === 'fulfilled')
                    ->action(function (OrderItem $record): void {
                        $this->refundItem($record);
                    }),
            ])
            ->headerActions([
                Action::make('refundAll')
                    ->label('Refund all')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'fulfilled')
                    ->action(function (): void {
                        /** @var Order $order */
                        $order = $this->getOwnerRecord();
                        $order->items()->whereNull('refunded_at')->get()
                            ->each(fn (OrderItem $item) => $this->refundItem($item));
                    }),
            ]);
    }

    private function refundItem(OrderItem $item): void
    {
        try {
            app(RefundOrderItem::class)->handle($item);

            Notification::make()->success()->title('Item refunded')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Refund failed')->body($e->getMessage())->send();
        }
    }
}
```

*(Filament v4 API note: if `->recordActions()` is rejected on the installed minor version, the v3-style name is `->actions()` — same builder, one rename. The render test catches this immediately.)*

- [ ] **Step 6: Run the Filament test, then the full suite**

Run: `php artisan test tests/Feature/Admin/OrderResourceTest.php && php artisan test`
Expected: PASS — M2a complete.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add admin orders resource with per-item refunds"
```

---

## Self-Review

**1. Spec coverage:** basket (DB-backed, unique per report, add/remove/badge/page) → Tasks 2/4 ✓; button states incl. hidden-when-owned + older-issue downloads on detail → Task 4 ✓; checkout with stale-line revalidation, frozen issues, single Stripe session → Task 5 ✓; webhook-only idempotent fulfilment, entitlement per item, basket cleared on fulfilment, cancel keeps basket (checkout never clears it — asserted in Task 5) → Tasks 5/6 ✓; success page reads live status, owner-only, grants nothing → Tasks 5/6 ✓; AccessService entitlement rule → Task 3 ✓; My reports with 30-day chip boundary → Tasks 2/7 ✓; per-item refunds, last-item flips order, gateway-failure-intact → Task 8 ✓; gateway boundary + fake with no keys → Task 1 ✓; Billable/Cashier columns → Task 1 ✓. Spec §9 open questions are all explicitly non-blocking.

**2. Placeholder scan:** none — every code step carries complete code. Two deliberate forward references are resolved in place: the basket page's disabled Checkout button (Task 4) is replaced by the real form in Task 5, and the success page's plain "My reports" text (Task 5) becomes a link in Task 7. One flagged API-drift note (Filament `recordActions` vs `actions`) names the exact alternative.

**3. Type consistency:** `PaymentGateway` signatures identical across Tasks 1/5/6/8; `FakePaymentGateway` hooks (`$checkoutSessions`, `$refundedItems`, `$failRefunds`, `$nextWebhookEvent`) match their uses in Tasks 5/6/8; `Entitlement::scopeActive()/isActive()/status()` defined in Task 2, consumed in Tasks 3/4/5/7; `FulfilOrder::handle(int, ?string)` matches the webhook controller call; factory states `fulfilled()/expired()/revoked()/expiring()` defined in Task 2 and used in Tasks 7/8; route names `basket.show`/`checkout.store`/`checkout.success`/`my-reports`/`webhooks.stripe` consistent across Tasks 4–7.
