<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\PaymentGateway;
use App\Services\FulfilOrder;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function store(Request $request, PaymentGateway $gateway, FulfilOrder $fulfil): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            // PIR checkout needs no prior registration: the account is created (or
            // signed into) here as part of checkout, with no email verification gate.
            // The session cart is merged into the account on login, then they land back here.
            session(['url.intended' => route('basket.show')]);

            return redirect()
                ->route(config('app.allow_registration', true) ? 'register' : 'login')
                ->with('status', 'Create your account (or log in) to complete your purchase — your cart will be waiting for you.');
        }

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

        // Demo bypass: fulfil the order in-app and skip the gateway + webhook,
        // so the end-to-end flow works locally without external Stripe.
        if (config('demo.instant_fulfil')) {
            $fulfil->handle($order->id, 'demo_'.$order->id);

            return redirect()->route('checkout.success', $order);
        }

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
