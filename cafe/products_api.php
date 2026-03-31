<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db.php';

$result = mysqli_query($conn, 'SELECT id, name, category, description, price, image_url, is_available FROM products WHERE is_available = 1 ORDER BY category ASC, name ASC');

$products = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['price']        = (float) $row['price'];
        $row['is_available'] = (int)   $row['is_available'];

        // Fix path for locally uploaded images so the browser can find them
        // The API is at  /activity/cafe/products_api.php
        // Uploaded files are at  /activity/uploads/filename.jpg
        // So we prefix local paths with  ../
        if ($row['image_url'] && strpos($row['image_url'], 'http') !== 0) {
            $row['image_url'] = '../' . ltrim($row['image_url'], '/');
        }

        $products[] = $row;
    }
    mysqli_free_result($result);
}

mysqli_close($conn);
echo json_encode($products);
