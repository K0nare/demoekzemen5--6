<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $user['id']]);
        $_SESSION['user']['full_name'] = $full_name;
        $message = '✅ Имя обновлено.';
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = '❌ Неверный текущий пароль.';
        } elseif (strlen($new) < 6) {
            $error = '❌ Новый пароль не менее 6 символов.';
        } elseif ($new !== $confirm) {
            $error = '❌ Пароли не совпадают.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            $_SESSION['user']['password_hash'] = $hash;
            $message = '✅ Пароль изменён.';
        }
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Профиль пользователя</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Изменить имя</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                    <label>Полное имя</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Сменить пароль</div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label>Текущий пароль</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Новый пароль</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Подтверждение нового пароля</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Сменить пароль</button>
            </form>
        </div>
    </div>

    <a href="../index.php" class="btn btn-secondary mt-3">← Назад</a>
</div>
<?php include '../templates/footer.php'; ?>