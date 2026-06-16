-- phpMyAdmin SQL Dump
-- version 5.1.3-3.red80
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Июн 05 2026 г., 08:55
-- Версия сервера: 10.11.11-MariaDB
-- Версия PHP: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `work_auth`
--

-- --------------------------------------------------------

--
-- Структура таблицы `customers`
--

CREATE TABLE `customers` (
  `id` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `inn` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `salesman` tinyint(1) DEFAULT 0,
  `buyer` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `customers`
--

INSERT INTO `customers` (`id`, `name`, `inn`, `address`, `phone`, `salesman`, `buyer`) VALUES
('000000001', 'ООО \"Поставка\"', '', 'г.Пятигорск', '+79198634592', 1, 1),
('000000002', 'ООО \"Кинотеатр Квант\"', '26320045123', 'г. Железноводск, ул. Мира, 123', '+79884581555', 1, 0),
('000000003', 'ООО \"Ромашка\"', '4140784214', 'г. Омск, ул. Строителей, 294', '+79882584546', 0, 1),
('000000008', 'ООО \"Новый JDTO\"', '26320045111', 'г. Железноводсу', '+79884581555', 1, 0),
('000000009', 'ООО \"Ипподром\"', '5874045632', 'г. Уфа, ул. Набережная,  37', '+79627486389', 1, 1),
('000000010', 'ООО \"Ассоль\"', '2629011278', 'г. Калуга, ул. Пушкина, 94', '+79184572398', 0, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `ip`, `created_at`) VALUES
(1, 1, 'Заблокирован пользователь ID 2', '::1', '2026-06-05 14:08:25'),
(2, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 14:08:57'),
(3, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 14:10:20'),
(4, 1, 'Создан заказ №3', '::1', '2026-06-05 15:09:21'),
(5, 1, 'Создан заказ №4', '::1', '2026-06-05 15:15:28'),
(6, 1, 'Удалён заказ №4', '::1', '2026-06-05 15:15:43'),
(7, 1, 'Удалён заказ №3', '::1', '2026-06-05 15:15:45'),
(8, 1, 'Создан заказ №5', '::1', '2026-06-05 15:22:19'),
(9, 1, 'Удалён заказ №5', '::1', '2026-06-05 15:23:27'),
(10, 1, 'Заблокирован пользователь ID 2', '::1', '2026-06-05 15:31:19'),
(11, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 15:31:28'),
(12, 2, 'Заблокирован из-за 3 ошибок капчи', '::1', '2026-06-05 15:42:03'),
(13, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 15:42:36'),
(14, 2, 'Заблокирован из-за 3 ошибок капчи', '::1', '2026-06-05 15:46:18'),
(15, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 15:47:11'),
(16, 1, 'Добавлен пользователь admin+', '::1', '2026-06-05 15:49:06'),
(17, 1, 'Разблокирован пользователь ID 2', '::1', '2026-06-05 15:49:15');

-- --------------------------------------------------------

--
-- Структура таблицы `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'кг'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `materials`
--

INSERT INTO `materials` (`id`, `name`, `code`, `price`, `unit`) VALUES
(1, 'Булочка', 'MAT-001', '20.00', 'шт'),
(2, 'Фарш говяжий', 'MAT-002', '450.00', 'кг'),
(3, 'Помидор', 'MAT-003', '210.00', 'кг'),
(4, 'Сыр чеддер', 'MAT-004', '780.00', 'кг'),
(5, 'Кетчуп', 'MAT-005', '75.00', 'кг'),
(6, 'Закваска сметанная', 'MAT-006', '45.00', 'кг'),
(7, 'Молоко нормализованное', 'MAT-007', '34.00', 'л');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(20) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_date`, `total_amount`) VALUES
(2, '000000010', '2025-06-06', '2920.00');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 2, 1, '4.00', '440.00'),
(2, 2, 2, '2.00', '370.00'),
(3, 2, 4, '6.00', '70.00');

-- --------------------------------------------------------

--
-- Структура таблицы `production_norms`
--

CREATE TABLE `production_norms` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `quantity_per_unit` decimal(10,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `production_norms`
--

INSERT INTO `production_norms` (`id`, `product_id`, `material_id`, `quantity_per_unit`) VALUES
(1, 1, 1, '2.0000'),
(2, 1, 2, '0.4000'),
(3, 1, 3, '0.0600'),
(4, 1, 4, '0.0200'),
(5, 1, 5, '0.0400'),
(6, 2, 1, '2.0000'),
(7, 2, 2, '0.4000'),
(8, 2, 3, '0.0600'),
(9, 2, 4, '0.0200'),
(10, 2, 5, '0.0400');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'шт'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `code`, `price`, `unit`) VALUES
(1, 'Бургер \"Двойной позитив\"', 'BUR-001', '450.00', 'шт'),
(2, 'Бургер \"Душевный\"', 'BUR-002', '370.00', 'шт'),
(3, 'Бургер \"Полная дичь\"', 'BUR-003', '440.00', 'шт'),
(4, 'Морс клюквенный 0,5л', 'DRK-001', '70.00', 'шт'),
(5, 'Латте \"Ваниль\" 250г.', NULL, '210.00', 'шт'),
(6, 'Сок апельсиновый 1л.', NULL, '270.00', 'шт'),
(7, 'Булочка', NULL, '20.00', 'шт'),
(8, 'Фарш говяжий', NULL, '450.00', 'кг'),
(9, 'Помидор', NULL, '210.00', 'кг'),
(10, 'Сыр чеддер', NULL, '780.00', 'кг'),
(11, 'Кетчуп', NULL, '75.00', 'кг'),
(12, 'Закваска сметанная', NULL, '45.00', 'кг'),
(13, 'Молоко нормализованное', NULL, '34.00', 'л');

-- --------------------------------------------------------

--
-- Структура таблицы `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `text` text NOT NULL,
  `done` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `blocked` tinyint(1) DEFAULT 0,
  `role` enum('user','admin') DEFAULT 'user',
  `failed_attempts` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password_hash`, `full_name`, `blocked`, `role`, `failed_attempts`) VALUES
(1, 'admin', '$2y$10$i7PT7aQmG2K67QMzTwjVWO8yu8vjqDaGKKpDvDV1N6NrCBIhQe/Z6', 'Администратор', 0, 'admin', 0),
(2, 'user', '$2y$10$xqvrE7LlC9Lalq1gCYUWv./MwYQK.tzbp33ePabraPPszEp5rAxXa', 'Пользователь', 0, 'user', 0),
(3, 'admin+', '$2y$10$dlW848SdER5qBJUgouFEauw8d.RbnCvguaSl68QdoRCWBmxHFZkcy', 'Супер админ', 0, 'admin', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `validation_logs`
--

CREATE TABLE `validation_logs` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  `status` enum('Успешно','Не успешно') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `validation_logs`
--

INSERT INTO `validation_logs` (`id`, `type`, `value`, `status`, `error_message`, `created_at`) VALUES
(1, 'fullName', 'Никифоров+ Никифор+ Никифорович', 'Не успешно', 'ФИО содержит запрещённые символы (цифры, знаки = + ( ) и т.д.)', '2026-06-05 13:44:13'),
(2, 'email', 'sidorov.sidor@outlook.com', 'Успешно', NULL, '2026-06-05 14:12:52'),
(3, 'email', 'sidorov.sidor@outlook.com', 'Успешно', NULL, '2026-06-05 14:14:05'),
(4, 'email', 'morozov.morozov@gmail.com', 'Успешно', NULL, '2026-06-05 14:14:07'),
(5, 'email', 'kuznetsov.kuzma@protonmail.com kuznetsov.kuzma@protonmail.com', 'Не успешно', 'Неверный формат email', '2026-06-05 14:14:29'),
(6, 'email', 'stepanov.stepan@aol.com stepanov.stepan@aol.com', 'Не успешно', 'Неверный формат email', '2026-06-05 14:15:54'),
(7, 'email', 'ivanov.ivan@gmail.com@example.com', 'Не успешно', 'Неверный формат email', '2026-06-05 14:16:29'),
(8, 'email', 'kuznetsov.kuzma@protonmail.com', 'Успешно', NULL, '2026-06-05 14:16:43'),
(9, 'email', 'morozov.morozov@gmail.com', 'Успешно', NULL, '2026-06-05 15:17:52'),
(10, 'email', 'petrov.petr@yahoo.com', 'Успешно', NULL, '2026-06-05 15:18:11'),
(11, 'email', 'sidorov.sidor@outlook.com;sidorov.sidor@outlook.com', 'Не успешно', 'Неверный формат email', '2026-06-05 15:18:23'),
(12, 'email', 'kuznetsov.kuzma@protonmail.com kuznetsov.kuzma@protonmail.com', 'Не успешно', 'Неверный формат email', '2026-06-05 15:18:35'),
(13, 'email', 'lebedev.lebed@outlook.com lebedev.lebed@outlook.com', 'Не успешно', 'Неверный формат email', '2026-06-05 15:18:52'),
(14, 'email', 'ebedev.lebed@outlook.com lebedev.lebed@outlook.com', 'Не успешно', 'Неверный формат email', '2026-06-05 15:19:25'),
(15, 'email', 'nikiforov.nikifor@protonmail.com nikiforov.nikifor@protonmail.com', 'Не успешно', 'Неверный формат email', '2026-06-05 15:38:47'),
(16, 'email', 'lebedev.lebed@outlook.com', 'Успешно', NULL, '2026-06-05 15:51:16');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logs_user` (`user_id`);

--
-- Индексы таблицы `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `production_norms`
--
ALTER TABLE `production_norms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tasks_user` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Индексы таблицы `validation_logs`
--
ALTER TABLE `validation_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `production_norms`
--
ALTER TABLE `production_norms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `validation_logs`
--
ALTER TABLE `validation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `production_norms`
--
ALTER TABLE `production_norms`
  ADD CONSTRAINT `fk_norms_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_norms_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
