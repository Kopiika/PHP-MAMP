<?php

/**
 * Stock Movement Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time recording for stock movements
 * (Dashboard analytics are in dashboard.php — Exercise 7)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/stock/movements — List stock movements (authenticated)
// ============================================================
// EXERCISE 3 (Step 2): Students build this route
//
// Stock movements track inventory changes (in, out, adjustment).
// Each movement has a timestamp — this is where date/time matters most.
//
// Hints:
//   - Query the stock_movements table
//   - Join with products: 'select' => '*,products(name,sku)'
//   - Sort by newest first: 'order' => 'created_at.desc'
//   - Post-process: format dates, add relative time
//   - Optional filter: ?product_id=uuid to see movements for one product
// ============================================================

// STUB: Returns empty array until students implement Exercise 3 (Step 2).
// Replace the body of this route with your own logic.
$app->get('/api/stock/movements', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params = $request->getQueryParams();
    $query = [
        'select' => '*,products(name,sku)',
        'order'  => 'created_at.desc',
    ];
    if (!empty($params['product_id'])) {
        $query['product_id'] = 'eq.' . $params['product_id'];
    }

    $movements = $auth->query('stock_movements', $query);

    $movements = array_map(function ($row) {
        $timestamp = strtotime($row['created_at']);
        $daysAgo = floor((time() - $timestamp) / 86400);

        if ($daysAgo === 0) $relative = 'Today';
        elseif ($daysAgo === 1) $relative = 'Yesterday';
        else $relative = $daysAgo . ' days ago';

        $row['created_date'] = date('j M Y, H:i', $timestamp);
        $row['created_ago'] = $relative;
        $row['product_name'] = $row['products']['name'] ?? null;
        $row['product_sku']  = $row['products']['sku']  ?? null;

        return $row;
    }, $movements);

    $response->getBody()->write(json_encode($movements));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/stock/movements — Record a stock movement (authenticated)
// ============================================================
// EXERCISE 3 (Step 3): Students build this route
//
// When stock moves in or out, we record it AND update the product's stock_quantity.
// This is a two-step operation:
//   1. Insert the movement record
//   2. Update the product's stock_quantity
//
// The frontend sends:
//   {
//     product_id: "uuid",
//     quantity: 10,
//     movement_type: "in",       // "in", "out", or "adjustment"
//     reason: "Supplier delivery",
//     notes: "Invoice #12345"
//   }
//
// EXERCISE 3 focus: The created_at timestamp is auto-set by the database.
// But if you needed to record a movement for a past date, you could send:
//   'created_at' => date('c', strtotime('2026-03-01'))  // ISO 8601 format
//
// Hints:
//   - Validate: product_id, quantity (> 0), movement_type (in/out/adjustment)
//   - For "out" movements, check that enough stock exists
//   - Calculate new stock: for "in" add, for "out" subtract, for "adjustment" set directly
//   - Update the product's stock_quantity after inserting the movement
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 3 (Step 3).
// Replace the body of this route with your own logic.
$app->post('/api/stock/movements', function (Request $request, Response $response) {

    $body = $request->getParsedBody();

    // --- PRE-PROCESSING ---
    $productId    = $body['product_id']    ?? null;
    $quantity     = $body['quantity']      ?? null;
    $movementType = $body['movement_type'] ?? null;

    $validTypes = ['in', 'out', 'adjustment'];

    if (!$productId || $quantity === null || !$movementType) {
        $response->getBody()->write(json_encode(['error' => 'product_id, quantity, and movement_type are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if ((int)$quantity <= 0) {
        $response->getBody()->write(json_encode(['error' => 'quantity must be greater than 0']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if (!in_array($movementType, $validTypes, true)) {
        $response->getBody()->write(json_encode(['error' => 'movement_type must be in, out, or adjustment']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // --- INSERT MOVEMENT ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $movement = $auth->insert('stock_movements', [
        'product_id'    => $productId,
        'quantity'      => (int)$quantity,
        'movement_type' => $movementType,
        'reason'        => $body['reason'] ?? null,
        'notes'         => $body['notes']  ?? null,
    ]);

    // Fetch current product stock_quantity
    $products = $auth->query('products', ['id' => 'eq.' . $productId]);
    $product  = $products[0] ?? null;

    if ($product) {
        $current = (int)$product['stock_quantity'];

        if ($movementType === 'in') {
            $newQty = $current + (int)$quantity;
        } elseif ($movementType === 'out') {
            $newQty = max(0, $current - (int)$quantity);
        } else {
            // adjustment: set directly
            $newQty = (int)$quantity;
        }

        $auth->update('products', 'id=eq.' . $productId, ['stock_quantity' => $newQty]);
    }

    $response->getBody()->write(json_encode([
        'movement'      => $movement[0] ?? $movement,
        'stock_quantity' => $newQty ?? null,
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
