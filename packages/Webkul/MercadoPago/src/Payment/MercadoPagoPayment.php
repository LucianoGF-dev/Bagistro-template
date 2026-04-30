<?php

namespace Webkul\MercadoPago\Payment;

use Webkul\Payment\Payment\Payment;

class MercadoPagoPayment extends Payment
{
    protected $code = 'mercadopago';

    public function isAvailable(): bool
    {
        $isConfigured = core()->getConfigData('sales.paymentmethods.mercadopago.active');
        
        // Si no hay configuración en BD, usa el valor por defecto del config/payment-methods.php
        if ($isConfigured === null) {
            return (bool) config('payment_methods.mercadopago.active', true);
        }
        
        return (bool) $isConfigured;
    }

    public function getTitle(): string
    {
        $title = core()->getConfigData('sales.paymentmethods.mercadopago.title');
        
        if ($title === null) {
            return config('payment_methods.mercadopago.title', 'MercadoPago');
        }
        
        return $title;
    }

    public function getDescription(): string
    {
        $description = core()->getConfigData('sales.paymentmethods.mercadopago.description');
        
        if ($description === null) {
            return config('payment_methods.mercadopago.description', 'MercadoPago payment gateway');
        }
        
        return $description;
    }

    public function getSortOrder(): int
    {
        $sortOrder = core()->getConfigData('sales.paymentmethods.mercadopago.sort_order');
        
        if ($sortOrder === null) {
            return (int) config('payment_methods.mercadopago.sort', 4);
        }
        
        return (int) $sortOrder;
    }

    public function hasRedirect(): bool
    {
        return true;
    }

    public function getRedirectUrl(): string
    {
        return route('mercadopago.redirect');
    }

    public function getPaymentFormHtml(): string
    {
        return view('mercadopago::checkout')->render();
    }

    /**
     * Obtener token según modo sandbox/producción
     */
    public function getAccessToken(): string
    {
        $isSandbox = (bool) core()->getConfigData('sales.paymentmethods.mercadopago.sandbox_mode');
        
        if ($isSandbox) {
            $token = core()->getConfigData('sales.paymentmethods.mercadopago.sandbox_access_token') 
                ?: core()->getConfigData('sales.paymentmethods.mercadopago.access_token');
        } else {
            $token = core()->getConfigData('sales.paymentmethods.mercadopago.access_token');
        }
        
        return $token ?: '';
    }
}