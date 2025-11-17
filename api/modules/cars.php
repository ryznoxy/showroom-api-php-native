<?php
function cars_list()
{
  $stmt = db()->query("SELECT id,title,brand,model,year,price,stock,image_path,description FROM cars ORDER BY created_at DESC");
  return ok($stmt->fetchAll());
}

function cars_detail($id)
{
  $stmt = db()->prepare("SELECT id,title,brand,model,year,price,stock,image_path,description FROM cars WHERE id = ?");
  $stmt->execute([$id]);
  $car = $stmt->fetch();
  if (!$car) return notfound('Mobil tidak ditemukan');
  return ok($car);
}

function cars_create()
{
  $u = requireAuth();
  requireRole($u, 'admin');

  // ambil data dari form-data (bukan JSON)
  $title = $_POST['title'] ?? '';
  $brand = $_POST['brand'] ?? '';
  $model = $_POST['model'] ?? '';
  $year  = (int)($_POST['year'] ?? 0);
  $price = (float)($_POST['price'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  $desc  = $_POST['description'] ?? null;

  if (!$title || !$brand || !$model || !$year || !$price) {
    return bad('Field wajib diisi');
  }

  // handle upload file jika ada
  $filename = null;
  if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    if (!isset($allowed[$mime])) return bad('Format file harus JPG/PNG');

    $ext = $allowed[$mime];
    $filename = uniqid('car_', true) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    move_uploaded_file($_FILES['image']['tmp_name'], $targetDir . $filename);
  }

  $stmt = db()->prepare("INSERT INTO cars (title,brand,model,year,price,stock,image_path,description) VALUES (?,?,?,?,?,?,?,?)");
  $stmt->execute([$title, $brand, $model, $year, $price, $stock, $filename, $desc]);

  return created(['id' => db()->lastInsertId(), 'image' => $filename]);
}


function cars_update($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  // ambil data dari form-data
  $fields = [];
  $vals   = [];

  foreach (['title', 'brand', 'model', 'year', 'price', 'stock', 'description'] as $f) {
    if (isset($_POST[$f])) {
      $fields[] = "$f = ?";
      $vals[]   = $_POST[$f];
    }
  }

  // handle upload file jika ada
  if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    if (!isset($allowed[$mime])) return bad('Format file harus JPG/PNG');

    $ext = $allowed[$mime];
    $filename = uniqid('car_', true) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    move_uploaded_file($_FILES['image']['tmp_name'], $targetDir . $filename);

    $fields[] = "image_path = ?";
    $vals[]   = $filename;
  }

  if (!$fields) return bad('Tidak ada perubahan');
  $vals[] = $id;

  $sql = "UPDATE cars SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
  $stmt = db()->prepare($sql);
  $stmt->execute($vals);

  return ok(['message' => 'Mobil diperbarui']);
}


function cars_delete($id)
{
  $u = requireAuth();
  requireRole($u, 'admin');

  $stmt = db()->prepare("DELETE FROM cars WHERE id = ?");
  $stmt->execute([$id]);
  return ok(['message' => 'Mobil dihapus (jika terkait pesanan mungkin gagal oleh constraint)']);
}
