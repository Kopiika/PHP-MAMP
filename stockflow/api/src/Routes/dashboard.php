<?php

/**
 * Dashboard Routes
 *
 * EXERCISE 7: Aggregate data into dashboard summaries
 *
 * This is the capstone exercise — it combines everything:
 * pre-processing, date handling, and data aggregation.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/dashboard/summary — Dashboard overview (authenticated)
// ============================================================
// EXERCISE 7: Students build this route
//
// Fetch data from multiple tables and calculate summary statistics.
// This is ALL post-processing — the database gives you raw data,
// you crunch it in PHP before sending to the frontend.
//
// The frontend expects:
//   {
//     inventory: {
//       total_products: 18,
//       total_value: 12450.00,       // sum of (price * stock_quantity)
//       low_stock_count: 4,          // products where stock <= threshold
//       out_of_stock_count: 2        // products where stock = 0
//     },
//     orders: {
//       total_orders: 5,
//       by_status: {
//         draft: 1,
//         confirmed: 1,
//         fulfilled: 2,
//         cancelled: 1
//       },
//       total_revenue: 2602.00       // sum of fulfilled order totals
//     },
//     low_stock_products: [          // top 5 most urgent
//       { name: "...", stock_quantity: 2, reorder_threshold: 10 },
//       ...
//     ]
//   }
//
// Hints:
//   - Fetch all products: $auth->query('products', ['select' => '*'])
//   - Fetch all orders: $auth->query('orders', ['select' => '*'])
//   - Use PHP array functions to calculate:
//     array_filter() — filter arrays by condition
//     array_sum()    — sum values
//     array_map()    — transform arrays
//     count()        — count items
//     usort()        — sort arrays with custom comparison
//   - For total_value: loop products, sum up (price * stock_quantity)
//   - For low_stock: filter where stock_quantity <= reorder_threshold AND stock > 0
//   - For revenue: filter orders where status === 'fulfilled', then sum total_amount
// ============================================================

$app->get('/api/dashboard/summary', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch all products and orders from Supabase
    $products = $auth->query('products', ['select' => '*']);
    $orders   = $auth->query('orders',   ['select' => '*']);

    // --- INVENTORY STATS ---
    $totalValue    = 0;
    $outOfStock    = 0;
    $lowStock      = [];

    foreach ($products as $product) {
        $qty       = (int)$product['stock_quantity'];
        $threshold = (int)$product['reorder_threshold'];
        $price     = (float)$product['price'];

        $totalValue += $price * $qty;

        if ($qty === 0) {
            $outOfStock++;
        } elseif ($qty <= $threshold) {
            $lowStock[] = [
                'name'              => $product['name'],
                'stock_quantity'    => $qty,
                'reorder_threshold' => $threshold,
            ];
        }
    }

    // Sort low stock by most urgent (lowest stock first)
    usort($lowStock, fn($a, $b) => $a['stock_quantity'] - $b['stock_quantity']);
    $top5LowStock = array_slice($lowStock, 0, 5);

    // --- ORDER STATS ---
    $byStatus = ['draft' => 0, 'confirmed' => 0, 'fulfilled' => 0, 'cancelled' => 0];
    $totalRevenue = 0;

    foreach ($orders as $order) {
        $status = $order['status'] ?? 'draft';
        if (isset($byStatus[$status])) {
            $byStatus[$status]++;
        }
        if ($status === 'fulfilled') {
            $totalRevenue += (float)$order['total_amount'];
        }
    }

    $summary = [
        'inventory' => [
            'total_products'    => count($products),
            'total_value'       => round($totalValue, 2),
            'low_stock_count'   => count($lowStock),
            'out_of_stock_count' => $outOfStock,
        ],
        'orders' => [
            'total_orders'  => count($orders),
            'by_status'     => $byStatus,
            'total_revenue' => round($totalRevenue, 2),
        ],
        'low_stock_products' => $top5LowStock,
    ];

    $response->getBody()->write(json_encode($summary));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
