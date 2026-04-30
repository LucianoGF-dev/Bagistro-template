<?php

namespace Webkul\MercadoPago\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderPaymentRepository;

class MercadoPagoController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderPaymentRepository $orderPaymentRepository
    ) {}

    /**
     * Crear preferencia de pago y redirigir a Mercado Pago
     */
    public function createPreference(Request $request)
    {
        $cart = Cart::getCart();

        if (!$cart || $cart->items()->count() === 0) {
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('mercadopago::app.checkout.cart_empty'));
        }

        // Configurar SDK con token del canal actual
        $accessToken = app('Webkul\MercadoPago\Payment\MercadoPagoPayment')->getAccessToken();
        MercadoPagoConfig::setAccessToken($accessToken);

        try {
            $preferenceClient = new PreferenceClient();
            $preference = $preferenceClient->create([
                'items' => [
                    [
                        'title'       => __('mercadopago::app.checkout.order_title', ['order' => '#' . $cart->id]),
                        'description' => __('mercadopago::app.checkout.order_desc'),
                        'unit_price'  => (float) number_format($cart->grand_total, 2, '.', ''),
                        'quantity'    => 1,
                        'currency_id' => $cart->cart_currency_code ?? 'USD',
                    ]
                ],
                'payer' => [
                    'name'    => $cart->billing_address->first_name ?? '',
                    'surname' => $cart->billing_address->last_name ?? '',
                    'email'   => $cart->billing_address->email ?? '',
                ],
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.success'),
                ],
                'auto_return'      => 'approved',
                'external_reference' => (string) $cart->id,
                'notification_url' => route('mercadopago.webhook'),
                'statement_descriptor' => 'BAGISTO',
            ]);

            // Guardar referencia en sesión para recuperación
            session([
                'mercadopago_cart_id'      => $cart->id,
                'mercadopago_preference_id' => $preference->id,
            ]);

            return redirect($preference->init_point);

        } catch (\Exception $e) {
            \Log::channel('payment')->error('MercadoPago Preference Error: ' . $e->getMessage(), [
                'cart_id' => $cart?->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('mercadopago::app.checkout.preference_error'));
        }
    }

    /**
     * Webhook: Notificación asíncrona de Mercado Pago
     */
    public function handleWebhook(Request $request)
    {
        $topic  = $request->input('topic');
        $dataId = $request->input('data.id');
        $type   = $request->input('type');

        // Solo procesar notificaciones de pago
        if ($topic !== 'payment' && $type !== 'payment') {
            return response('OK', 200);
        }

        try {
            $accessToken = app('Webkul\MercadoPago\Payment\MercadoPagoPayment')->getAccessToken();
            MercadoPagoConfig::setAccessToken($accessToken);

            $paymentClient = new PaymentClient();
            $payment = $paymentClient->get((int) $dataId);

            $cartId = $payment->external_reference;
            $this->syncOrderWithPayment($cartId, $payment);

            return response('OK', 200);

        } catch (\Exception $e) {
            \Log::channel('payment')->error('MercadoPago Webhook Error: ' . $e->getMessage(), [
                'data_id' => $dataId,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response('ERROR', 500);
        }
    }

    /**
     * Redirección exitosa desde Mercado Pago
     */
    public function handleSuccess(Request $request)
    {
        $paymentId = $request->query('payment_id');

        if ($paymentId) {
            try {
                $accessToken = app('Webkul\MercadoPago\Payment\MercadoPagoPayment')->getAccessToken();
                MercadoPagoConfig::setAccessToken($accessToken);

                $paymentClient = new PaymentClient();
                $payment = $paymentClient->get((int) $paymentId);

                $this->syncOrderWithPayment($payment->external_reference, $payment);
            } catch (\Exception $e) {
                \Log::channel('payment')->warning('MercadoPago Success Sync Warning: ' . $e->getMessage());
            }
        }

        return redirect()->route('shop.checkout.success');
    }

    /**
     * Redirección fallida
     */
    public function handleFailure()
    {
        return redirect()->route('shop.checkout.cart.index')
            ->with('error', __('mercadopago::app.checkout.payment_failed'));
    }

    /**
     * Sincronizar estado del pago con la orden de Bagisto
     */
    protected function syncOrderWithPayment(string $cartId, object $payment): void
    {
        // Buscar orden por cart_id (Bagisto crea la orden antes del pago en flujo estándar)
        $order = $this->orderRepository->findOneWhere(['cart_id' => (int) $cartId]);

        if (!$order) {
            \Log::channel('payment')->warning("Order not found for cart_id: {$cartId}");
            return;
        }

        // Mapeo de estados de Mercado Pago a Bagisto
        $statusMap = [
            'approved'   => 'processing',
            'pending'    => 'pending_payment',
            'in_process' => 'pending_payment',
            'rejected'   => 'canceled',
            'cancelled'  => 'canceled',
            'refunded'   => 'refunded',
            'charged_back' => 'canceled',
        ];

        $newStatus = $statusMap[$payment->status] ?? 'pending_payment';
        
        // Actualizar orden
        $this->orderRepository->update([
            'status' => $newStatus,
        ], $order->id);

        // Actualizar pago de la orden
        $orderPayment = $order->payment;
        if ($orderPayment) {
            $this->orderPaymentRepository->update([
                'method'       => 'mercadopago',
                'method_title' => __('mercadopago::app.admin.system.title'),
                'additional'   => [
                    'mp_payment_id'     => $payment->id,
                    'mp_status'         => $payment->status,
                    'mp_transaction_amount' => $payment->transaction_amount,
                    'mp_date_approved'  => $payment->date_approved ?? null,
                ],
            ], $orderPayment->id);
        }

        \Log::channel('payment')->info("Order #{$order->id} synced with MP payment #{$payment->id} (status: {$payment->status})");
    }
}