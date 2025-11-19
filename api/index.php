<?php

header('Content-Type: application/json');
require 'config.php';
require __DIR__ . '/../vendor/autoload.php';

use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// -----------------------------
// GRAPHQL ENDPOINT
// -----------------------------
if ($endpoint === 'graphql') {

    $productType = new ObjectType([
        'name' => 'Product',
        'fields' => [
            'id' => Type::int(),
            'name' => Type::string(),
            'price' => Type::float()
        ]
    ]);

    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'products' => [
                'type' => Type::listOf($productType),
                'resolve' => function() use ($pdo) {
                    $stmt = $pdo->query("SELECT id, name, price FROM products ORDER BY id ASC");
                    return $stmt->fetchAll();
                }
            ]
        ]
    ]);

    $schema = new Schema([
        'query' => $queryType
    ]);

    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';

    try {
        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();
    } catch (Exception $e) {
        http_response_code(500);
        $output = ['error' => $e->getMessage()];
    }

    echo json_encode($output);
    exit;
}

// -----------------------------
// REST ENDPOINTS
// -----------------------------
if ($endpoint === 'products') {
    switch ($method) {
        case 'GET':
            getProducts($pdo);
            break;
        case 'POST':
            addProduct($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint Not Found']);
}

// -----------------------------
// GET /products
// -----------------------------
function getProducts($pdo) {
    $stmt = $pdo->query("SELECT id, name, price FROM products ORDER BY id ASC");
    $products = $stmt->fetchAll();
    echo json_encode($products);
}

// -----------------------------
// POST /products
// -----------------------------
function addProduct($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['name']) || empty(trim($input['name']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Product name is required']);
        return;
    }

    if (!isset($input['price']) || !is_numeric($input['price'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Price must be numeric']);
        return;
    }

    $name = trim($input['name']);
    $price = number_format((float)$input['price'], 2, '.', '');

    $stmt = $pdo->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
    $stmt->execute(['name' => $name, 'price' => $price]);

    http_response_code(201);
    echo json_encode(['message' => 'Product added', 'id' => $pdo->lastInsertId()]);
}
?>
