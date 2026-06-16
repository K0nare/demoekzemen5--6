<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён. Только для администратора.');
}
require_once '../config.php';

$message = '';
$error = '';

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file'];
    
    // Проверка на ошибки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла. Код: ' . $file['error'];
    } else {
        // Проверяем тип файла
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            $error = 'Разрешены только файлы с расширением .json';
        } else {
            // Читаем содержимое
            $json = file_get_contents($file['tmp_name']);
            $data = json_decode($json, true);
            
            if ($data === null) {
                $error = 'Ошибка декодирования JSON. Проверьте структуру файла.';
            } else {
                // Начинаем транзакцию
                $pdo->beginTransaction();
                try {
                    // Очищаем таблицу customers перед импортом
                    $pdo->exec("DELETE FROM customers");
                    
                    // Вставляем данные
                    $stmt = $pdo->prepare("INSERT INTO customers (id, name, inn, address, phone, salesman, buyer) VALUES (:id, :name, :inn, :address, :phone, :salesman, :buyer)");
                    
                    foreach ($data as $row) {
                        $stmt->execute([
                            ':id' => $row['id'],
                            ':name' => $row['name'],
                            ':inn' => $row['inn'] ?? '',
                            ':address' => $row['addres'] ?? '',
                            ':phone' => $row['phone'] ?? '',
                            ':salesman' => (int)($row['salesman'] ?? 0),
                            ':buyer' => (int)($row['buyer'] ?? 0)
                        ]);
                    }
                    
                    $pdo->commit();
                    $message = '✅ Импорт завершён. Загружено ' . count($data) . ' записей.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Ошибка при импорте: ' . $e->getMessage();
                }
            }
        }
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Импорт Заказчики.json</h2>
    <p>Загрузите файл <code>Заказчики.json</code> для импорта в таблицу <code>customers</code>.</p>
    <p><strong>Внимание:</strong> Все существующие данные в таблице <code>customers</code> будут удалены перед импортом.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="json_file" class="form-label">Выберите файл .json</label>
            <input type="file" class="form-control" id="json_file" name="json_file" accept=".json" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload me-2"></i> Импортировать
        </button>
        <a href="index.php" class="btn btn-secondary">Назад в админку</a>
    </form>
</div>
<?php include '../templates/footer.php'; ?>