<?php
function auth_register()
{
  $b = body();
  $name = trim($b['name'] ?? '');
  $email = strtolower(trim($b['email'] ?? ''));
  $password = $b['password'] ?? '';

  if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    return bad('Nama, email valid, dan password >= 6 karakter wajib diisi');
  }

  $hash = password_hash($password, PASSWORD_BCRYPT);
  try {
    $stmt = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?, 'customer')");
    $stmt->execute([$name, $email, $hash]);
  } catch (PDOException $e) {
    return bad('Email sudah terdaftar');
  }

  return created(['message' => 'Registrasi berhasil']);
}

function auth_login()
{
  $b = body();
  $email = strtolower(trim($b['email'] ?? ''));
  $password = $b['password'] ?? '';
  if (!$email || !$password) return bad('Email dan password wajib diisi');

  $stmt = db()->prepare("SELECT id,name,email,password_hash,role FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if (!$u || !password_verify($password, $u['password_hash'])) {
    return unauthorized('Email atau password salah');
  }
  $token = bin2hex(random_bytes(32));
  $upd = db()->prepare("UPDATE users SET api_token = ?, updated_at = NOW() WHERE id = ?");
  $upd->execute([$token, $u['id']]);

  return ok(['token' => $token, 'user' => ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']]]);
}

function auth_logout()
{
  $u = requireAuth();
  $upd = db()->prepare("UPDATE users SET api_token = NULL, updated_at = NOW() WHERE id = ?");
  $upd->execute([$u['id']]);
  return ok(['message' => 'Logout berhasil']);
}
