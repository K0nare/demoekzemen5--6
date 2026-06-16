<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён. Только для администратора.');
}
require_once '../config.php';

$message = '';
$error = '';

// Если нажата кнопка «Установить БД»
if (isset($_POST['install'])) {
    try {
        // Сначала удаляем существующие таблицы (если есть)
        $tables = [
            'order_items', 'orders', 'production_norms', 'materials', 'products', 'customers', 'users', 'tasks', 'login_attempts', 'logs'
        ];
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        // Создаём таблицы заново
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `login` varchar(50) NOT NULL,
                `password_hash` varchar(255) NOT NULL,
                `full_name` varchar(100) DEFAULT NULL,
                `blocked` tinyint(1) DEFAULT 0,
                `role` enum('user','admin') DEFAULT 'user',
                PRIMARY KEY (`id`),
                UNIQUE KEY `login` (`login`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `text` text NOT NULL,
                `done` tinyint(1) DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `customers` (
                `id` varchar(20) NOT NULL,
                `name` varchar(255) NOT NULL,
                `inn` varchar(20) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `salesman` tinyint(1) DEFAULT 0,
                `buyer` tinyint(1) DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `products` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `code` varchar(50) DEFAULT NULL,
                `price` decimal(10,2) NOT NULL,
                `unit` varchar(20) DEFAULT 'шт',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `orders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_id` varchar(20) DEFAULT NULL,
                `order_date` date DEFAULT NULL,
                `total_amount` decimal(10,2) DEFAULT '0.00',
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `order_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) DEFAULT NULL,
                `product_id` int(11) DEFAULT NULL,
                `quantity` decimal(10,2) NOT NULL,
                `price` decimal(10,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `materials` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `code` varchar(50) DEFAULT NULL,
                `price` decimal(10,2) NOT NULL,
                `unit` varchar(20) DEFAULT 'кг',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `production_norms` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(11) DEFAULT NULL,
                `material_id` int(11) DEFAULT NULL,
                `quantity_per_unit` decimal(10,4) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `product_id` (`product_id`),
                KEY `material_id` (`material_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Импорт из Заказчики.json
        $jsonFile = realpath(__DIR__ . '/../Заказчики.json');
        if (file_exists($jsonFile)) {
            $json = file_get_contents($jsonFile);
            $data = json_decode($json, true);
            $stmt = $pdo->prepare("INSERT IGNORE INTO customers (id, name, inn, address, phone, salesman, buyer) VALUES (:id, :name, :inn, :address, :phone, :salesman, :buyer)");
            foreach ($data as $row) {
                $stmt->execute([
                    ':id' => $row['id'],
                    ':name' => $row['name'],
                    ':inn' => $row['inn'],
                    ':address' => $row['addres'],
                    ':phone' => $row['phone'],
                    ':salesman' => (int)$row['salesman'],
                    ':buyer' => (int)$row['buyer']
                ]);
            }
            $message .= "✅ Данные из Заказчики.json импортированы.<br>";
        } else {
            $message .= "⚠️ Файл Заказчики.json не найден. Пропускаем импорт.<br>";
        }

        // Добавляем тестовые товары, материалы, нормы, заказы
        $pdo->exec("
            INSERT IGNORE INTO products (id, name, code, price, unit) VALUES
            (1, 'Кефир 2,5% 900г', 'KEF-001', 80.00, 'шт'),
            (2, 'Кефир 3,2% 900г', 'KEF-002', 82.00, 'шт'),
            (3, 'Молоко 2,5% 900г', 'MOL-001', 70.00, 'шт'),
            (4, 'Сметана классическая 15% 540г', 'SME-001', 89.00, 'шт');

            INSERT IGNORE INTO materials (id, name, code, price, unit) VALUES
            (1, 'Молоко нормализованное', 'MAT-001', 34.00, 'кг'),
            (2, 'Закваска сметанная', 'MAT-002', 45.00, 'кг');

            INSERT IGNORE INTO production_norms (id, product_id, material_id, quantity_per_unit) VALUES
            (1, 4, 1, 0.9000),
            (2, 4, 2, 0.0700);

            INSERT IGNORE INTO orders (id, customer_id, order_date, total_amount) VALUES
            (1, '000000010', '2025-06-06', 2488.00);

            INSERT IGNORE INTO order_items (id, order_id, product_id, quantity, price) VALUES
            (1, 1, 1, 12.00, 80.00),
            (2, 1, 2, 9.00, 82.00),
            (3, 1, 3, 10.00, 79.00);
        ");
        $message .= "✅ Тестовые данные добавлены.<br>";

        // Создаём администратора, если его нет
        $hash = password_hash('12345', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO users (login, password_hash, full_name, blocked, role) VALUES ('admin', '$hash', 'Администратор', 0, 'admin')");
            $message .= "✅ Администратор создан.<br>";
        } else {
            $message .= "ℹ️ Администратор уже существует.<br>";
        }

        $message .= "<br><strong>✅ Установка завершена!</strong>";

    } catch (PDOException $e) {
        $error = "❌ Ошибка: " . $e->getMessage();
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Установка базы данных</h2>
    <p>Нажмите кнопку ниже, чтобы <strong>пересоздать базу данных</strong> (удалить все таблицы, создать заново и импортировать данные из <code>Заказчики.json</code>).</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
        <button type="submit" name="install" class="btn btn-danger" onclick="return confirm('Внимание! Это действие удалит все таблицы и создаст их заново. Продолжить?')">
            <i class="fas fa-database me-2"></i> Установить базу данных
        </button>
        <a href="index.php" class="btn btn-secondary">Назад в админку</a>
    </form>
</div>
<?php include '../templates/footer.php'; ?>