<?php

/**
 * Orders Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time handling (timestamps, relative dates)
 * - Exercise 6: CRUD operations for orders and order items
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/orders — List orders (authenticated)
// ============================================================
// Currently returns raw order data.
//
// EXERCISE 3: Add date/time post-processing:
//   - Format 'created_at' as a human-readable date (e.g., "9 Mar 2026, 14:30")
//   - Add a 'created_ago' field with relative time (e.g., "2 days ago")
//   - Add an 'age_days' field (number of days since creation)
//   - Format 'total_amount' as currency with 2 decimal places
//
// EXERCISE 5 (Step 1): Add filtering:
//   - Filter by status: ?status=confirmed
//   - Sort by date: ?sort=created_at&order=desc
//
// PHP date/time hints:
//   $timestamp = strtotime($row['created_at']);         // Parse ISO date to Unix timestamp
//   $formatted = date('j M Y, H:i', $timestamp);       // "9 Mar 2026, 14:30"
//   $daysAgo = floor((time() - $timestamp) / 86400);   // 86400 = seconds in a day
//
//   For relative time, you can build a simple helper:
//   if ($daysAgo === 0) return 'Today';
//   if ($daysAgo === 1) return 'Yesterday';
//   return $daysAgo . ' days ago';
// ============================================================

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params  = $request->getQueryParams();
    $status  = $params['status'] ?? null;
    $sort    = $params['sort'] ?? 'created_at';
    $orderDir = $params['order'] ?? 'desc';

    $queryParams = ['order' => $sort . '.' . $orderDir];

    if ($status) {
        $queryParams['status'] = 'eq.' . $status;
    }

    $orders = $auth->query('orders', $queryParams);

    // --- POST-PROCESSING (Exercise 3) ---
    $orders = array_map(function ($row) {
        $timestamp = strtotime($row['created_at']);
        $daysAgo = floor((time() - $timestamp) / 86400);

        if ($daysAgo === 0) $relative = 'Today';
        elseif ($daysAgo === 1) $relative = 'Yesterday';
        else $relative = $daysAgo . ' days ago';

        $row['created_date'] = date('j M Y, H:i', $timestamp);
        $row['created_ago'] = $relative;
        $row['total_amount'] = number_format((float)$row['total_amount'], 2, '.', '');

        return $row;
    }, $orders);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());


// ============================================================
// GET /api/orders/{id} — Get single order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 2): Students build this route
//
// Hints:
//   - Fetch the order: query('orders', ['id' => 'eq.' . $id])
//   - Fetch its items: query('order_items', ['order_id' => 'eq.' . $id])
//   - Combine them: $order['items'] = $items
//   - Return 404 if order not found
//   - Apply the same date formatting from Exercise 3
// ============================================================

$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args) {

    $id   = $args['id'];
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $results = $auth->query('orders', ['id' => 'eq.' . $id]);

    if (empty($results)) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $order = $results[0];

    // Format dates
    $timestamp = strtotime($order['created_at']);
    $daysAgo   = floor((time() - $timestamp) / 86400);
    $order['created_date'] = date('j M Y, H:i', $timestamp);
    $order['created_ago']  = $daysAgo === 0 ? 'Today' : ($daysAgo === 1 ? 'Yesterday' : $daysAgo . ' days ago');
    $order['total_amount'] = number_format((float)$order['total_amount'], 2, '.', '');

    // Fetch items for this order
    $order['items'] = $auth->query('order_items', ['order_id' => 'eq.' . $id]);

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/orders — Create an order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 3): Students build this route
//
// This is the most complex exercise — creating an order involves:
//   1. Validate the order data (customer_name required)
//   2. Insert the order (without items first)
//   3. Loop through items and insert each one
//   4. Calculate the total_amount from the items
//   5. Update the order with the calculated total
//
// The frontend sends:
//   {
//     customer_name: "Company Oy",
//     notes: "Rush order",
//     items: [
//       { product_id: "uuid", product_name: "Widget", quantity: 3, unit_price: 29.99 },
//       { product_id: "uuid", product_name: "Gadget", quantity: 1, unit_price: 49.99 }
//     ]
//   }
//
// EXERCISE 3 (bonus): Record timestamps correctly:
//   - The database auto-sets created_at, but you should understand that
//     Supabase stores timestamps in UTC (TIMESTAMPTZ)
//   - When displaying, the frontend handles timezone conversion
//   - If you need to set a date manually in PHP: date('c') gives ISO 8601 format
// ============================================================

$app->post('/api/orders', function (Request $request, Response $response) {

    $body = $request->getParsedBody();

    // Validate required fields
    if (empty($body['customer_name'])) {
        $response->getBody()->write(json_encode(['error' => 'customer_name is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (empty($body['items']) || !is_array($body['items'])) {
        $response->getBody()->write(json_encode(['error' => 'Order must have at least one item']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    foreach ($body['items'] as $i => $item) {
        if (empty($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
            $response->getBody()->write(json_encode(['error' => "Item $i is missing product_id, quantity, or unit_price"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Step 1: Insert the order with total_amount = 0
    $orderResult = $auth->insert('orders', [
        'customer_name' => trim($body['customer_name']),
        'notes'         => trim($body['notes'] ?? ''),
        'status'        => 'draft',
        'total_amount'  => 0,
    ]);
    $orderId = $orderResult[0]['id'];

    // Step 2: Insert each item and sum up the total
    $totalAmount = 0;
    $insertedItems = [];

    foreach ($body['items'] as $item) {
        $lineTotal    = round((float)$item['unit_price'] * (int)$item['quantity'], 2);
        $totalAmount += $lineTotal;

        $inserted = $auth->insert('order_items', [
            'order_id'     => $orderId,
            'product_id'   => $item['product_id'],
            'product_name' => trim($item['product_name'] ?? ''),
            'quantity'     => (int)$item['quantity'],
            'unit_price'   => (float)$item['unit_price'],
            'line_total'   => $lineTotal,
        ]);
        $insertedItems[] = $inserted[0];
    }

    // Step 3: Update the order with the real total
    $auth->update('orders', 'id=eq.' . $orderId, ['total_amount' => $totalAmount]);

    $orderResult[0]['total_amount'] = number_format($totalAmount, 2, '.', '');
    $orderResult[0]['items']        = $insertedItems;

    $response->getBody()->write(json_encode($orderResult[0]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/orders/{id}/status — Update order status (authenticated)
// ============================================================
// EXERCISE 5 (Step 4): Students build this route
//
// This teaches state machine logic — not every status transition is valid:
//   draft → confirmed → fulfilled
//   draft → cancelled
//   confirmed → cancelled
//
// Hints:
//   - Fetch the current order to check its current status
//   - Define valid transitions as an array:
//     $validTransitions = [
//         'draft' => ['confirmed', 'cancelled'],
//         'confirmed' => ['fulfilled', 'cancelled'],
//     ];
//   - Return 400 if the transition is not valid
//   - Use $auth->update() to change the status
// ============================================================

$app->put('/api/orders/{id}/status', function (Request $request, Response $response, array $args) {

    $id        = $args['id'];
    $body      = $request->getParsedBody();
    $newStatus = $body['status'] ?? null;

    $validStatuses = ['draft', 'confirmed', 'fulfilled', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid status: ' . $newStatus]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $results = $auth->query('orders', ['id' => 'eq.' . $id]);
    if (empty($results)) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $currentStatus = $results[0]['status'];

    // State machine — only these transitions are allowed
    $validTransitions = [
        'draft'     => ['confirmed', 'cancelled'],
        'confirmed' => ['fulfilled', 'cancelled'],
    ];

    $allowed = $validTransitions[$currentStatus] ?? [];
    if (!in_array($newStatus, $allowed)) {
        $response->getBody()->write(json_encode([
            'error' => "Cannot change status from '$currentStatus' to '$newStatus'"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $updated = $auth->update('orders', 'id=eq.' . $id, ['status' => $newStatus]);

    $response->getBody()->write(json_encode($updated[0] ?? ['status' => $newStatus]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
