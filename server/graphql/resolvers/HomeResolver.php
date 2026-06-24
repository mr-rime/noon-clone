<?php

require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/BrowsingHistory.php';
require_once __DIR__ . '/../../models/SessionManager.php';

function home_pick_diverse_unique(array $products, int $limit, array &$usedIds): array
{
    $buckets = [];
    foreach ($products as $product) {
        if (!isset($product['id'])) {
            continue;
        }
        if (isset($usedIds[$product['id']])) {
            continue;
        }
        $categoryId = $product['category_id'] ?? 'uncategorized';
        if (!isset($buckets[$categoryId])) {
            $buckets[$categoryId] = [];
        }
        $buckets[$categoryId][] = $product;
    }

    $result = [];
    $bucketKeys = array_keys($buckets);
    $bucketIndex = 0;

    while (count($result) < $limit && !empty($bucketKeys)) {
        $categoryKey = $bucketKeys[$bucketIndex % count($bucketKeys)];

        if (!empty($buckets[$categoryKey])) {
            $candidate = array_shift($buckets[$categoryKey]);
            if (!isset($usedIds[$candidate['id']])) {
                $result[] = $candidate;
                $usedIds[$candidate['id']] = true;
            }
        }

        if (empty($buckets[$categoryKey])) {
            unset($buckets[$categoryKey]);
            $bucketKeys = array_keys($buckets);
            $bucketIndex = 0;
            continue;
        }

        $bucketIndex++;
    }

    if (count($result) < $limit) {
        foreach ($products as $product) {
            if (count($result) >= $limit) {
                break;
            }
            if (!isset($product['id']) || isset($usedIds[$product['id']])) {
                continue;
            }
            $result[] = $product;
            $usedIds[$product['id']] = true;
        }
    }

    return $result;
}

function getHome(mysqli $db, array $data): array
{
    try {
        $productModel = new Product($db);
        $sessionManager = new SessionManager($db);
        $sessionId = $sessionManager->getSessionId();
        $user = $sessionManager->getUser($sessionId);
        $userId = $user['id'] ?? null;
        $limit = $data['limit'] ?? 60;
        $offset = $data['offset'] ?? 0;
        $search = $data['search'] ?? '';

        error_log("User iD" . $userId);
        error_log("_SESSION iD" . $sessionId);

        $history = new BrowsingHistory($db);

        $userCategories = $history->getUserCategories($sessionId, $userId, 5);
        $userBrands = $history->getUserBrands($sessionId, $userId, 3);

        $recommendedCandidates = [];
        if (!empty($userCategories) || !empty($userBrands)) {
            $recommendedCandidates = $productModel->findByPreferences($userId, $userCategories, $userBrands, 40, $offset, $search, true);
        }

        if (count($recommendedCandidates) < 20) {
            $randomLimit = 40 - count($recommendedCandidates);
            $randomCandidates = $productModel->findAll($userId, $randomLimit, $offset, $search, true);
            // Do not shuffle randomCandidates — keep deterministic order
            $recommendedCandidates = array_merge($recommendedCandidates, $randomCandidates);
        }

        $recentIds = $history->getRecent($sessionId, $userId, 40);
        $previouslyBrowsedCandidates = [];
        foreach ($recentIds as $pid) {
            $p = $productModel->findById($pid, true);
            if ($p) {
                $previouslyBrowsedCandidates[] = $p;
            }
        }

        $discountCandidates = $productModel->findDiscountedProducts(80, 'DESC');

        $usedIds = [];
        // Keep recommendedCandidates order deterministic; do not shuffle here.
        $recommended = home_pick_diverse_unique($recommendedCandidates, 20, $usedIds);
        $previouslyBrowsed = home_pick_diverse_unique($previouslyBrowsedCandidates, 20, $usedIds);

        $bestDeals = home_pick_diverse_unique($discountCandidates, 20, $usedIds);
        $discountedProducts = home_pick_diverse_unique($discountCandidates, 20, $usedIds);

        return [
            'success' => true,
            'message' => 'Home page loaded successfully.',
            'home' => [
                'recommendedForYou' => $recommended,
                'previouslyBrowsed' => $previouslyBrowsed,
                'bestDeals' => $bestDeals,
                'discountedProducts' => $discountedProducts
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'home' => []
        ];
    }
}