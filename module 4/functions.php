<?php
require_once 'config.php';

// --- БД (CRUD) ---
function getTables($pdo) {
    return $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
}

function getColumnsInfo($pdo, $table) {
    $stmt = $pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = [];
    $pk = null;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['Type'];
        $enum = [];
        if (preg_match('/^enum\((.*)\)$/i', $type, $matches)) {
            $enum = str_getcsv($matches[1], ',', "'");
        }
        $cols[] = [
            'name' => $row['Field'],
            'type' => $type,
            'null' => $row['Null'] === 'YES',
            'key' => $row['Key'],
            'default' => $row['Default'],
            'enum' => $enum
        ];
        if ($row['Key'] === 'PRI') $pk = $row['Field'];
    }
    return [$cols, $pk];
}

function getForeignKeys($pdo, $table) {
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$GLOBALS['db_name'], $table]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDisplayValue($pdo, $table, $id, $keyColumn) {
    if (!$id) return '';
    $displayCol = $keyColumn;
    $stmt = $pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        $name = $col['Field'];
        if (in_array($name, ['name', 'title', 'full_name', 'login', 'company_name', 'description'])) {
            $displayCol = $name;
            break;
        }
    }
    $stmt2 = $pdo->prepare("SELECT `$displayCol` FROM `$table` WHERE `$keyColumn` = ?");
    $stmt2->execute([$id]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    return $row ? $row[$displayCol] : $id;
}

function getAllRows($pdo, $table) {
    return $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
}

function getRowData($pdo, $table, $id, $pk) {
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Авторизация ---
function getUserByLogin($pdo, $login) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Проверка логина и пароля.
 * При неудачной попытке увеличивает счётчик failed_attempts.
 * Если достигнуто 3 неудачных попытки — блокирует пользователя.
 */
function checkLogin($pdo, $login, $password) {
    $user = getUserByLogin($pdo, $login);
    if (!$user) {
        return false;
    }
    // Если пользователь уже заблокирован
    if ($user['blocked'] == 1) {
        return $user; // возвращаем пользователя, но с блокировкой
    }
    // Проверка пароля
    if (password_verify($password, $user['password_hash'])) {
        // Сбрасываем счетчик неудачных попыток при успехе
        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0 WHERE id = ?");
        $stmt->execute([$user['id']]);
        return $user;
    } else {
        // Увеличиваем счетчик неудачных попыток
        $newAttempts = ($user['failed_attempts'] ?? 0) + 1;
        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
        $stmt->execute([$newAttempts, $user['id']]);
        // Если 3 неудачные попытки — блокируем
        if ($newAttempts >= 3) {
            $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
        return false;
    }
}

function createUser($pdo, $login, $password, $full_name) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, full_name, blocked, role) VALUES (?, ?, ?, 0, 'user')");
    return $stmt->execute([$login, $hash, $full_name]);
}

function setUserBlocked($pdo, $id, $blocked) {
    $stmt = $pdo->prepare("UPDATE users SET blocked = ? WHERE id = ?");
    return $stmt->execute([$blocked ? 1 : 0, $id]);
}

function getAllUsers($pdo) {
    return $pdo->query("SELECT id, login, full_name, role, blocked FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

// --- Расчёт заказа (с учётом материалов) ---
function getOrderTotal($pdo, $orderId) {
    $sql = "SELECT 
                SUM(oi.quantity * oi.price) AS total_products
            FROM order_items oi
            WHERE oi.order_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $total = $stmt->fetchColumn();

    $sql2 = "SELECT SUM(pn.quantity_per_unit * m.price * oi.quantity) 
             FROM order_items oi
             JOIN production_norms pn ON oi.product_id = pn.product_id
             JOIN materials m ON pn.material_id = m.id
             WHERE oi.order_id = ?";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$orderId]);
    $totalMaterials = $stmt2->fetchColumn();

    return $total + ($totalMaterials ?? 0);
}

// --- Логирование ---
function logAction($pdo, $userId, $action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $ip]);
}
/**
 * Универсальная валидация строковых полей
 * @param string $value Проверяемое значение
 * @param array $rules Правила: ['required' => bool, 'min' => int, 'max' => int, 'regex' => string, 'forbidden_chars' => array]
 * @return array ['isValid' => bool, 'error' => string]
 */
function validateField($value, $rules = []) {
    $value = trim($value);
    // Обязательное поле
    if (isset($rules['required']) && $rules['required'] && empty($value)) {
        return ['isValid' => false, 'error' => 'Поле обязательно для заполнения'];
    }
    // Длина
    if (isset($rules['min']) && mb_strlen($value, 'UTF-8') < $rules['min']) {
        return ['isValid' => false, 'error' => "Минимальная длина: {$rules['min']} символов"];
    }
    if (isset($rules['max']) && mb_strlen($value, 'UTF-8') > $rules['max']) {
        return ['isValid' => false, 'error' => "Максимальная длина: {$rules['max']} символов"];
    }
    // Запрещённые символы
    if (isset($rules['forbidden_chars'])) {
        foreach ($rules['forbidden_chars'] as $char) {
            if (strpos($value, $char) !== false) {
                return ['isValid' => false, 'error' => "Поле содержит запрещённый символ '$char'"];
            }
        }
    }
    // Регулярное выражение
    if (isset($rules['regex']) && !preg_match($rules['regex'], $value)) {
        return ['isValid' => false, 'error' => 'Значение не соответствует формату'];
    }
    return ['isValid' => true, 'error' => ''];
}
?>