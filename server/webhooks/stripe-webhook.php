<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/StripeService.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/TrackingDetails.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../utils/generateHash.php';

header('Content-Type: application/json');

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sig_header)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload or signature']);
    exit;
}

try {

    $stripeService = new StripeService();
    $webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET');

    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $webhook_secret
    );

    error_log('Stripe webhook received: ' . $event->type);


    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            error_log('Processing checkout.session.completed: ' . $session->id);


            if ($session->payment_status === 'paid') {
                $db = new Database();
                $conn = $db->getConnection();

                $orderModel = new Order($conn);
                $trackingModel = new TrackingDetails($conn);
                $cartModel = new Cart($conn);


                $userId = isset($session->metadata['user_id']) ? (int)$session->metadata['user_id'] : null;
                error_log('User ID from metadata: ' . var_export($userId, true));

                if ($userId) {

                    $cartItems = $cartModel->getCartItems($userId);
                    error_log('Cart items found: ' . count($cartItems));


                    if (empty($cartItems)) {
                        error_log('Cart is empty, retrieving line items from Stripe API');
                        $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY'));
                        $lineItems = $stripe->checkout->sessions->allLineItems($session->id, ['expand' => ['data.price.product']]);

                        $cartItems = [];
                        foreach ($lineItems->data as $item) {
                            if ($item->price && $item->price->product) {
                                $cartItems[] = [
                                    'product_id' => $item->price->product->metadata->product_id ?? 'unknown',
                                    'name' => $item->price->product->name,
                                    'price' => $item->price->unit_amount / 100,
                                    'currency' => $item->price->currency,
                                    'quantity' => $item->quantity,
                                    'image_url' => !empty($item->price->product->images) ? $item->price->product->images[0] : null
                                ];
                            }
                        }
                        error_log('Line items retrieved: ' . count($cartItems));
                    }


                    if (empty($cartItems)) {
                        error_log('Creating virtual Stripe payment product');
                        $productId = $session->metadata['product_id'] ?? generateHash();


                        $productCheck = $conn->prepare("SELECT id FROM products WHERE id = ?");
                        $productCheck->bind_param("s", $productId);
                        $productCheck->execute();
                        $result = $productCheck->get_result();

                        if ($result->num_rows === 0) {
                            $createProduct = $conn->prepare("INSERT INTO products (id, psku, name, product_overview, price, currency, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $productName = "Stripe Payment";
                            $productOverview = "Payment processed through Stripe";
                            $productPrice = 0.00;
                            $productCurrency = "USD";
                            $adminUserId = 1;
                            $psku = "ST-" . strtoupper(substr(uniqid(), -6));

                            $createProduct->bind_param("ssssdsi", $productId, $psku, $productName, $productOverview, $productPrice, $productCurrency, $adminUserId);
                            $createProduct->execute();
                            $createProduct->close();
                            error_log('Virtual Stripe product created');
                        }
                        $productCheck->close();


                        $shortSessionId = substr($session->id, -10);
                        $cartItems = [
                            [
                                'product_id' => $productId,
                                'name' => 'Payment from Stripe Checkout - Session: ' . $shortSessionId,
                                'price' => $session->amount_total / 100,
                                'currency' => $session->currency,
                                'quantity' => 1,
                                'image_url' => null
                            ]
                        ];
                    }


                    $shippingAddress = 'Address collected during checkout';
                    if (isset($session->customer_details->address)) {
                        $addr = $session->customer_details->address;
                        $shippingAddress = json_encode([
                            'line1' => $addr->line1 ?? '',
                            'line2' => $addr->line2 ?? '',
                            'city' => $addr->city ?? '',
                            'state' => $addr->state ?? '',
                            'postal_code' => $addr->postal_code ?? '',
                            'country' => $addr->country ?? '',
                            'name' => $session->customer_details->name ?? ''
                        ]);
                    }



                    $order = [
                        'currency' => strtoupper($session->currency),
                        'shipping_address' => $shippingAddress,
                        'payment_method' => 'stripe',
                        'items' => array_map(function ($item) {
                            return [
                                'product_id' => $item['product_id'],
                                'name' => $item['name'],
                                'price' => $item['final_price'] ?? $item['price'],
                                'currency' => $item['currency'],
                                'quantity' => $item['quantity'],
                                'image_url' => $item['image_url'] ?? null
                            ];
                        }, $cartItems)
                    ];

                    error_log('Creating order with data: ' . json_encode($order));
                    $createdOrder = $orderModel->createFromWebhook($order, $userId);

                    if ($createdOrder) {
                        error_log('Order created successfully: ' . $createdOrder['id']);


                        $orderModel->updateStatus($createdOrder['id'], 'processing', 'paid');


                        $trackingData = [
                            'order_id' => $createdOrder['id'],
                            'shipping_provider' => 'Standard Shipping',
                            'status' => 'processing',
                            'estimated_delivery_date' => date('Y-m-d', strtotime('+7 days'))
                        ];

                        $trackingModel->create($trackingData);


                        $cartModel->clearCart((int)$userId);

                        error_log("Order processing completed for order: " . $createdOrder['id']);
                    } else {
                        error_log('Failed to create order. Data passed: ' . json_encode($order));
                    }
                } else {
                    error_log('No user ID found in session metadata');
                }
            } else {
                error_log('Payment not successful. Status: ' . $session->payment_status);
            }
            break;

        case 'payment_intent.succeeded':
            error_log('Payment intent succeeded: ' . $event->data->object->id);
            break;

        case 'payment_intent.payment_failed':
            error_log('Payment intent failed: ' . $event->data->object->id);
            break;

        default:
            error_log('Unhandled event type: ' . $event->type);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature']);
} catch (Exception $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed']);
}