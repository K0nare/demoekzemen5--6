<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён. Только для администратора.');
}
require_once '../config.php';
require_once '../functions.php';

// --- Список таблиц (исключаем системные) ---
$tables = getTables($pdo);
$tables = array_filter($tables, function($t) {
    return !in_array($t, ['login_attempts', 'logs']);
});

// --- Определяем режим ---
$mode = $_GET['mode'] ?? '';

// ==================== ОБЩИЕ ПЕРЕМЕННЫЕ ====================
$selectedTable = $_GET['table'] ?? '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// Если режим не выбран — показываем кнопки выбора
if (!$mode) {
    include 'templates/header.php';
    ?>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">Административная панель</h1>
        <div class="row justify-content-center">
            <div class="col-md-5">
                <a href="?mode=crud" class="btn btn-primary w-100 p-4 mb-3" style="font-size: 1.5rem;">
                    <i class="fas fa-database me-2"></i> CRUD интерфейс
                </a>
                <p class="text-muted text-center">Работа со всеми таблицами системы (включая пользователей)</p>
            </div>
            <div class="col-md-5">
                <a href="?mode=users" class="btn btn-success w-100 p-4 mb-3" style="font-size: 1.5rem;">
                    <i class="fas fa-users me-2"></i> Управление пользователями
                </a>
                <p class="text-muted text-center">Специализированный интерфейс для управления пользователями</p>
            </div>
        </div>
        <div class="row justify-content-center mt-3">
            <div class="col-md-5">
                <a href="setup_db.php" class="btn btn-danger w-100 p-3" style="font-size: 1.2rem;">
                    <i class="fas fa-database me-2"></i> Установка базы данных
                </a>
                <p class="text-muted text-center">Пересоздать БД и импортировать Заказчики.json</p>
            </div>
            <div class="col-md-5">
                <a href="import_json.php" class="btn btn-warning w-100 p-3" style="font-size: 1.2rem;">
                    <i class="fas fa-upload me-2"></i> Импорт .json
                </a>
                <p class="text-muted text-center">Импортировать Заказчики.json в таблицу customers</p>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="../index.php" class="btn btn-secondary">← На главную</a>
        </div>
    </div>
    <?php
    include 'templates/footer.php';
    exit;
}

