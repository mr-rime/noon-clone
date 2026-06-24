<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:3002',
    'http://dashboard.localhost:3000',
    'http://dashboard.localhost:3002',
    'https://noon-rime.vercel.app',
    'https://noon-market.vercel.app',
    'https://noon-dashboard.vercel.app'
];


header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");


if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json");

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$sessionPath = __DIR__ . '/../config/session.php';

if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing vendor/autoload.php']);
    exit;
}

require_once $autoloadPath;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (file_exists($sessionPath)) {
    require_once $sessionPath;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

switch ($uri) {
    case '/graphql':
        require_once __DIR__ . '/../routes/graphql.php';
        break;

    case '/webhooks/stripe-webhook':
        require_once __DIR__ . '/../webhooks/stripe-webhook.php';
        break;

    case '/api/uploadthing/callback':
        require_once __DIR__ . '/../webhooks/uploadthing-callback.php';
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found!']);
        break;
}
