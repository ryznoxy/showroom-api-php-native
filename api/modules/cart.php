<?php
function cart_list()
{
  $u = requireAuth();

  $stmt = db()->prepare("
        SELECT ci.id, ci.car_id, c.title, c.brand, c.model, ci.qty, ci.price,
               (ci.qty * ci.price) AS subtotal
        FROM cart_items ci
        JOIN cars c ON c.id = ci.car_id
        WHERE ci.user_id = ?
        ORDER BY ci.created_at DESC");
  $stmt->execute([$u['id']]);
  $items = $stmt->fetchAll();

  $total = 0;
  foreach ($items as $it) $total += (float)$it['subtotal'];
  return ok(['items' => $items, 'total' => $total]);
}

function cart_add()
{
  $u = requireAuth();
  $b = body();
  $car_id = (int)($b['car_id'] ?? 0);
  $qty = max(1, (int)($b['qty'] ?? 1));

  // ambil snapshot price + cek stok
  $stmt = db()->prepare("SELECT id, price, stock FROM cars WHERE id = ?");
  $stmt->execute([$car_id]);
  $car = $stmt->fetch();
  if (!$car) return notfound('Mobil tidak ditemukan');
  if ($qty > (int)$car['stock']) return bad('Qty melebihi stok');

  // upsert unik (user_id, car_id)
  $pdo = db();
  $pdo->beginTransaction();
  try {
    // cek ada item
    $chk = $pdo->prepare("SELECT id, qty FROM cart_items WHERE user_id = ? AND car_id = ?");
    $chk->execute([$u['id'], $car_id]);
    $item = $chk->fetch();
    if ($item) {
      $newQty = $item['qty'] + $qty;
      if ($newQty > (int)$car['stock']) throw new Exception('Qty melebihi stok');
      $up = $pdo->prepare("UPDATE cart_items SET qty = ?, price = ?, updated_at = NOW() WHERE id = ?");
      $up->execute([$newQty, $car['price'], $item['id']]);
      $id = $item['id'];
    } else {
      $ins = $pdo->prepare("INSERT INTO cart_items (user_id, car_id, qty, price) VALUES (?,?,?,?)");
      $ins->execute([$u['id'], $car_id, $qty, $car['price']]);
      $id = $pdo->lastInsertId();
    }
    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    return bad($e->getMessage());
  }
  return created(['id' => $id]);
}

function cart_update_qty($item_id)
{
  $u = requireAuth();
  $b = body();
  $qty = max(1, (int)($b['qty'] ?? 1));

  // ambil item & stok mobil
  $stmt = db()->prepare("
        SELECT ci.id, ci.user_id, ci.car_id, ci.price, c.stock
        FROM cart_items ci JOIN cars c ON c.id = ci.car_id
        WHERE ci.id = ?");
  $stmt->execute([$item_id]);
  $item = $stmt->fetch();
  if (!$item || (int)$item['user_id'] !== (int)$u['id']) return notfound('Item tidak ditemukan');

  if ($qty > (int)$item['stock']) return bad('Qty melebihi stok');

  $upd = db()->prepare("UPDATE cart_items SET qty = ?, updated_at = NOW() WHERE id = ?");
  $upd->execute([$qty, $item_id]);
  return ok(['message' => 'Qty diperbarui']);
}

function cart_remove($item_id)
{
  $u = requireAuth();
  $stmt = db()->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
  $stmt->execute([$item_id, $u['id']]);
  return ok(['message' => 'Item dihapus']);
}

function cart_clear()
{
  $u = requireAuth();
  $stmt = db()->prepare("DELETE FROM cart_items WHERE user_id = ?");
  $stmt->execute([$u['id']]);
  return ok(['message' => 'Keranjang dikosongkan']);
}