// ==================== ФУНКЦИЯ ДЛЯ ВЫВОДА СПЕЦИАЛИЗИРОВАННОГО ИНТЕРФЕЙСА ПОЛЬЗОВАТЕЛЕЙ (режим users) ====================
function renderUserManagementInterface(
    PDO $pdo,
    int $page,
    int $limit,
    int $offset,
    string $search,
    string $action,
    ?int $id
): void {
    // --- Обработка POST (добавление/редактирование пользователей) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            die('Ошибка CSRF токена');
        }

        if ($_POST['action'] === 'add_user') {
            $login = trim($_POST['login'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $blocked = isset($_POST['blocked']) ? 1 : 0;

            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
            $stmt->execute([$login]);
            if ($stmt->fetch()) {
                $_SESSION['flash'] = '❌ Пользователь с таким логином уже существует.';
                header('Location: ?mode=users&search=' . urlencode($search) . '&page=' . $page);
                exit;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, full_name, role, blocked) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$login, $hash, $full_name, $role, $blocked]);
            logAction($pdo, $_SESSION['user']['id'], "Добавлен пользователь $login");
            $_SESSION['flash'] = '✅ Пользователь добавлен.';
            header('Location: ?mode=users&search=' . urlencode($search) . '&page=' . $page);
            exit;
        } elseif ($_POST['action'] === 'edit_user' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $blocked = isset($_POST['blocked']) ? 1 : 0;
            $new_password = $_POST['new_password'] ?? '';

            if ($new_password !== '') {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, blocked = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$full_name, $role, $blocked, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, blocked = ? WHERE id = ?");
                $stmt->execute([$full_name, $role, $blocked, $id]);
            }
            logAction($pdo, $_SESSION['user']['id'], "Изменён пользователь ID $id");
            $_SESSION['flash'] = '✅ Пользователь обновлён.';
            header('Location: ?mode=users&search=' . urlencode($search) . '&page=' . $page);
            exit;
        }
    }

    // --- Обработка GET-действий (block/unblock/delete) ---
    if (isset($_GET['action']) && in_array($_GET['action'], ['block', 'unblock', 'delete'])) {
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            die('Ошибка CSRF токена');
        }
        $id = (int)$_GET['id'];
        if ($_GET['action'] === 'block') {
            setUserBlocked($pdo, $id, 1);
            logAction($pdo, $_SESSION['user']['id'], "Заблокирован пользователь ID $id");
            $_SESSION['flash'] = 'Пользователь заблокирован.';
        } elseif ($_GET['action'] === 'unblock') {
            setUserBlocked($pdo, $id, 0);
            logAction($pdo, $_SESSION['user']['id'], "Разблокирован пользователь ID $id");
            $_SESSION['flash'] = 'Пользователь разблокирован.';
        } elseif ($_GET['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            logAction($pdo, $_SESSION['user']['id'], "Удалён пользователь ID $id");
            $_SESSION['flash'] = 'Пользователь удалён.';
        }
        header('Location: ?mode=users&search=' . urlencode($search) . '&page=' . $page);
        exit;
    }

    // --- Поиск и пагинация ---
    $countSql = "SELECT COUNT(*) FROM users";
    $countParams = [];
    if ($search) {
        $countSql .= " WHERE login LIKE ? OR full_name LIKE ?";
        $countParams = ["%$search%", "%$search%"];
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = $stmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    $sql = "SELECT * FROM users";
    if ($search) {
        $sql .= " WHERE login LIKE ? OR full_name LIKE ?";
        $params = ["%$search%", "%$search%"];
    } else {
        $params = [];
    }
    $sql .= " ORDER BY id LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $paramIndex = 1;
    foreach ($params as $p) {
        $stmt->bindValue($paramIndex++, $p, PDO::PARAM_STR);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Генерация CSRF-токена
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-users me-2"></i> Управление пользователями</h2>
        <a href="?mode=users&action=add_user" class="btn btn-success">
            <i class="fas fa-plus"></i> Добавить пользователя
        </a>
    </div>

    <form method="get" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Поиск..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-primary">🔍</button>
            <?php if ($search): ?>
                <a href="?mode=users" class="btn btn-outline-secondary">Сбросить</a>
            <?php endif; ?>
        </div>
        <input type="hidden" name="mode" value="users">
    </form>

    <?php if ($rows): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr><th>ID</th><th>Логин</th><th>Имя</th><th>Роль</th><th>Статус</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['login']) ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['role']) ?></td>
                            <td><?= $row['blocked'] ? '<span class="badge bg-danger">Заблокирован</span>' : '<span class="badge bg-success">Активен</span>' ?></td>
                            <td>
                                <?php if ($row['blocked']): ?>
                                    <a href="?mode=users&action=unblock&id=<?= $row['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-success">Разблокировать</a>
                                <?php else: ?>
                                    <a href="?mode=users&action=block&id=<?= $row['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-danger">Заблокировать</a>
                                <?php endif; ?>
                                <a href="?mode=users&action=edit_user&id=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                <a href="?mode=users&action=delete&id=<?= $row['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Удалить?')">Удалить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?mode=users&page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">Нет пользователей для отображения.</div>
    <?php endif; ?>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'add_user'): ?>
        <!-- Форма добавления пользователя -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-plus-circle me-2"></i> Добавить пользователя
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Логин</label>
                            <input type="text" name="login" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Пароль</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Имя</label>
                            <input type="text" name="full_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Роль</label>
                            <select name="role" class="form-select">
                                <option value="user">Пользователь</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 form-check">
                        <input type="checkbox" name="blocked" class="form-check-input" id="blockedAdd">
                        <label class="form-check-label" for="blockedAdd">Заблокирован</label>
                    </div>
                    <button type="submit" class="btn btn-success mt-2">Добавить</button>
                    <a href="?mode=users&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-secondary mt-2">Отмена</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'edit_user' && isset($_GET['id'])): ?>
        <?php
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <?php if ($editUser): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning text-white">
                <i class="fas fa-edit me-2"></i> Редактирование пользователя <?= htmlspecialchars($editUser['login']) ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <label>Имя</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($editUser['full_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Роль</label>
                            <select name="role" class="form-select">
                                <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>Пользователь</option>
                                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Новый пароль (оставьте пустым, если не меняете)</label>
                            <input type="password" name="new_password" class="form-control">
                        </div>
                    </div>
                    <div class="mt-2 form-check">
                        <input type="checkbox" name="blocked" class="form-check-input" id="blockedEdit" <?= $editUser['blocked'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="blockedEdit">Заблокирован</label>
                    </div>
                    <button type="submit" class="btn btn-warning mt-2">Сохранить</button>
                    <a href="?mode=users&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-secondary mt-2">Отмена</a>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

// ==================== УНИВЕРСАЛЬНЫЙ CRUD ДЛЯ ВСЕХ ТАБЛИЦ (режим crud) ====================
if ($mode === 'crud') {
    $colsInfo = [];
    $primaryKey = null;
    $foreignKeys = [];
    $rows = [];
    $total = 0;
    $totalPages = 0;
    $editRow = null;

    if ($selectedTable && in_array($selectedTable, $tables)) {
        [$colsInfo, $primaryKey] = getColumnsInfo($pdo, $selectedTable);
        $foreignKeys = getForeignKeys($pdo, $selectedTable);

        // Поиск и пагинация
        $countSql = "SELECT COUNT(*) FROM `$selectedTable`";
        $countParams = [];
        if ($search) {
            $searchFields = [];
            foreach ($colsInfo as $col) {
                if (in_array($col['type'], ['varchar', 'text', 'char', 'enum', 'date', 'datetime'])) {
                    $searchFields[] = "`{$col['name']}` LIKE ?";
                    $countParams[] = "%$search%";
                }
            }
            if ($searchFields) {
                $countSql .= " WHERE " . implode(' OR ', $searchFields);
            }
        }
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($countParams);
        $total = $stmt->fetchColumn();
        $totalPages = ceil($total / $limit);

        $sql = "SELECT * FROM `$selectedTable`";
        $params = [];
        if ($search) {
            $searchFields = [];
            foreach ($colsInfo as $col) {
                if (in_array($col['type'], ['varchar', 'text', 'char', 'enum', 'date', 'datetime'])) {
                    $searchFields[] = "`{$col['name']}` LIKE ?";
                    $params[] = "%$search%";
                }
            }
            if ($searchFields) {
                $sql .= " WHERE " . implode(' OR ', $searchFields);
            }
        }
        $sql .= " ORDER BY `$primaryKey` LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $paramIndex = 1;
        foreach ($params as $p) {
            $stmt->bindValue($paramIndex++, $p, PDO::PARAM_STR);
        }
        $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Обработка POST (добавление/редактирование) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                die('Ошибка CSRF токена');
            }

            if ($_POST['action'] === 'add') {
                $setParts = [];
                $values = [];
                foreach ($colsInfo as $col) {
                    $name = $col['name'];
                    if ($name === $primaryKey) continue;
                    if (isset($_POST[$name]) && $_POST[$name] !== '') {
                        // Для таблицы users хешируем пароль
                        if ($selectedTable === 'users' && $name === 'password_hash') {
                            $values[] = password_hash($_POST[$name], PASSWORD_DEFAULT);
                        } else {
                            $values[] = $_POST[$name];
                        }
                        $setParts[] = "`$name` = ?";
                    }
                }
                if ($setParts) {
                    $sql = "INSERT INTO `$selectedTable` SET " . implode(',', $setParts);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    logAction($pdo, $_SESSION['user']['id'], "Добавлена запись в таблицу $selectedTable");
                    $_SESSION['flash'] = "✅ Запись добавлена.";
                }
                header('Location: ?mode=crud&table=' . urlencode($selectedTable) . '&page=' . $page . '&search=' . urlencode($search));
                exit;
            } elseif ($_POST['action'] === 'edit' && $id) {
                $setParts = [];
                $values = [];
                foreach ($colsInfo as $col) {
                    $name = $col['name'];
                    if ($name === $primaryKey) continue;
                    if (isset($_POST[$name]) && $_POST[$name] !== '') {
                        // Для таблицы users хешируем пароль
                        if ($selectedTable === 'users' && $name === 'password_hash') {
                            $values[] = password_hash($_POST[$name], PASSWORD_DEFAULT);
                        } else {
                            $values[] = $_POST[$name];
                        }
                        $setParts[] = "`$name` = ?";
                    }
                }
                if ($setParts) {
                    $values[] = $id;
                    $sql = "UPDATE `$selectedTable` SET " . implode(',', $setParts) . " WHERE `$primaryKey` = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    logAction($pdo, $_SESSION['user']['id'], "Изменена запись ID $id в таблице $selectedTable");
                    $_SESSION['flash'] = "✅ Запись обновлена.";
                }
                header('Location: ?mode=crud&table=' . urlencode($selectedTable) . '&page=' . $page . '&search=' . urlencode($search));
                exit;
            }
        }

        // --- Удаление (GET) ---
        if ($action === 'delete' && $id) {
            if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                die('Ошибка CSRF токена');
            }
            $stmt = $pdo->prepare("DELETE FROM `$selectedTable` WHERE `$primaryKey` = ?");
            $stmt->execute([$id]);
            logAction($pdo, $_SESSION['user']['id'], "Удалена запись ID $id из таблицы $selectedTable");
            $_SESSION['flash'] = "✅ Запись удалена.";
            header('Location: ?mode=crud&table=' . urlencode($selectedTable) . '&page=' . $page . '&search=' . urlencode($search));
            exit;
        }

        // --- Данные для редактирования ---
        if ($action === 'edit' && $id) {
            $editRow = getRowData($pdo, $selectedTable, $id, $primaryKey);
        }
    }

    // Генерация CSRF-токена
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    include 'templates/header.php';
    ?>
    <div class="mb-3">
        <a href="?mode=" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Назад к выбору режима
        </a>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-database me-2"></i> CRUD интерфейс
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Таблица</label>
                    <select name="table" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Выберите таблицу --</option>
                        <?php foreach ($tables as $tbl): ?>
                            <option value="<?= htmlspecialchars($tbl) ?>" <?= $selectedTable === $tbl ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedTable): ?>
                    <div class="col-md-4">
                        <label class="form-label">Поиск</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Поиск..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-outline-primary">🔍</button>
                            <?php if ($search): ?>
                                <a href="?mode=crud&table=<?= urlencode($selectedTable) ?>" class="btn btn-outline-secondary">Сбросить</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="hidden" name="mode" value="crud">
            </form>
        </div>
    </div>

    <?php if ($selectedTable): ?>
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="fas fa-table me-2"></i><?= htmlspecialchars($selectedTable) ?></h2>
            <a href="?mode=crud&table=<?= urlencode($selectedTable) ?>&action=add" class="btn btn-success">
                <i class="fas fa-plus"></i> Добавить запись
            </a>
        </div>

        <?php if ($rows): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($colsInfo as $col): ?>
                                <th><?= htmlspecialchars($col['name']) ?></th>
                            <?php endforeach; ?>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($colsInfo as $col): 
                                    $val = $row[$col['name']];
                                    // Для таблицы users поле password_hash показываем как обычное поле
                                    $isFk = false;
                                    foreach ($foreignKeys as $fk) {
                                        if ($fk['COLUMN_NAME'] === $col['name']) {
                                            $isFk = true;
                                            $refTable = $fk['REFERENCED_TABLE_NAME'];
                                            $refCol = $fk['REFERENCED_COLUMN_NAME'];
                                            echo "<td>" . htmlspecialchars(getDisplayValue($pdo, $refTable, $val, $refCol)) . "</td>";
                                            break;
                                        }
                                    }
                                    if (!$isFk) echo "<td>" . htmlspecialchars($val ?? '') . "</td>";
                                endforeach; ?>
                                <td class="action-icons">
                                    <a href="?mode=crud&table=<?= urlencode($selectedTable) ?>&action=edit&id=<?= $row[$primaryKey] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?mode=crud&table=<?= urlencode($selectedTable) ?>&action=delete&id=<?= $row[$primaryKey] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить запись?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?mode=crud&table=<?= urlencode($selectedTable) ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">Нет данных для отображения.</div>
        <?php endif; ?>

        <!-- Форма добавления/редактирования -->
        <?php if ($action === 'add' || ($action === 'edit' && $editRow)): ?>
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas <?= $action === 'add' ? 'fa-plus-circle' : 'fa-pen' ?> me-2"></i>
                    <?= $action === 'add' ? 'Добавление записи' : 'Редактирование записи' ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="<?= $action === 'add' ? 'add' : 'edit' ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php foreach ($colsInfo as $col): 
                            $name = $col['name'];
                            if ($name === $primaryKey) continue;
                            $value = $editRow ? ($editRow[$name] ?? '') : '';
                            $isFk = false;
                            $fkTable = $fkColumn = '';
                            foreach ($foreignKeys as $fk) {
                                if ($fk['COLUMN_NAME'] === $name) {
                                    $isFk = true;
                                    $fkTable = $fk['REFERENCED_TABLE_NAME'];
                                    $fkColumn = $fk['REFERENCED_COLUMN_NAME'];
                                    break;
                                }
                            }
                        ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= htmlspecialchars($name) ?></label>
                                <?php if ($isFk && $fkTable): ?>
                                    <select name="<?= $name ?>" class="form-select">
                                        <option value="">-- Не выбрано --</option>
                                        <?php
                                        $fkRows = $pdo->query("SELECT * FROM `$fkTable`")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($fkRows as $opt) {
                                            $optId = $opt[$fkColumn];
                                            $selected = ($value == $optId) ? 'selected' : '';
                                            $display = $optId;
                                            foreach (['name', 'title', 'full_name', 'login', 'company_name', 'description'] as $candidate) {
                                                if (isset($opt[$candidate])) { $display = $opt[$candidate]; break; }
                                            }
                                            echo "<option value='$optId' $selected>" . htmlspecialchars($display) . "</option>";
                                        }
                                        ?>
                                    </select>
                                <?php elseif (!empty($col['enum'])): ?>
                                    <select name="<?= $name ?>" class="form-select">
                                        <option value="">-- Выберите --</option>
                                        <?php foreach ($col['enum'] as $enumVal): ?>
                                            <option value="<?= htmlspecialchars($enumVal) ?>" <?= ($value == $enumVal) ? 'selected' : '' ?>><?= htmlspecialchars($enumVal) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="<?= $name ?>" class="form-control" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $action === 'add' ? 'Добавить' : 'Сохранить' ?>
                        </button>
                        <a href="?mode=crud&table=<?= urlencode($selectedTable) ?>" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">Выберите таблицу для работы.</div>
    <?php endif; ?>
    <?php include 'templates/footer.php'; ?>
    <?php
}

// ==================== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ (режим users) ====================
if ($mode === 'users') {
    include 'templates/header.php';
    ?>
    <div class="mb-3">
        <a href="?mode=" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Назад к выбору режима
        </a>
    </div>
    <?php
    if (isset($_SESSION['flash'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['flash']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
    renderUserManagementInterface($pdo, $page, $limit, $offset, $search, $action, $id);
    include 'templates/footer.php';
    ?>
    <?php
}
?>