<?php

use GraphQL\Type\Definition\Type;

return [
    'createOrder' => [
        'type' => $OrderResponseType,
        'args' => [
            'currency' => Type::nonNull(Type::string()),
            'shipping_address' => Type::string(),
            'payment_method' => Type::string(),
            'items' => Type::listOf($OrderItemInputType),
        ],
        'resolve' => requireAuth(fn($root, $args, $context) => createOrder($context['db'], $args))
    ],

    'createCheckoutSession' => [
        'type' => $CheckoutSessionResponseType,
        'args' => [
            'success_url' => Type::string(),
            'cancel_url' => Type::string()
        ],
        'resolve' => requireAuth(fn($root, $args, $context) => createCheckoutSession($context['db'], $args))
    ],

    'updateOrderStatus' => [
        'type' => $UpdateOrderStatusResponseType,
        'args' => [
            'order_id' => Type::nonNull(Type::string()),
            'status' => Type::string(),
            'payment_status' => Type::string()
        ],
        'resolve' => requireStoreAuth(fn($root, $args, $context) => updateOrderStatus($context['db'], $args))
    ],

    'updateTrackingDetails' => [
        'type' => $UpdateTrackingResponseType,
        'args' => [
            'order_id' => Type::nonNull(Type::string()),
            'shipping_provider' => Type::string(),
            'tracking_number' => Type::string(),
            'status' => Type::string(),
            'estimated_delivery_date' => Type::string()
        ],
        'resolve' => requireStoreAuth(fn($root, $args, $context) => updateTrackingDetails($context['db'], $args))
    ],

    'verifyPayment' => [
        'type' => $OrderResponseType,
        'args' => [
            'session_id' => Type::nonNull(Type::string()),
        ],
        'resolve' => requireAuth(fn($root, $args, $context) => verifyPayment($context['db'], $args))
    ],
];
