<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';
require_once '../functions.php';

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

// Удаление заказа
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Ошибка CSRF');
    }
    $order_id = (int)$_POST['order_id'];
    if ($order_id > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
            $pdo->commit();
            logAction($pdo, $_SESSION['user']['id'], "Удалён заказ №$order_id");
            $_SESSION['flash'] = "Заказ №$order_id удалён.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash'] = "Ошибка удаления: " . $e->getMessage();
        }
    }
    header('Location: orders_list.php');
    exit;
}

// Пересчёт: обновляем total_amount в таблице orders (теперь это будет выручка)
if ($isAdmin && isset($_GET['recalc'])) {
    $order_id = (int)$_GET['recalc'];
    if ($order_id > 0) {
        // Вычисляем выручку (сумма продукции)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity * price), 0) FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $revenue = (float)$stmt->fetchColumn();
        // Обновляем total_amount (теперь это выручка)
        $upd = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $upd->execute([$revenue, $order_id]);
        $_SESSION['flash'] = "Заказ №$order_id пересчитан. Выручка: " . number_format($revenue, 2) . " руб.";
    }
    header('Location: orders_list.php');
    exit;
}

// Получаем список заказов с вычисленной выручкой (на случай, если total_amount не обновлён)
$sql = "
    SELECT 
        o.id, 
        o.order_date, 
        c.name AS customer_name,
        COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id, o.order_date, c.name
    ORDER BY o.id DESC
";
$orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

include '../templates/header.php';
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-list me-2"></i>Список заказов</h2>
        <a href="order_create.php" class="btn btn-success">+ Новый заказ</a>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-info alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">Заказов пока нет.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Дата</th>
                        <th>Покупатель</th>
                        <th>Выручка (руб.)</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['order_date']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name'] ?? '—') ?></td>
                            <td><?= number_format($order['revenue'], 2) ?></td>
                            <td>
                                <a href="calc_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-info">Детали</a>
                                <?php if ($isAdmin): ?>
                                    <a href="?recalc=<?= $order['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Пересчитать выручку заказа №<?= $order['id'] ?>?')">⟳ Пересчитать</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Удалить заказ №<?= $order['id'] ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" name="delete_order" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> Удалить</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <a href="../index.php" class="btn btn-secondary mt-3">← На главную</a>
</div>
<?php include '../templates/footer.php'; ?>