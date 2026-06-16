<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// --- Обработка AJAX-проверки капчи ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_order'])) {
    $order = json_decode($_POST['captcha_order'], true);
    $correct = [1, 2, 3, 4];
    if ($order == $correct) {
        $_SESSION['captcha_passed'] = true;
        echo json_encode(['success' => true]);
    } else {
        $_SESSION['captcha_attempts'] = ($_SESSION['captcha_attempts'] ?? 0) + 1;
        if ($_SESSION['captcha_attempts'] >= 3) {
            echo json_encode(['success' => false, 'blocked' => true]);
        } else {
            echo json_encode(['success' => false, 'blocked' => false]);
        }
    }
    exit;
}

// --- Обработка блокировки пользователя при ошибках капчи ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_login'])) {
    $login = trim($_POST['block_login']);
    if ($login) {
        $user = getUserByLogin($pdo, $login);
        if ($user && $user['blocked'] == 0) {
            setUserBlocked($pdo, $user['id'], 1);
            logAction($pdo, $user['id'], "Заблокирован из-за 3 ошибок капчи");
        }
    }
    exit;
}

// --- Обычная отправка формы (логин + пароль) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['captcha_order']) && !isset($_POST['block_login'])) {
    if (!isset($_SESSION['captcha_passed']) || $_SESSION['captcha_passed'] !== true) {
        $error = '❌ Сначала соберите пазл из 4 фрагментов!';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = checkLogin($pdo, $login, $password);
        if ($user) {
            if ($user['blocked'] == 1) {
                $error = '🚫 Вы заблокированы. Обратитесь к администратору.';
            } else {
                $_SESSION['user'] = $user;
                // Успешный вход — сбрасываем попытки капчи
                unset($_SESSION['captcha_attempts']);
                unset($_SESSION['captcha_passed']);
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Вы ввели неверный логин или пароль. Пожалуйста проверьте ещё раз введенные данные.';
        }
    }
}

