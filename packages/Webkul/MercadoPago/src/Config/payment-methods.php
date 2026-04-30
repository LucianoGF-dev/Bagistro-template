<?php

use Webkul\MercadoPago\Payment\MercadoPagoPayment;

return [
    'mercadopago' => [
        'class' => MercadoPagoPayment::class,
        'code' => 'mercadopago',
        'title' => 'MercadoPago',
        'description' => 'MercadoPago payment gateway',
        'active' => true,
        'sort' => 4,
    ],
];
