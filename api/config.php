<?php
// Sesuaikan kredensial DB-mu
define('DB_HOST', 'localhost');
define('DB_NAME', 'showroom');
define('DB_USER', 'root');
define('DB_PASS', '');

// CORS sederhana untuk Android
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}
