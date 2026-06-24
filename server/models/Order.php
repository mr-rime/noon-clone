<?php

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

require_once __DIR__ . '/../utils/generateHash.php';

class Order
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $query = 'SELECT * FROM orders';
        $result = $this->db->query($query);

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findById(int $id): ?array
    {
        $query = 'SELECT * FROM orders WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($query);

        if (!$stmt) {
            error_log("Prepare failed: " . $this->db->error);
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();



        return $result->fetch_assoc() ?: null;
    }

    public function createFromWebhook(array $data, int $userId): ?array
    {
        $validator = v::key('currency', v::stringType()->notEmpty()->length(3, 4))
            ->key('shipping_address', v::stringType()->notEmpty())
            ->key('payment_method', v::stringType()->notEmpty())
            ->key('items', v::arrayType()->notEmpty());

        try {
            $validator->assert($data);
        } catch (ValidationException $e) {
            error_log("Webhook order validation failed: " . $e->getMessage());
            return null;
        }

        $currency = $data['currency'];
        $shippingAddress = $data['shipping_address'];
        $paymentMethod = $data['payment_method'];
        $status = 'placed';
        $paymentStatus = 'paid';

        $this->db->begin_transaction();

        try {
            $stripeSessionId = $data['stripe_session_id'] ?? null;

            if ($stripeSessionId) {
                $orderQuery = "INSERT INTO orders (id, user_id, total_amount, currency, status, shipping_address, payment_method, payment_status, stripe_session_id) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            } else {
                $orderQuery = "INSERT INTO orders (id, user_id, total_amount, currency, status, shipping_address, payment_method, payment_status) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt = $this->db->prepare($orderQuery);
            if (!$stmt) {
                throw new Exception("Failed to prepare order insert: " . $this->db->error);
            }

            $orderId = generateHash();
            $totalAmount = 0;
            $orderCurrency = $currency; // preserve before item loop overwrites $currency

            foreach ($data['items'] as $item) {
                $totalAmount += (float)($item['price']) * (int)($item['quantity']);
            }
            $totalAmount = round($totalAmount, 2);

            if ($stripeSessionId) {
                $stmt->bind_param('sidssssss', $orderId, $userId, $totalAmount, $orderCurrency, $status, $shippingAddress, $paymentMethod, $paymentStatus, $stripeSessionId);
            } else {
                $stmt->bind_param('sidsssss', $orderId, $userId, $totalAmount, $orderCurrency, $status, $shippingAddress, $paymentMethod, $paymentStatus);
            }

            if (!$stmt->execute()) {
                throw new Exception("Order insert execute failed: " . $stmt->error);
            }

            if ($stmt->affected_rows < 1) {
                throw new Exception("Order insert affected 0 rows — possible constraint violation");
            }


            $stmt->close();
            error_log("Order row inserted: " . $orderId);

            $itemQuery = "INSERT INTO order_items (id, order_id, product_id, quantity, price, currency) VALUES (?, ?, ?, ?, ?, ?)";
            $itemStmt = $this->db->prepare($itemQuery);
            if (!$itemStmt) {
                throw new Exception("Failed to prepare order_items insert: " . $this->db->error);
            }

            foreach ($data['items'] as $item) {
                $itemId     = generateHash();
                $productId  = $item['product_id'];
                $quantity   = (int)$item['quantity'];
                $price      = (float)$item['price'];
                $itemCurrency = $item['currency'];

                // Verify the product exists before inserting to avoid FK violation
                $checkStmt = $this->db->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
                if ($checkStmt) {
                    $checkStmt->bind_param('s', $productId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $productExists = $checkResult->num_rows > 0;
                    $checkStmt->close();
                } else {
                    $productExists = false;
                }

                if (!$productExists) {
                    error_log("Skipping order_item for unknown product_id: " . $productId);
                    continue;
                }

                $itemStmt->bind_param('sssids', $itemId, $orderId, $productId, $quantity, $price, $itemCurrency);

                if (!$itemStmt->execute()) {
                    throw new Exception("order_items insert failed for product {$productId}: " . $itemStmt->error);
                }
                error_log("Order item inserted for product: " . $productId);
            }

            $itemStmt->close();

            $this->db->commit();
            error_log("Transaction committed for order: " . $orderId);

            return [
                'id'              => $orderId,
                'user_id'         => $userId,
                'total_amount'    => $totalAmount,
                'currency'        => $orderCurrency,
                'status'          => $status,
                'payment_status'  => $paymentStatus,
                'shipping_address' => $shippingAddress,
                'payment_method'  => $paymentMethod
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Webhook order creation failed: " . $e->getMessage());
            return null;
        }
    }

    public function create(array $data): ?array
    {
        $validator = v::key('currency', v::stringType()->notEmpty()->length(3, 4))
            ->key('shipping_address', v::stringType()->notEmpty())
            ->key('payment_method', v::stringType()->notEmpty())
            ->key('items', v::arrayType()->notEmpty());

        try {
            $validator->assert($data);
        } catch (ValidationException $e) {
            error_log("Validation failed: " . $e->getMessage());
            return null;
        }

        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            error_log("No user ID available for order creation");
            return null;
        }
        $currency = $data['currency'];
        $shippingAddress = $data['shipping_address'];
        $paymentMethod = $data['payment_method'];
        $status = 'placed';
        $paymentStatus = 'unpaid';



        $this->db->begin_transaction();

        try {
            $orderQuery = "INSERT INTO orders (id, user_id, total_amount, currency, status, shipping_address, payment_method, payment_status) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($orderQuery);
            $orderId = generateHash();
            $totalAmount = 0;

            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'];
                $inputPrice = $item['price'];

                $productStmt = $this->db->prepare("SELECT price FROM products WHERE id = ?");
                $productStmt->bind_param("s", $productId);
                $productStmt->execute();
                $productStmt->bind_result($dbPrice);
                $productStmt->fetch();
                $productStmt->close();

                if ((float) $dbPrice != (float) $inputPrice) {
                    throw new Exception(
                        "Price mismatch for product $productId: " .
                        "DB says $dbPrice, input says $inputPrice"
                    );
                }

                $totalAmount += (float) $dbPrice * $item['quantity'];
            }

            $final_amount = round($totalAmount, 2);


            $stmt->bind_param("sidsssss", $orderId, $userId, $final_amount, $currency, $status, $shippingAddress, $paymentMethod, $paymentStatus);

            $stmt->execute();

            $stmt->close();

            $itemID = generateHash();

            $itemQuery = "INSERT INTO order_items (id, order_id, product_id, quantity, price, currency) VALUES (?, ?, ?, ?, ?, ?)";
            $itemStmt = $this->db->prepare($itemQuery);

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                $itemCurrency = $item['currency'];

                $itemStmt->bind_param("sssids", $itemID, $orderId, $productId, $quantity, $price, $itemCurrency);
                $itemStmt->execute();
            }


            $itemStmt->close();

            $this->db->commit();


            return [
                'id' => $orderId,
                'user_id' => $userId,
                'total_amount' => $final_amount,
                'currency' => $currency,
                'status' => $status,
                'shipping_address' => $shippingAddress,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'items' => $data['items'],
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Order creation failed: " . $e->getMessage());
            return null;
        }
    }


    private function createTracking(int $orderId): bool
    {
        $trackingNumber = $this->generateTrackingNumber();

        $query = "INSERT INTO order_tracking (order_id, tracking_number, status) VALUES (?, ?, 'processing')";
        $stmt = $this->db->prepare($query);

        if (!$stmt) {
            error_log("Tracking insert prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param("is", $orderId, $trackingNumber);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    private function generateTrackingNumber(): string
    {
        return strtoupper(bin2hex(random_bytes(5)));
    }

    public function update(int $id, array $data): bool
    {
        $validator = v::key('status', v::stringType()->oneOf(
            v::equals('placed'),
            v::equals('processing'),
            v::equals('confirmed'),
            v::equals('dispatched'),
            v::equals('delivered'),
            v::equals('cancelled')
        ))
            ->key('payment_status', v::stringType()->oneOf(
                v::equals('unpaid'),
                v::equals('paid'),
                v::equals('refunded')
            ));

        try {
            $validator->assert($data);
        } catch (ValidationException $e) {
            error_log("Validation failed: " . $e->getMessage());
            return false;
        }

        $this->db->begin_transaction();

        try {
            $query = 'UPDATE orders SET status = ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $stmt = $this->db->prepare($query);

            if (!$stmt) {
                error_log("Prepare failed: " . $this->db->error);
                $this->db->rollback();
                return false;
            }

            $stmt->bind_param('ssi', $data['status'], $data['payment_status'], $id);
            $stmt->execute();
            $stmt->close();

            if ($data['payment_status'] === 'paid') {
                $this->createTracking($id);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Order update failed: " . $e->getMessage());
            return false;
        }
    }

    public function updateByStringId(string $id, array $data): bool
    {

        $validator = v::create();

        if (isset($data['status'])) {
            $validator = $validator->key('status', v::stringType()->oneOf(
                v::equals('placed'),
                v::equals('processing'),
                v::equals('confirmed'),
                v::equals('dispatched'),
                v::equals('delivered'),
                v::equals('cancelled')
            ));
        }

        if (isset($data['payment_status'])) {
            $validator = $validator->key('payment_status', v::stringType()->oneOf(
                v::equals('unpaid'),
                v::equals('paid'),
                v::equals('refunded')
            ));
        }

        try {
            if (isset($data['status']) || isset($data['payment_status'])) {
                $validator->assert($data);
            }
        } catch (ValidationException $e) {
            error_log("Validation failed: " . $e->getMessage());
            return false;
        }

        $this->db->begin_transaction();

        try {

            $fields = [];
            $values = [];
            $types = '';

            if (isset($data['status'])) {
                $fields[] = 'status = ?';
                $values[] = $data['status'];
                $types .= 's';
            }

            if (isset($data['payment_status'])) {
                $fields[] = 'payment_status = ?';
                $values[] = $data['payment_status'];
                $types .= 's';
            }

            if (empty($fields)) {
                $this->db->rollback();
                return false;
            }

            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $values[] = $id;
            $types .= 's';

            $query = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $this->db->prepare($query);

            if (!$stmt) {
                error_log("Prepare failed: " . $this->db->error);
                $this->db->rollback();
                return false;
            }

            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Order update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $query = 'DELETE FROM orders WHERE id = ?';
        $stmt = $this->db->prepare($query);

        if (!$stmt) {
            error_log("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    public function getByUserId(int $userId, int $limit = 10, int $offset = 0): array
    {
        $query = "
            SELECT o.*, 
                   GROUP_CONCAT(
                       JSON_OBJECT(
                           'id', oi.id,
                           'product_id', oi.product_id,
                           'quantity', oi.quantity,
                           'price', oi.price,
                           'currency', oi.currency,
                           'product_name', p.name,
                           'product_description', p.product_overview,
                           'product_image', pi.image_url
                       )
                   ) as items_json
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("Get user orders prepare failed: " . $this->db->error);
            return [];
        }

        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();


        foreach ($orders as &$order) {
            if ($order['items_json']) {
                $itemsArray = explode(',', $order['items_json']);
                $order['items'] = [];
                foreach ($itemsArray as $itemJson) {
                    $decodedItem = json_decode($itemJson, true);
                    if ($decodedItem !== null) {
                        $order['items'][] = $decodedItem;
                    }
                }
            } else {
                $order['items'] = [];
            }
            unset($order['items_json']);
        }

        return $orders;
    }

    public function getOrderWithItems(string $orderId): ?array
    {

        $orderQuery = "SELECT * FROM orders WHERE id = ?";
        $orderStmt = $this->db->prepare($orderQuery);

        if (!$orderStmt) {
            error_log("Get order prepare failed: " . $this->db->error);
            return null;
        }

        $orderStmt->bind_param("s", $orderId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $order = $orderResult->fetch_assoc();
        $orderStmt->close();

        if (!$order) {
            return null;
        }


        $itemsQuery = "
            SELECT oi.*, p.name as product_name, p.product_overview as product_description,
                   pi.image_url as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
            WHERE oi.order_id = ?
        ";

        $itemsStmt = $this->db->prepare($itemsQuery);
        if (!$itemsStmt) {
            error_log("Get order items prepare failed: " . $this->db->error);
            return $order;
        }

        $itemsStmt->bind_param("s", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();

        $order['items'] = $items;
        return $order;
    }

    public function updateStatus(string $orderId, string $status, ?string $paymentStatus = null): bool
    {
        $query = 'UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP';
        $params = [$status];
        $types = 's';

        if ($paymentStatus !== null) {
            $query .= ', payment_status = ?';
            $params[] = $paymentStatus;
            $types .= 's';
        }

        $query .= ' WHERE id = ?';
        $params[] = $orderId;
        $types .= 's';

        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("Update order status prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    public function findByIdString(string $id): ?array
    {
        $query = 'SELECT * FROM orders WHERE id = ? LIMIT 1';
        $stmt = $this->db->prepare($query);

        if (!$stmt) {
            error_log("Prepare failed: " . $this->db->error);
            return null;
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();

        return $order ?: null;
    }
}
