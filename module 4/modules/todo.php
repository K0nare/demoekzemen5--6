<?php
require_once '../config.php';
require_once '../functions.php';

$file = '../tasks.json';
$tasks = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $task = trim($_POST['task']);
    if (!empty($task)) {
        $tasks[] = ['id' => time(), 'text' => $task, 'done' => false];
        file_put_contents($file, json_encode($tasks));
    }
    header('Location: ?page=todo');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $tasks = array_filter($tasks, fn($t) => $t['id'] !== $id);
    file_put_contents($file, json_encode(array_values($tasks)));
    header('Location: ?page=todo');
    exit;
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    foreach ($tasks as &$t) {
        if ($t['id'] === $id) { $t['done'] = !$t['done']; break; }
    }
    file_put_contents($file, json_encode($tasks));
    header('Location: ?page=todo');
    exit;
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Список задач</h2>
    <form method="post" class="mb-3">
        <div class="input-group">
            <input type="text" name="task" class="form-control" placeholder="Новая задача" required>
            <button type="submit" name="add" class="btn btn-primary">Добавить</button>
        </div>
    </form>
    <ul class="list-group">
        <?php foreach ($tasks as $t): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center" style="<?= $t['done'] ? 'text-decoration:line-through; opacity:0.7' : '' ?>">
                <?= htmlspecialchars($t['text']) ?>
                <div>
                    <a href="?page=todo&toggle=<?= $t['id'] ?>" class="btn btn-sm btn-success">✓</a>
                    <a href="?page=todo&delete=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить?')">✗</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="../index.php" class="btn btn-secondary mt-3">← Назад</a>
</div>
<?php include '../templates/footer.php'; ?>