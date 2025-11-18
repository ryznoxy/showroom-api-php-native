<?php
// function orders_list()
// {
//   $u = requireAuth();
//   if ($u['role'] === 'admin') {
//     $stmt = db()->query("SELECT id,user_id,order_code,status,total,created_at,updated_at FROM orders ORDER BY created_at DESC");
//     return ok($stmt->fetchAll());
//   } else {
//     $stmt = db()->prepare("SELECT id,user_id,order_code,status,total,created_at,updated_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
//     $stmt->execute([$u['id']]);
//     return ok($stmt->fetchAll());
//   }
// }

// function orders_detail($id)
// {
//   $u = requireAuth();
//   $stmt = db()->prepare("SELECT id,user_id,order_code,status,total,created_at,updated_at FROM orders WHERE id = ?");
//   $stmt->execute([$id]);
//   $o = $stmt->fetch();
//   if (!$o) return notfound('Pesanan tidak ditemukan');

//   if ($u['role'] !== 'admin' && (int)$o['user_id'] !== (int)$u['id']) return forbidden('Tidak boleh melihat pesanan orang lain');

//   return ok($o);
// }

// function orders_create()
// {
//   $u = requireAuth();
//   if ($u['role'] !== 'customer' && $u['role'] !== 'admin') return forbidden('Role tidak valid');

//   $pdo = db();
//   $pdo->beginTransaction();
//   try {
//     // ambil keranjang user
//     $itemsStmt = $pdo->prepare("
//             SELECT ci.id, ci.car_id, ci.qty, ci.price, c.stock
//             FROM cart_items ci JOIN cars c ON c.id = ci.car_id
//             WHERE ci.user_id = ?");
//     $itemsStmt->execute([$u['id']]);
//     $items = $itemsStmt->fetchAll();
//     if (!$items) throw new Exception('Keranjang kosong');

//     // cek stok
//     foreach ($items as $it) {
//       if ((int)$it['qty'] > (int)$it['stock']) throw new Exception('Stok tidak cukup untuk salah satu item');
//     }

//     // total & kode
//     $total = 0.0;
//     foreach ($items as $it) {
//       $total += $it['qty'] * $it['price'];
//     }
//     $order_code = strtoupper(bin2hex(random_bytes(8))); // 16 hex chars

//     // buat order
//     $ins = $pdo->prepare("INSERT INTO orders (user_id,order_code,status,total) VALUES (?,?, 'pending', ?)");
//     $ins->execute([$u['id'], $order_code, $total]);
//     $order_id = $pdo->lastInsertId();

//     // kurangi stok & bersihkan keranjang
//     foreach ($items as $it) {
//       $updStock = $pdo->prepare("UPDATE cars SET stock = stock - ? WHERE id = ?");
//       $updStock->execute([$it['qty'], $it['car_id']]);
//     }
//     $delCart = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
//     $delCart->execute([$u['id']]);

//     $pdo->commit();
//     return created(['id' => $order_id, 'order_code' => $order_code, 'total' => $total]);
//   } catch (Exception $e) {
//     $pdo->rollBack();
//     return bad($e->getMessage());
//   }
// }

function orders_list()
{
  $u = requireAuth();
  if ($u['role'] === 'admin') {
    $stmt = db()->query("SELECT id,user_id,order_code,status,total,address,payment_method,created_at,updated_at FROM orders ORDER BY created_at DESC");
    return ok($stmt->fetchAll());
  } else {
    $stmt = db()->prepare("SELECT id,user_id,order_code,status,total,address,payment_method,created_at,updated_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$u['id']]);
    return ok($stmt->fetchAll());
  }
}

function orders_detail($id)
{
  $u = requireAuth();
  $stmt = db()->prepare("SELECT id,user_id,order_code,status,total,address,payment_method,created_at,updated_at FROM orders WHERE id = ?");
  $stmt->execute([$id]);
  $o = $stmt->fetch();
  if (!$o) return notfound('Pesanan tidak ditemukan');

  if ($u['role'] !== 'admin' && (int)$o['user_id'] !== (int)$u['id']) return forbidden('Tidak boleh melihat pesanan orang lain');

  return ok($o);
}

function orders_create()
{
  $u = requireAuth();
  if ($u['role'] !== 'customer' && $u['role'] !== 'admin') return forbidden('Role tidak valid');

  $b = body();
  $address = trim($b['address'] ?? '');
  $payment_method = $b['payment_method'] ?? '';

  if ($address === '') return bad('Alamat wajib diisi');
  if (!in_array($payment_method, ['cash', 'transfer', 'ewallet'])) return bad('Metode pembayaran tidak valid');

  $pdo = db();
  $pdo->beginTransaction();
  try {
    // ambil keranjang user
    $itemsStmt = $pdo->prepare("
            SELECT ci.id, ci.car_id, ci.qty, ci.price, c.stock
            FROM cart_items ci JOIN cars c ON c.id = ci.car_id
            WHERE ci.user_id = ?");
    $itemsStmt->execute([$u['id']]);
    $items = $itemsStmt->fetchAll();
    if (!$items) throw new Exception('Keranjang kosong');

    // cek stok
    foreach ($items as $it) {
      if ((int)$it['qty'] > (int)$it['stock']) throw new Exception('Stok tidak cukup untuk salah satu item');
    }

    // total & kode
    $total = 0.0;
    foreach ($items as $it) {
      $total += $it['qty'] * $it['price'];
    }
    $order_code = strtoupper(bin2hex(random_bytes(8))); // 16 hex chars

    // buat order dengan address & payment_method
    $ins = $pdo->prepare("INSERT INTO orders (user_id,order_code,status,total,address,payment_method) VALUES (?,?, 'pending', ?, ?, ?)");
    $ins->execute([$u['id'], $order_code, $total, $address, $payment_method]);
    $order_id = $pdo->lastInsertId();

    // kurangi stok & bersihkan keranjang
    foreach ($items as $it) {
      $updStock = $pdo->prepare("UPDATE cars SET stock = stock - ? WHERE id = ?");
      $updStock->execute([$it['qty'], $it['car_id']]);
    }
    $delCart = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $delCart->execute([$u['id']]);

    $pdo->commit();
    return created(['id' => $order_id, 'order_code' => $order_code, 'total' => $total, 'address' => $address, 'payment_method' => $payment_method]);
  } catch (Exception $e) {
    $pdo->rollBack();
    return bad($e->getMessage());
  }
}

function orders_update_status($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $b = body();
  $status = $b['status'] ?? '';
  if (!in_array($status, ['pending', 'delivered', 'cancelled'])) return bad('Status tidak valid');

  $stmt = db()->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$status, $id]);

  return ok(['message' => 'Status pesanan diperbarui']);
}

function orders_delete($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $stmt = db()->prepare("DELETE FROM orders WHERE id = ?");
  $stmt->execute([$id]);
  return ok(['message' => 'Pesanan dihapus']);
}