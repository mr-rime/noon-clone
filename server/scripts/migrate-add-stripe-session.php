<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$database = new Database();
$conn = $database->getConnection();

echo "Adding stripe_session_id column to orders table...\n";

$result = $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255) DEFAULT NULL");
if ($result) {
    echo "✅ Column added (or already exists)\n";
} else {
    echo "❌ Failed: " . $conn->error . "\n";
}

$result2 = $conn->query("CREATE INDEX IF NOT EXISTS idx_stripe_session ON orders(stripe_session_id)");
if ($result2) {
    echo "✅ Index created (or already exists)\n";
} else {
    echo "❌ Index error: " . $conn->error . "\n";
}

echo "Done.\n";
$conn->close();
