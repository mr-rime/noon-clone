<?php

require_once __DIR__ . '/../vendor/autoload.php';


if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}


class StripeService
{
    private string $stripeSecretKey;
    private string $stripePublishableKey;
    private \Stripe\StripeClient $stripe;

    public function __construct()
    {

        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_test_key_here';
        $this->stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_test_key_here';


        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        $this->stripe = new \Stripe\StripeClient($this->stripeSecretKey);
    }

    public function createCheckoutSession(array $orderData): array
    {
        try {

            $lineItems = [];
            foreach ($orderData['items'] as $item) {

                $productName = strlen($item['name']) > 100
                    ? substr($item['name'], 0, 97) . '...'
                    : $item['name'];

                $productData = [
                    'name' => $productName,
                    'metadata' => [
                        'product_id' => $item['product_id'] ?? 'unknown',
                        'name' => $productName,
                        'images' => $item['images'] ?? [],
                    ]
                ];


                if (!empty($item['description']) && strlen($item['description']) <= 500) {
                    $productData['description'] = $item['description'];
                }

                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($item['currency']),
                        'product_data' => $productData,
                        'unit_amount' => (int) ($item['price'] * 100),
                    ],
                    'quantity' => $item['quantity'],
                ];
            }


            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $orderData['success_url'] ?? 'http://localhost:3000/orders?payment=success',
                'cancel_url' => $orderData['cancel_url'] ?? 'http://localhost:3000/cart?payment=cancelled',
                'customer_email' => $orderData['customer_email'] ?? null,
                'metadata' => [
                    'user_id' => $orderData['user_id'] ?? null,
                    'product_id' => $item['product_id'] ?? 'unknown',
                    'name' => $productName,
                    'images' => $item['images'] ?? [],
                    'item_count' => count($orderData['items']),
                    'total_amount' => array_sum(array_map(function ($item) {
                        return $item['price'] * $item['quantity'];
                    }, $orderData['items'])),
                ],
                'shipping_address_collection' => [
                    'allowed_countries' => ['EG', 'US', 'GB', 'CA', 'AU'],
                ],
            ]);

            return [
                'success' => true,
                'session_id' => $session->id,
                'url' => $session->url,
                'publishable_key' => $this->stripePublishableKey,
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            error_log('Stripe Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An error occurred while creating the checkout session',
            ];
        }
    }

    public function getCheckoutSession(string $sessionId): array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);

            return [
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'status' => $session->status,
                    'amount_total' => $session->amount_total,
                    'currency' => $session->currency,
                    'customer_email' => $session->customer_email,
                    'metadata' => $session->metadata,
                ],
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $_ENV['STRIPE_WEBHOOK_SECRET'] ?? 'whsec_f518797ae4b74f69472fd0789aa1a407079557a70e407e5fd9481aa712a3d710'
            );

            switch ($event->type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutSessionCompleted($event->data->object);

                case 'payment_intent.succeeded':
                    return $this->handlePaymentSucceeded($event->data->object);

                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailed($event->data->object);

                default:
                    return [
                        'success' => true,
                        'message' => 'Event type not handled: ' . $event->type,
                    ];
            }
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Invalid webhook signature',
            ];
        } catch (Exception $e) {
            error_log('Stripe webhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Webhook processing failed',
            ];
        }
    }

    private function handleCheckoutSessionCompleted($session): array
    {


        return [
            'success' => true,
            'message' => 'Checkout session completed',
            'session_id' => $session->id,
        ];
    }

    private function handlePaymentSucceeded($paymentIntent): array
    {
        return [
            'success' => true,
            'message' => 'Payment succeeded',
            'payment_intent_id' => $paymentIntent->id,
        ];
    }

    private function handlePaymentFailed($paymentIntent): array
    {
        return [
            'success' => false,
            'message' => 'Payment failed',
            'payment_intent_id' => $paymentIntent->id,
        ];
    }
}
