<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $pass = $_POST['password'];
    $confirm = $_POST['confirm'];
    $full_name = trim($_POST['full_name']);

    if (empty($login)) $errors[] = "Логин обязателен";
    if (strlen($pass) < 6) $errors[] = "Пароль не менее 6 символов";
    if ($pass !== $confirm) $errors[] = "Пароли не совпадают";
    if (getUserByLogin($pdo, $login)) $errors[] = "Логин уже занят";

    if (empty($errors)) {
        createUser($pdo, $login, $pass, $full_name);
        header('Location: login.php');
        exit;
    }
}
include 'templates/header.php';
?>
<div class="container mt-5" style="max-width:400px;">
    <h2>Регистрация</h2>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <div class="mb-3"><label>Логин</label><input type="text" name="login" class="form-control" required></div>
        <div class="mb-3"><label>Имя</label><input type="text" name="full_name" class="form-control"></div>
        <div class="mb-3"><label>Пароль</label><input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label>Подтверждение</label><input type="password" name="confirm" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-100">Зарегистрироваться</button>
    </form>
    <p class="mt-3 text-center"><a href="login.php">Уже есть аккаунт?</a></p>
</div>
<?php include 'templates/footer.php'; ?>