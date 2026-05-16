-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Май 15 2026 г., 12:00
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `Kursach_Shein`
--

-- --------------------------------------------------------

--
-- Структура таблицы `cars`
--

CREATE TABLE `cars` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `vin` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint UNSIGNED NOT NULL,
  `gosnumber` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `cars`
--

INSERT INTO `cars` (`id`, `user_id`, `vin`, `brand`, `model`, `year`, `gosnumber`) VALUES
(1, 5, 'XTA210930Y1234567', 'Lada', 'Vesta', 2020, 'A123AA77'),
(2, 6, 'WVWZZZ1JZXW000001', 'Volkswagen', 'Golf', 2018, 'M456MM77'),
(3, 7, 'VF3MJAHXVHS101043', 'Toyota', 'Camry', 2019, 'А003АА159');

-- --------------------------------------------------------

--
-- Структура таблицы `mechanic_assignments`
--

CREATE TABLE `mechanic_assignments` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `mechanic_id` int UNSIGNED NOT NULL,
  `assigned_by_master_id` int UNSIGNED NOT NULL,
  `status` enum('assigned','reassigned','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `mechanic_assignments`
--

INSERT INTO `mechanic_assignments` (`id`, `order_id`, `mechanic_id`, `assigned_by_master_id`, `status`, `comment`, `assigned_at`, `updated_at`) VALUES
(1, 1, 3, 2, 'assigned', 'Первичное назначение', '2026-05-08 09:45:00', NULL),
(2, 2, 4, 2, 'assigned', 'Срочный заказ', '2026-05-09 09:10:00', NULL),
(3, 3, 3, 2, 'assigned', 'Просмотри что там у него, попробуй содрать побольше денег', '2026-05-10 11:33:58', NULL),
(4, 4, 3, 2, 'assigned', 'Разведи лошару', '2026-05-11 13:49:43', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL,
  `client_id` int UNSIGNED NOT NULL,
  `car_id` int UNSIGNED NOT NULL,
  `mechanic_id` int UNSIGNED DEFAULT NULL,
  `master_id` int UNSIGNED DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('new','assigned','in_progress','waiting_parts','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `description` text COLLATE utf8mb4_unicode_ci,
  `parts_comment` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_comment` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `client_id`, `car_id`, `mechanic_id`, `master_id`, `total_price`, `start_date`, `end_date`, `status`, `description`, `parts_comment`, `cancel_comment`, `created_at`) VALUES
(1, 5, 1, 3, 2, '4300.00', '2026-05-08 10:00:00', NULL, 'in_progress', 'Плановое ТО + диагностика', NULL, NULL, '2026-05-09 08:28:05'),
(2, 6, 2, 4, 2, '3200.00', '2026-05-09 09:30:00', NULL, 'waiting_parts', 'Нужна замена передних колодок', NULL, NULL, '2026-05-09 08:28:05'),
(3, 7, 3, 3, 2, '0.00', NULL, NULL, 'completed', 'Нужна диагностика', NULL, NULL, '2026-05-10 07:29:10'),
(4, 7, 3, 3, 2, '2500.00', NULL, NULL, 'completed', 'что-то не понятное', NULL, NULL, '2026-05-11 10:48:43');

-- --------------------------------------------------------

--
-- Структура таблицы `order_parts`
--

CREATE TABLE `order_parts` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `part_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `price_each` decimal(10,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_by_mechanic_id` int UNSIGNED DEFAULT NULL,
  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `order_parts`
--

-- (нет данных)

-- --------------------------------------------------------

--
-- Структура таблицы `order_services`
--

CREATE TABLE `order_services` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `order_services`
--

INSERT INTO `order_services` (`id`, `order_id`, `service_id`, `quantity`, `comment`) VALUES
(1, 1, 1, 1, 'Использовать масло клиента'),
(2, 1, 2, 1, NULL),
(3, 2, 3, 1, NULL),
(4, 3, 1, 1, ''),
(5, 4, 1, 1, '');

-- --------------------------------------------------------

--
-- Структура таблицы `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `old_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_user_id` int UNSIGNED NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `order_status_history`
--

-- (нет данных)

-- --------------------------------------------------------

--
-- Структура таблицы `parts`
--

CREATE TABLE `parts` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `article` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `parts`
--

INSERT INTO `parts` (`id`, `name`, `article`, `price`, `quantity`, `description`, `image`) VALUES
(1, 'Масляный фильтр', 'OF-001', '650.00', 25, 'Фильтр для ТО', NULL),
(2, 'Моторное масло 5W-30 (1л)', 'OIL-530-1L', '900.00', 60, 'Синтетическое масло', NULL),
(3, 'Тормозные колодки передние', 'BP-FRONT-01', '2500.00', 4, 'Комплект передних колодок', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `part_purchase_requests`
--

CREATE TABLE `part_purchase_requests` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `part_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL,
  `requested_by_master_id` int UNSIGNED DEFAULT NULL,
  `requested_by_mechanic_id` int UNSIGNED DEFAULT NULL,
  `approved_by_admin_id` int UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected','ordered','received') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `part_purchase_requests`
--

INSERT INTO `part_purchase_requests` (`id`, `order_id`, `part_id`, `quantity`, `requested_by_master_id`, `requested_by_mechanic_id`, `approved_by_admin_id`, `status`, `comment`, `created_at`, `resolved_at`) VALUES
(1, 2, 3, 2, 2, NULL, 1, 'approved', 'Низкий остаток на складе', '2026-05-09 10:20:00', '2026-05-09 11:05:00');

-- --------------------------------------------------------

--
-- Структура таблицы `services`
--

CREATE TABLE `services` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `services`
--

INSERT INTO `services` (`id`, `name`, `price`, `description`, `image`, `category`) VALUES
(1, 'Замена масла', '2500.00', 'Замена моторного масла и фильтра', NULL, 'ТО'),
(2, 'Диагностика подвески', '1800.00', 'Проверка состояния элементов подвески', NULL, 'Диагностика'),
(3, 'Замена тормозных колодок', '3200.00', 'Замена передних тормозных колодок', NULL, 'Ремонт');

-- --------------------------------------------------------

--
-- Структура таблицы `service_parts`
--

CREATE TABLE `service_parts` (
  `id` int UNSIGNED NOT NULL,
  `service_id` int UNSIGNED NOT NULL,
  `part_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `service_parts`
--

INSERT INTO `service_parts` (`id`, `service_id`, `part_id`, `quantity`) VALUES
(1, 1, 1, 1),
(2, 1, 2, 4),
(3, 3, 3, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('client','mechanic','master','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `full_name`, `phone`, `email`, `role`, `password`, `is_active`, `created_at`) VALUES
(1, 'Супер Админ', '+79990000001', 'admin@service.local', 'admin', '$2y$10$elXXUNzxALWT24729nWfce9NN4RJJ9oRVjAqdNex26t1LW3IwZ0fq', 1, '2026-05-09 08:28:04'),
(2, 'Иван Мастер', '+79990000002', 'master@service.local', 'master', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:28:04'),
(3, 'Петр Механик', '+79990000003', 'mechanic1@service.local', 'mechanic', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:28:04'),
(4, 'Сергей Механик', '+79990000004', 'mechanic2@service.local', 'mechanic', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:28:04'),
(5, 'Алексей Клиент', '+79990000005', 'client1@mail.local', 'client', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:28:04'),
(6, 'Мария Клиент', '+79990000006', 'client2@mail.local', 'client', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:28:04'),
(7, 'Роберт Шейн', '89999999999', 'robert@gmail.com', 'client', '$2y$10$9vY6ygfzwAegh2DtxQAZZuiyjD4s9ycSb7MBGmHyzXJd./l80i12S', 1, '2026-05-09 08:44:24');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cars_vin` (`vin`),
  ADD KEY `idx_cars_user_id` (`user_id`);

--
-- Индексы таблицы `mechanic_assignments`
--
ALTER TABLE `mechanic_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ma_order_id` (`order_id`),
  ADD KEY `fk_ma_mechanic` (`mechanic_id`),
  ADD KEY `fk_ma_master` (`assigned_by_master_id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_client_id` (`client_id`),
  ADD KEY `idx_orders_car_id` (`car_id`),
  ADD KEY `idx_orders_mechanic_id` (`mechanic_id`),
  ADD KEY `idx_orders_master_id` (`master_id`);

--
-- Индексы таблицы `order_parts`
--
ALTER TABLE `order_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_op_order_id` (`order_id`),
  ADD KEY `fk_op_part` (`part_id`),
  ADD KEY `fk_op_mechanic` (`added_by_mechanic_id`);

--
-- Индексы таблицы `order_services`
--
ALTER TABLE `order_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_services_order` (`order_id`),
  ADD KEY `fk_order_services_service` (`service_id`);

--
-- Индексы таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_osh_order_id` (`order_id`),
  ADD KEY `fk_osh_user` (`changed_by_user_id`);

--
-- Индексы таблицы `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_parts_article` (`article`);

--
-- Индексы таблицы `part_purchase_requests`
--
ALTER TABLE `part_purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ppr_order_id` (`order_id`),
  ADD KEY `fk_ppr_part` (`part_id`),
  ADD KEY `fk_ppr_master` (`requested_by_master_id`),
  ADD KEY `fk_ppr_mechanic` (`requested_by_mechanic_id`),
  ADD KEY `fk_ppr_admin` (`approved_by_admin_id`);

--
-- Индексы таблицы `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `service_parts`
--
ALTER TABLE `service_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_parts` (`service_id`,`part_id`),
  ADD KEY `fk_service_parts_part` (`part_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `cars`
--
ALTER TABLE `cars`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `mechanic_assignments`
--
ALTER TABLE `mechanic_assignments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `order_parts`
--
ALTER TABLE `order_parts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT для таблицы `order_services`
--
ALTER TABLE `order_services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT для таблицы `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `part_purchase_requests`
--
ALTER TABLE `part_purchase_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `services`
--
ALTER TABLE `services`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `service_parts`
--
ALTER TABLE `service_parts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `cars`
--
ALTER TABLE `cars`
  ADD CONSTRAINT `fk_cars_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mechanic_assignments`
--
ALTER TABLE `mechanic_assignments`
  ADD CONSTRAINT `fk_ma_master` FOREIGN KEY (`assigned_by_master_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ma_mechanic` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ma_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_master` FOREIGN KEY (`master_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_mechanic` FOREIGN KEY (`mechanic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_parts`
--
ALTER TABLE `order_parts`
  ADD CONSTRAINT `fk_op_mechanic` FOREIGN KEY (`added_by_mechanic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_op_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_op_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_services`
--
ALTER TABLE `order_services`
  ADD CONSTRAINT `fk_order_services_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `part_purchase_requests`
--
ALTER TABLE `part_purchase_requests`
  ADD CONSTRAINT `fk_ppr_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ppr_master` FOREIGN KEY (`requested_by_master_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ppr_mechanic` FOREIGN KEY (`requested_by_mechanic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ppr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ppr_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `service_parts`
--
ALTER TABLE `service_parts`
  ADD CONSTRAINT `fk_service_parts_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_service_parts_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
