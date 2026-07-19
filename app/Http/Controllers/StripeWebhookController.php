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
