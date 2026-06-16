<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён. Только для администратора.');
}
require_once '../config.php';

$message = '';
$error = '';

if (isset($_POST['install'])) {
    try {
        // 1. Удаляем все таблицы, кроме системных (login_attempts, logs, validation_logs)
        $tables = [
            'order_items', 'orders', 'production_norms', 'materials', 'products',
            'customers', 'users', 'tasks', 'login_attempts', 'logs', 'validation_logs'
        ];
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        // 2. Создаём таблицы заново (полная структура из дампа work_auth (5).sql)
        $pdo->exec("
            CREATE TABLE `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `login` varchar(50) NOT NULL,
                `password_hash` varchar(255) NOT NULL,
                `full_name` varchar(100) DEFAULT NULL,
                `blocked` tinyint(1) DEFAULT 0,
                `role` enum('user','admin') DEFAULT 'user',
                `failed_attempts` int(11) DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `login` (`login`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `tasks` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `text` text NOT NULL,
                `done` tinyint(1) DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_tasks_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `customers` (
                `id` varchar(20) NOT NULL,
                `name` varchar(255) NOT NULL,
                `inn` varchar(20) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `salesman` tinyint(1) DEFAULT 0,
                `buyer` tinyint(1) DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `products` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `code` varchar(50) DEFAULT NULL,
                `price` decimal(10,2) NOT NULL,
                `unit` varchar(20) DEFAULT 'шт',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `materials` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `code` varchar(50) DEFAULT NULL,
                `price` decimal(10,2) NOT NULL,
                `unit` varchar(20) DEFAULT 'кг',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `production_norms` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(11) DEFAULT NULL,
                `material_id` int(11) DEFAULT NULL,
                `quantity_per_unit` decimal(10,4) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `product_id` (`product_id`),
                KEY `material_id` (`material_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `orders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_id` varchar(20) DEFAULT NULL,
                `order_date` date DEFAULT NULL,
                `total_amount` decimal(10,2) DEFAULT 0.00,
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `order_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) DEFAULT NULL,
                `product_id` int(11) DEFAULT NULL,
                `quantity` decimal(10,2) NOT NULL,
                `price` decimal(10,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `login_attempts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip` varchar(45) NOT NULL,
                `attempt_time` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `action` varchar(255) NOT NULL,
                `ip` varchar(45) DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `fk_logs_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `validation_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(50) NOT NULL,
                `value` varchar(255) NOT NULL,
                `status` enum('Успешно','Не успешно') NOT NULL,
                `error_message` text DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $message .= "✅ Таблицы созданы.<br>";

        // 3. Импорт заказчиков из Заказчики.json
        $jsonFile = realpath(__DIR__ . '/../Заказчики.json');
        if (file_exists($jsonFile)) {
            $json = file_get_contents($jsonFile);
            $data = json_decode($json, true);
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
            $message .= "✅ Импортировано заказчиков: " . count($data) . ".<br>";
        } else {
            $message .= "⚠️ Файл Заказчики.json не найден, импорт пропущен.<br>";
        }

        // Добавляем нового заказчика "Аква-сервис", если его нет
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = '000000011'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO customers (id, name, inn, address, phone, salesman, buyer) VALUES ('000000011', 'ООО \"Аква-сервис\"', '', 'г. Москва, ул. Водная, 12', '+79001234567', 0, 1)");
            $message .= "✅ Добавлен заказчик: ООО \"Аква-сервис\".<br>";
        }

        // 4. Добавление продуктов (бургеры, морс)
        $pdo->exec("
            INSERT INTO products (id, name, code, price, unit) VALUES
            (1, 'Бургер \"Двойной позитив\"', 'BUR-001', 440.00, 'шт'),
            (2, 'Бургер \"Душевный\"', 'BUR-002', 370.00, 'шт'),
            (3, 'Бургер \"Полная дичь\"', 'BUR-003', 440.00, 'шт'),
            (4, 'Морс клюквенный 0,5л', 'DRK-001', 70.00, 'шт');
        ");
        $message .= "✅ Продукты добавлены.<br>";

        // 5. Добавление материалов (ингредиенты)
        $pdo->exec("
            INSERT INTO materials (id, name, code, price, unit) VALUES
            (1, 'Булочка', 'MAT-001', 20.00, 'шт'),
            (2, 'Фарш говяжий', 'MAT-002', 450.00, 'кг'),
            (3, 'Помидор', 'MAT-003', 210.00, 'кг'),
            (4, 'Сыр чеддер', 'MAT-004', 780.00, 'кг'),
            (5, 'Кетчуп', 'MAT-005', 75.00, 'кг');
        ");
        $message .= "✅ Материалы добавлены.<br>";

        // 6. Нормы расхода для бургера "Двойной позитив" (id=1)
        $pdo->exec("
            INSERT INTO production_norms (product_id, material_id, quantity_per_unit) VALUES
            (1, 1, 2.0000),   -- 2 булочки
            (1, 2, 0.4000),   -- 0.4 кг фарша
            (1, 3, 0.0600),   -- 60 г помидора
            (1, 4, 0.0200),   -- 20 г сыра
            (1, 5, 0.0400);   -- 40 г кетчупа
        ");
        $message .= "✅ Нормы расхода добавлены.<br>";

        // 7. Тестовый заказ для ООО "Аква-сервис"
        $pdo->exec("
            INSERT INTO orders (id, customer_id, order_date, total_amount) VALUES
            (2, '000000011', '2025-06-06', 2920.00);
        ");
        $pdo->exec("
            INSERT INTO order_items (order_id, product_id, quantity, price) VALUES
            (2, 1, 4.00, 440.00),   -- 4 бургера \"Двойной позитив\"
            (2, 2, 2.00, 370.00),   -- 2 бургера \"Душевный\"
            (2, 4, 6.00, 70.00);    -- 6 морсов
        ");
        $message .= "✅ Заказ №2 добавлен.<br>";

        // 8. Создание администратора (если нет)
        $hash = password_hash('12345', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO users (login, password_hash, full_name, blocked, role, failed_attempts) VALUES ('admin', '$hash', 'Администратор', 0, 'admin', 0)");
            $message .= "✅ Администратор (admin / 12345) создан.<br>";
        } else {
            $message .= "ℹ️ Администратор уже существует.<br>";
        }

        $message .= "<br><strong>✅ База данных полностью пересоздана и актуализирована под бургерную продукцию!</strong>";
    } catch (PDOException $e) {
        $error = "❌ Ошибка: " . $e->getMessage();
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Установка (пересоздание) базы данных</h2>
    <p>Нажмите кнопку ниже, чтобы <strong>полностью удалить все таблицы</strong> и создать их заново с актуальной структурой, импортировать <code>Заказчики.json</code> и добавить тестовые данные (бургеры, ингредиенты, нормы расхода, заказ).</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
        <button type="submit" name="install" class="btn btn-danger" onclick="return confirm('Внимание! Все существующие данные будут удалены. Продолжить?')">
            <i class="fas fa-database me-2"></i> Пересоздать БД
        </button>
        <a href="index.php" class="btn btn-secondary">Назад в админку</a>
    </form>
</div>
<?php include '../templates/footer.php'; ?>