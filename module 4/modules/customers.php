<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';

include '../templates/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-building me-2"></i>Список заказчиков</h2>

    <?php
    try {
        $stmt = $pdo->query("SELECT * FROM customers ORDER BY id");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($customers)) {
            echo '<div class="alert alert-warning">Таблица customers пуста или не существует.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-hover">';
            echo '<thead class="table-light">';
            echo '<tr><th>ID</th><th>Название</th><th>ИНН</th><th>Адрес</th><th>Телефон</th><th>Продавец</th><th>Покупатель</th></tr>';
            echo '</thead><tbody>';
            foreach ($customers as $c) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($c['id']) . '</td>';
                echo '<td>' . htmlspecialchars($c['name']) . '</td>';
                echo '<td>' . htmlspecialchars($c['inn'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['address'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($c['phone'] ?? '') . '</td>';
                echo '<td>' . ($c['salesman'] ? 'Да' : 'Нет') . '</td>';
                echo '<td>' . ($c['buyer'] ? 'Да' : 'Нет') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Ошибка базы данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <a href="../index.php" class="btn btn-secondary mt-3">← На главную</a>
</div>

<?php include '../templates/footer.php'; ?>