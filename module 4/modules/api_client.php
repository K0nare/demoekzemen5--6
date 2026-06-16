<?php
require_once '../config.php';
require_once '../functions.php';

define('SIMULATOR_URL', 'http://localhost:4444/TransferSimulator/');

// Список типов данных для валидации
$testTypes = [
    'fullName'     => ['label' => 'ФИО', 'param' => 'fullName', 'mock' => 'Тестовый+Запрос+Иванович', 'regex' => '/^[А-Яа-яЁё\s-]+$/u', 'error' => 'ФИО содержит запрещённые символы (цифры, знаки = + ( ) и т.д.)'],
    'snils'        => ['label' => 'СНИЛС', 'param' => 'snils', 'mock' => '112-233-445+95', 'regex' => '/^\d{3}-\d{3}-\d{3}\s\d{2}$/', 'error' => 'Неверный формат СНИЛС (ожидается XXX-XXX-XXX XX)'],
    'inn'          => ['label' => 'ИНН', 'param' => 'inn', 'mock' => '770101001', 'regex' => '/^(\d{10}|\d{12})$/', 'error' => 'ИНН должен содержать 10 или 12 цифр'],
    'mobilePhone'  => ['label' => 'Мобильный телефон', 'param' => 'mobilePhone', 'mock' => '%2B79991112233', 'regex' => '/^(\+7|8)\d{10}$/', 'error' => 'Неверный формат телефона (ожидается +7XXXXXXXXXX или 8XXXXXXXXXX)'],
    'identityCard' => ['label' => 'Паспорт', 'param' => 'identityCard', 'mock' => '4508+123456', 'regex' => '/^\d{4}\s\d{6}$/', 'error' => 'Формат паспорта: 4 цифры, пробел, 6 цифр'],
    'email'        => ['label' => 'Email', 'param' => 'email', 'mock' => 'test@example.com', 'regex' => 'email', 'error' => 'Неверный формат email']
];

$currentType = $_POST['type'] ?? $_GET['type'] ?? 'fullName';
$generatedValue = '';
$resultMessage = '';
$validationResult = null;

// Получение данных от эмулятора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_data'])) {
    $type = $_POST['type'];
    if (isset($testTypes[$type])) {
        $config = $testTypes[$type];
        $url = SIMULATOR_URL . $type . '?' . $config['param'] . '=' . $config['mock'];
        $response = @file_get_contents($url);
        if ($response === false) {
            $resultMessage = '❌ Ошибка: эмулятор недоступен. Проверьте запуск TransferSimulator.';
        } else {
            $data = json_decode($response, true);
            $generatedValue = $data['value'] ?? '';
            $resultMessage = 'Данные получены: ' . htmlspecialchars($generatedValue);
        }
    } else {
        $resultMessage = '❌ Неизвестный тип данных.';
    }
}

// Отправка результата теста (валидация + запись в БД и CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $type = $_POST['type'];
    $value = trim($_POST['value'] ?? '');
    $config = $testTypes[$type] ?? null;
    
    if (!$config) {
        $resultMessage = '❌ Неизвестный тип данных.';
    } else {
        // Валидация
        if ($config['regex'] === 'email') {
            $isValid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        } else {
            $isValid = preg_match($config['regex'], $value);
        }
        $status = $isValid ? 'Успешно' : 'Не успешно';
        $errorMsg = $isValid ? null : $config['error'];
        
        $resultMessage = $isValid ? '✅ Валидация пройдена' : '❌ ' . $config['error'];
        $resultMessage .= '<br>Значение: ' . htmlspecialchars($value);
        
        // --- ЗАПИСЬ В БАЗУ ДАННЫХ ---
        try {
            $stmt = $pdo->prepare("INSERT INTO validation_logs (type, value, status, error_message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$type, $value, $status, $errorMsg]);
            $resultMessage .= '<br>✅ Результат записан в базу данных (validation_logs)';
        } catch (PDOException $e) {
            $resultMessage .= '<br>⚠️ Ошибка записи в БД: ' . $e->getMessage();
        }
        
        // --- ЗАПИСЬ В CSV (для совместимости со старым заданием) ---
        $file = '../testcase.csv';
        $fp = fopen($file, 'a');
        fputcsv($fp, [date('Y-m-d H:i:s'), $value, $status]);
        fclose($fp);
        $resultMessage .= '<br>✅ Результат записан в testcase.csv';
        
        $generatedValue = $value;
        $validationResult = $isValid;
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2>Универсальная валидация данных</h2>
    <div class="card">
        <div class="card-body">
            <form method="post" id="validationForm">
                <div class="mb-3">
                    <label class="form-label">Тип данных</label>
                    <select name="type" class="form-select" id="typeSelect">
                        <?php foreach ($testTypes as $key => $info): ?>
                            <option value="<?= $key ?>" <?= ($key == $currentType) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($info['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <button type="submit" name="get_data" class="btn btn-info w-100">Получить данные от эмулятора</button>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" name="submit_test" class="btn btn-primary w-100">Отправить результат теста</button>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label for="value">Значение для проверки</label>
                    <input type="text" class="form-control" id="value" name="value" 
                           value="<?= htmlspecialchars($generatedValue) ?>">
                    <small class="text-muted">Вы можете отредактировать значение вручную.</small>
                </div>
                
                <?php if ($resultMessage): ?>
                    <div class="mt-3 alert <?= strpos($resultMessage, '✅') !== false ? 'alert-success' : (strpos($resultMessage, '❌') !== false ? 'alert-danger' : 'alert-info') ?>">
                        <?= $resultMessage ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <a href="../index.php" class="btn btn-secondary mt-3">← Назад</a>
</div>

<script>
// Опционально: автообновление значения при смене типа (можно и без этого)
document.getElementById('typeSelect').addEventListener('change', function() {
    // При смене типа очищаем поле значения (чтобы не было путаницы)
    document.getElementById('value').value = '';
});
</script>

<?php include '../templates/footer.php'; ?>