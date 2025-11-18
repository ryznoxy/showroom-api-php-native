<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Ambil method dan path
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Buang prefix folder + index.php
// Sesuaikan dengan struktur folder kamu
$path = preg_replace('#^/showroomapi/api/index.php#', '', $path);
$path = trim($path, '/');

// Include modul
require_once __DIR__ . '/modules/auth.php';
require_once __DIR__ . '/modules/cars.php';
require_once __DIR__ . '/modules/users.php';
require_once __DIR__ . '/modules/cart.php';
require_once __DIR__ . '/modules/orders.php';

// Routing
switch (true) {
  // AUTH
  case $path === 'auth/register' && $method === 'POST':
    auth_register();
    exit;
  case $path === 'auth/login' && $method === 'POST':
    auth_login();
    exit;
  case $path === 'auth/logout' && $method === 'POST':
    auth_logout();
    exit;

    // CARS
  case $path === 'cars' && $method === 'GET':
    if (isset($_GET['q']) && $_GET['q'] !== '') {
      cars_search($_GET['q']);
    } else {
      cars_list();
    }
    exit;

  case preg_match('#^cars/(\d+)$#', $path, $m) && $method === 'GET':
    cars_detail((int)$m[1]);
    exit;

  case $path === 'cars' && $method === 'POST':
    cars_create();
    exit;

  case preg_match('#^cars/(\d+)$#', $path, $m) && ($method === 'PUT' || $method === 'PATCH'):
    cars_update((int)$m[1]);
    exit;

  case preg_match('#^cars/(\d+)$#', $path, $m) && $method === 'DELETE':
    cars_delete((int)$m[1]);
    exit;

    // USERS (admin)
  case $path === 'users' && $method === 'GET':
    users_list();
    exit;
  case preg_match('#^users/(\d+)$#', $path, $m) && $method === 'GET':
    users_detail((int)$m[1]);
    exit;
  case $path === 'users' && $method === 'POST':
    users_create();
    exit;
  case preg_match('#^users/(\d+)$#', $path, $m) && ($method === 'PUT' || $method === 'PATCH'):
    users_update((int)$m[1]);
    exit;
  case preg_match('#^users/(\d+)$#', $path, $m) && $method === 'DELETE':
    users_delete((int)$m[1]);
    exit;

    // CART
  case $path === 'cart' && $method === 'GET':
    cart_list();
    exit;
  case $path === 'cart/items' && $method === 'POST':
    cart_add();
    exit;
  case preg_match('#^cart/items/(\d+)$#', $path, $m) && ($method === 'PUT' || $method === 'PATCH'):
    cart_update_qty((int)$m[1]);
    exit;
  case preg_match('#^cart/items/(\d+)$#', $path, $m) && $method === 'DELETE':
    cart_remove((int)$m[1]);
    exit;
  case $path === 'cart/clear' && $method === 'POST':
    cart_clear();
    exit;

    // ORDERS
  case $path === 'orders' && $method === 'GET':
    orders_list();
    exit;
  case $path === 'orders' && $method === 'POST':
    orders_create();
    exit;
  case preg_match('#^orders/(\d+)$#', $path, $m) && $method === 'GET':
    orders_detail((int)$m[1]);
    exit;
  case preg_match('#^orders/(\d+)$#', $path, $m) && ($method === 'PUT' || $method === 'PATCH'):
    orders_update_status((int)$m[1]);
    exit;
  case preg_match('#^orders/(\d+)$#', $path, $m) && $method === 'DELETE':
    orders_delete((int)$m[1]);
    exit;

  default:
    notfound('Endpoint tidak ditemukan');
    exit;
}