include 'templates/header.php';
?>
<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4">Вход в систему</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="login">Логин</label>
                    <input type="text" name="login" id="login" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password">Пароль</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <button type="submit" id="loginBtn" class="btn btn-primary w-100" disabled>Войти</button>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">Соберите пазл</div>
                    <div class="card-body">
                        <p class="small text-muted">Перетащите фрагменты в пустые ячейки.<br>
                        Правильный порядок: 1 (левый верх) → 2 (правый верх) → 3 (левый низ) → 4 (правый низ).</p>
                        <div id="puzzleGrid" class="d-flex flex-wrap" style="width: 166px; height: 166px;"></div>
                        <div id="puzzlePool" class="d-flex flex-wrap gap-2 mt-3"></div>
                        <input type="hidden" id="captcha_ok" value="0">
                    </div>
                </div>
            </div>
        </div>
    </form>

    <p class="mt-3 text-center"><a href="register.php">Нет аккаунта? Зарегистрируйтесь</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const correctOrder = [1, 2, 3, 4];
    let userOrder = [];
    let attempts = 0;
    const grid = document.getElementById('puzzleGrid');
    const pool = document.getElementById('puzzlePool');
    const hidden = document.getElementById('captcha_ok');
    const loginBtn = document.getElementById('loginBtn');

    const imgPath = 'captcha/';

    const pieces = [
        { id: 1, src: imgPath + '1.png', label: '1' },
        { id: 2, src: imgPath + '2.png', label: '2' },
        { id: 3, src: imgPath + '3.png', label: '3' },
        { id: 4, src: imgPath + '4.png', label: '4' }
    ];

    // Создаём сетку 2x2 с пустыми ячейками
    for (let i = 0; i < 4; i++) {
        const slot = document.createElement('div');
        slot.className = 'puzzle-slot';
        slot.style.width = '80px';
        slot.style.height = '80px';
        slot.style.border = '2px dashed #ccc';
        slot.style.background = '#f0f0f0';
        slot.style.borderRadius = '4px';
        slot.dataset.expected = i + 1;
        slot.dataset.filled = 'false';
        slot.addEventListener('dragover', onDragOver);
        slot.addEventListener('drop', onDrop);
        grid.appendChild(slot);
    }

    // Перемешиваем и выводим фрагменты в пул
    const shuffled = [...pieces].sort(() => Math.random() - 0.5);
    shuffled.forEach(piece => {
        const el = document.createElement('div');
        el.className = 'puzzle-piece';
        el.draggable = true;
        el.dataset.id = piece.id;
        el.style.width = '80px';
        el.style.height = '80px';
        el.style.backgroundImage = `url(${piece.src})`;
        el.style.backgroundSize = 'cover';
        el.style.border = '2px solid #999';
        el.style.borderRadius = '4px';
        el.style.cursor = 'grab';
        el.addEventListener('dragstart', onDragStart);
        el.addEventListener('dragend', onDragEnd);
        pool.appendChild(el);
    });

    let draggedElement = null;

    function onDragStart(e) {
        draggedElement = this;
        this.style.opacity = '0.5';
        e.dataTransfer.setData('text/plain', this.dataset.id);
    }

    function onDragEnd(e) {
        this.style.opacity = '1';
    }

    function onDragOver(e) {
        e.preventDefault();
    }

    function onDrop(e) {
        e.preventDefault();
        const slot = this;
        if (slot.dataset.filled === 'true') return;

        const pieceId = parseInt(e.dataTransfer.getData('text/plain'));
        const expected = parseInt(slot.dataset.expected);
        if (pieceId === expected) {
            // Правильно: вставляем фрагмент в ячейку
            slot.style.border = '2px solid green';
            slot.style.background = 'transparent';
            const img = document.createElement('div');
            img.style.width = '80px';
            img.style.height = '80px';
            img.style.backgroundImage = `url(${imgPath + pieceId + '.png'})`;
            img.style.backgroundSize = 'cover';
            slot.appendChild(img);
            slot.dataset.filled = 'true';
            userOrder.push(pieceId);
            if (draggedElement) draggedElement.remove();
            if (userOrder.length === 4) {
                checkPuzzle();
            }
        } else {
            // Неправильно: возвращаем фрагмент в пул
            attempts++;
            if (attempts >= 3) {
                alert('❌ Вы заблокированы! Обратитесь к администратору.');
                document.querySelector('.container').innerHTML = '<h2 class="text-danger">Вы заблокированы.</h2>';
                blockUser();
                return;
            } else {
                alert('❌ Неверный фрагмент! Попробуйте снова.');
                resetPuzzle();
            }
        }
    }

    function resetPuzzle() {
        userOrder = [];
        document.querySelectorAll('.puzzle-slot').forEach(s => {
            s.style.border = '2px dashed #ccc';
            s.style.background = '#f0f0f0';
            s.innerHTML = '';
            s.dataset.filled = 'false';
        });
        pool.innerHTML = '';
        const shuffled = [...pieces].sort(() => Math.random() - 0.5);
        shuffled.forEach(piece => {
            const el = document.createElement('div');
            el.className = 'puzzle-piece';
            el.draggable = true;
            el.dataset.id = piece.id;
            el.style.width = '80px';
            el.style.height = '80px';
            el.style.backgroundImage = `url(${piece.src})`;
            el.style.backgroundSize = 'cover';
            el.style.border = '2px solid #999';
            el.style.borderRadius = '4px';
            el.style.cursor = 'grab';
            el.addEventListener('dragstart', onDragStart);
            el.addEventListener('dragend', onDragEnd);
            pool.appendChild(el);
        });
    }

    function checkPuzzle() {
        let ok = userOrder.every((val, idx) => val === correctOrder[idx]);
        if (ok) {
            hidden.value = '1';
            loginBtn.disabled = false;
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'captcha_order=' + encodeURIComponent(JSON.stringify(userOrder))
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('✅ Пазл собран верно!');
                }
            });
        } else {
            resetPuzzle();
        }
    }

    function blockUser() {
        const login = document.getElementById('login').value;
        if (login) {
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'block_login=' + encodeURIComponent(login)
            });
        }
    }
});
</script>

<style>
.puzzle-slot, .puzzle-piece { box-sizing: border-box; }
</style>
<?php include 'templates/footer.php'; ?>