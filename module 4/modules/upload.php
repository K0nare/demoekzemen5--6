<?php
require_once '../config.php';
require_once '../functions.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Ошибка загрузки';
    } elseif (!in_array($file['type'], $allowed)) {
        $message = 'Разрешены только JPG и PNG';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $message = 'Файл не более 2 Мб';
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = time() . '.' . $ext;
        if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
        if (move_uploaded_file($file['tmp_name'], '../uploads/' . $newName)) {
            $message = 'Файл загружен: ' . $newName;
            $uploaded = $newName;
        } else {
            $message = 'Не удалось сохранить файл';
        }
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Загрузка изображения</h2>
    <?php if ($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/jpeg,image/png" required>
        <button type="submit" class="btn btn-primary">Загрузить</button>
    </form>
    <?php if (isset($uploaded)): ?>
        <p><img src="../uploads/<?= $uploaded ?>" width="200" class="mt-3"></p>
    <?php endif; ?>
    <a href="../index.php" class="btn btn-secondary mt-3">← Назад</a>
</div>
<?php include '../templates/footer.php'; ?>