<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include 'templates/header.php';
?>
<div class="container mt-4">
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <h1>Добро пожаловать, <?= htmlspecialchars($_SESSION['user']['login']) ?></h1>
    <div class="row mt-4">
        <div class="col-md-4">
            <a href="modules/calc_order.php?id=1" class="btn btn-success w-100 p-3">🧮 Расчёт заказа</a>
        </div>
        <!-- НОВЫЕ КНОПКИ ДЛЯ СОЗДАНИЯ И ПРОСМОТРА ЗАКАЗОВ -->
        <div class="col-md-4">
            <a href="modules/order_create.php" class="btn btn-success w-100 p-3">
                <i class="fas fa-cart-plus me-2"></i> Создать заказ
            </a>
        </div>
        <div class="col-md-4">
            <a href="modules/orders_list.php" class="btn btn-primary w-100 p-3">
                <i class="fas fa-list me-2"></i> Список заказов
            </a>
        </div>
        <div class="col-md-4">
            <a href="modules/api_client.php" class="btn btn-info w-100 p-3">🔌 API эмулятор</a>
        </div>
        <div class="col-md-4">
            <a href="modules/upload.php" class="btn btn-warning w-100 p-3">📤 Загрузка</a>
        </div>
        <div class="col-md-4">
            <a href="modules/todo.php" class="btn btn-dark w-100 p-3">📋 Список задач</a>
        </div>
        <div class="col-md-4">
            <a href="modules/profile.php" class="btn btn-secondary w-100 p-3">👤 Профиль</a>
        </div>
        <div class="col-md-4">
            <a href="admin/index.php" class="btn btn-primary w-100 p-3">👑 Админка (CRUD)</a>
        </div>
        <div class="col-md-4">
            <a href="modules/customers.php" class="btn btn-info w-100 p-3">
                <i class="fas fa-address-book me-2"></i> Список заказчиков
            </a>
        </div>
        <div class="col-md-4">
            <a href="logout.php" class="btn btn-danger w-100 p-3">🚪 Выход</a>
        </div>
    </div>
</div>
<?php include 'templates/footer.php'; ?>