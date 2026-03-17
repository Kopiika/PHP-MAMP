<?php

/**
 * AI Routes — Gemini Integration
 *
 * EXERCISE 8: Use the GeminiAI class to add AI-powered features
 *
 * The GeminiAI class is already built (src/AI/GeminiAI.php).
 * Your job is to build the routes that USE it with real data.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\AI\GeminiAI;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// POST /api/ai/describe — Generate a product description
// ============================================================
// EXERCISE 6 (Step 1): Students build this route
//
// Given a product name and basic details, ask Gemini to write
// a short marketing description.
//
// The frontend sends:
//   { product_id: "uuid" }
//
// Your route should:
//   1. Fetch the product from Supabase (to get name, category, price)
//   2. Build a prompt like:
//      "Write a short product description (2-3 sentences) for: {name}.
//       Category: {category}. Price: {price} EUR."
//   3. Send the prompt to Gemini using $ai->ask($prompt)
//   4. Return the generated description
//
// Hints:
//   - Create the AI instance: $ai = new GeminiAI();
//   - Call it: $description = $ai->ask($prompt);
//   - Wrap in try/catch — AI calls can fail (rate limits, network issues)
// ============================================================

$app->post('/api/ai/describe', function (Request $request, Response $response) {

    $body      = $request->getParsedBody();
    $productId = $body['product_id'] ?? null;

    if (!$productId) {
        $response->getBody()->write(json_encode(['error' => 'product_id is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $results = $auth->query('products', [
        'id'     => 'eq.' . $productId,
        'select' => '*,categories(name)',
    ]);

    if (empty($results)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product  = $results[0];
    $name     = $product['name'];
    $category = $product['categories']['name'] ?? 'General';
    $price    = number_format((float)$product['price'], 2);

    $prompt = "Write a 2-3 sentence product description for: $name. "
            . "Category: $category. Price: $price EUR. "
            . "Make it engaging and suitable for an online store.";

    try {
        $ai          = new GeminiAI();
        $description = $ai->ask($prompt);

        $response->getBody()->write(json_encode(['description' => $description]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/stock-advice — Get AI advice on stock levels
// ============================================================
// EXERCISE 6 (Step 2): Students build this route
//
// Fetch all products with low stock and ask Gemini for advice.
//
// Your route should:
//   1. Fetch products where stock_quantity <= reorder_threshold
//      (hint: you may need to fetch all products and filter in PHP,
//       or use Supabase filter syntax)
//   2. Build a prompt with the low-stock products list
//   3. Ask Gemini for reorder recommendations
//   4. Return the AI advice plus the product data
//
// Example prompt:
//   "These products are running low on stock. For each, suggest a
//    reorder quantity based on the current stock and threshold:
//    - Wireless Earbuds Pro: 5 in stock, threshold: 15
//    - USB-C Hub Pro: 2 in stock, threshold: 10
//    Give a brief recommendation for each."
// ============================================================

$app->post('/api/ai/stock-advice', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $products = $auth->query('products', ['select' => '*']);

    // Filter: only products where stock is at or below reorder threshold
    $lowStock = array_values(array_filter($products, function ($p) {
        return (int)$p['stock_quantity'] <= (int)$p['reorder_threshold'];
    }));

    if (empty($lowStock)) {
        $response->getBody()->write(json_encode([
            'advice'   => 'All products are sufficiently stocked. No reorders needed at this time.',
            'products' => [],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Build the prompt listing each low-stock item
    $lines = array_map(function ($p) {
        return "- {$p['name']}: {$p['stock_quantity']} in stock, threshold: {$p['reorder_threshold']}";
    }, $lowStock);

    $prompt = "These products are running low on stock. "
            . "For each, suggest a reorder quantity based on the current stock and threshold:\n"
            . implode("\n", $lines) . "\n"
            . "Give a brief, practical recommendation for each product.";

    try {
        $ai     = new GeminiAI();
        $advice = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'advice'   => $advice,
            'products' => array_map(fn($p) => [
                'name'              => $p['name'],
                'stock_quantity'    => (int)$p['stock_quantity'],
                'reorder_threshold' => (int)$p['reorder_threshold'],
            ], $lowStock),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/summarize-orders — Summarize recent orders
// ============================================================
// EXERCISE 6 (Step 3 — Stretch): Students build this route
//
// Fetch recent orders and ask Gemini to summarize trends.
//
// Your route should:
//   1. Fetch orders from the last 7 days
//   2. Build a prompt with order data (customer, total, status)
//   3. Ask Gemini to identify patterns and summarize
//   4. Return the summary
//
// This combines Exercise 3 (date handling) with Exercise 8 (AI).
// ============================================================

$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch orders from the last 7 days
    $since  = date('c', strtotime('-7 days'));
    $orders = $auth->query('orders', [
        'created_at' => 'gte.' . $since,
        'order'      => 'created_at.desc',
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'summary' => 'No orders in the last 7 days.',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $lines = array_map(function ($o) {
        $date = date('j M Y', strtotime($o['created_at']));
        return "- {$o['customer_name']} | {$o['status']} | {$o['total_amount']} EUR | $date";
    }, $orders);

    $prompt = "Here are the orders from the last 7 days:\n"
            . implode("\n", $lines) . "\n\n"
            . "Summarize the key trends: total revenue, most common status, any patterns worth noting. "
            . "Keep it concise (3-5 sentences).";

    try {
        $ai      = new GeminiAI();
        $summary = $ai->ask($prompt);

        $response->getBody()->write(json_encode(['summary' => $summary]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());
