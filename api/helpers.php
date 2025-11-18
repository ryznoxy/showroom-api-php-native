<?php
function json($code, $payload)
{
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function ok($data = null)
{
  json(200, ['success' => true, 'data' => $data]);
}
function created($data = null)
{
  json(201, ['success' => true, 'data' => $data]);
}
function bad($msg)
{
  json(400, ['success' => false, 'error' => $msg]);
}
function unauthorized($msg = 'Unauthorized')
{
  json(401, ['success' => false, 'error' => $msg]);
}
function forbidden($msg = 'Forbidden')
{
  json(403, ['success' => false, 'error' => $msg]);
}
function notfound($msg = 'Not found')
{
  json(404, ['success' => false, 'error' => $msg]);
}

function body()
{
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function bearerToken()
{
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/Bearer\s+(\S+)/i', $h, $m)) return $m[1];
  return null;
}

function authUser()
{
  $token = bearerToken();
  if (!$token) return null;
  $stmt = db()->prepare("SELECT id,name,email,role,api_token FROM users WHERE api_token = ?");
  $stmt->execute([$token]);
  return $stmt->fetch() ?: null;
}

function requireAuth()
{
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? '';
  if (preg_match('/Bearer\s+(\w+)/', $auth, $m)) {
    $token = $m[1];
    $stmt = db()->prepare("SELECT * FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) return $user;
  }
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Token tidak valid atau tidak ada']);
  exit;
}

function requireRole($user, $role)
{
  if ($user['role'] !== $role) forbidden('Akses ditolak untuk role ini');
}
