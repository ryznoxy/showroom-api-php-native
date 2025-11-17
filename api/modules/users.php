<?php
function users_list()
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $stmt = db()->query("SELECT id,name,email,role,created_at,updated_at FROM users ORDER BY created_at DESC");
  return ok($stmt->fetchAll());
}

function users_detail($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $stmt = db()->prepare("SELECT id,name,email,role,created_at,updated_at FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $user = $stmt->fetch();
  if (!$user) return notfound('User tidak ditemukan');
  return ok($user);
}

function users_create()
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $b = body();
  $name = trim($b['name'] ?? '');
  $email = strtolower(trim($b['email'] ?? ''));
  $password = $b['password'] ?? '';
  $role = $b['role'] ?? 'customer';
  if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    return bad('Nama, email valid, password >=6');
  }
  if (!in_array($role, ['admin', 'customer'])) return bad('Role tidak valid');

  $hash = password_hash($password, PASSWORD_BCRYPT);
  try {
    $stmt = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
    $stmt->execute([$name, $email, $hash, $role]);
  } catch (PDOException $e) {
    return bad('Email sudah dipakai');
  }
  return created(['id' => db()->lastInsertId()]);
}

function users_update($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $b = body();
  $fields = ['name', 'email', 'role', 'password'];
  $set = [];
  $vals = [];

  if (isset($b['name'])) {
    $set[] = 'name = ?';
    $vals[] = $b['name'];
  }
  if (isset($b['email'])) {
    $set[] = 'email = ?';
    $vals[] = $b['email'];
  }
  if (isset($b['role'])) {
    if (!in_array($b['role'], ['admin', 'customer'])) return bad('Role tidak valid');
    $set[] = 'role = ?';
    $vals[] = $b['role'];
  }
  if (isset($b['password'])) {
    $set[] = 'password_hash = ?';
    $vals[] = password_hash($b['password'], PASSWORD_BCRYPT);
  }

  if (!$set) return bad('Tidak ada perubahan');
  $vals[] = $id;

  $sql = "UPDATE users SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?";
  $stmt = db()->prepare($sql);
  try {
    $stmt->execute($vals);
  } catch (PDOException $e) {
    return bad('Gagal update (email mungkin duplikat)');
  }
  return ok(['message' => 'User diperbarui']);
}

function users_delete($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
  $stmt->execute([$id]);
  return ok(['message' => 'User dihapus']);
}
