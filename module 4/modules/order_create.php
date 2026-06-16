<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../config.php';
require_once '../functions.php';

// Получаем покупателей (buyer = 1)
$customers = $pdo->query("SELECT id, name FROM customers WHERE buyer = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// Получаем продукты
$products = $pdo->query("SELECT id, name, price, unit FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = false;
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = trim($_POST['customer_id'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($customer_id)) {
        $error = 'Выберите покупателя.';
    } elseif (empty($items)) {
        $error = 'Добавьте хотя бы одну позицию.';
    } else {
        $valid_items = [];
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (float)($item['quantity'] ?? 0);
            if ($product_id > 0 && $quantity > 0) {
                $product = array_filter($products, fn($p) => $p['id'] == $product_id);
                $product = reset($product);
                if ($product) {
                    $valid_items[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'price' => $product['price']
                    ];
                }
            }
        }
        if (empty($valid_items)) {
            $error = 'Нет корректных позиций.';
        } else {
            $order_date = date('Y-m-d');
            $pdo->beginTransaction();
            try {
                // Создаём заказ
                $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_date, total_amount) VALUES (?, ?, 0)");
                $stmt->execute([$customer_id, $order_date]);
                $order_id = $pdo->lastInsertId();

                // Вставляем позиции
                $insert_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $product_total = 0;
                foreach ($valid_items as $item) {
                    $insert_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                    $product_total += $item['quantity'] * $item['price'];
                }

                // Стоимость материалов
                $sql_mat = "SELECT COALESCE(SUM(oi.quantity * pn.quantity_per_unit * m.price), 0)
                            FROM order_items oi
                            LEFT JOIN production_norms pn ON oi.product_id = pn.product_id
                            LEFT JOIN materials m ON pn.material_id = m.id
                            WHERE oi.order_id = ?";
                $stmt_mat = $pdo->prepare($sql_mat);
                $stmt_mat->execute([$order_id]);
                $material_cost = (float)$stmt_mat->fetchColumn();

                $total_amount = $product_total + $material_cost;

                // Обновляем итог
                $update = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
                $update->execute([$total_amount, $order_id]);

                $pdo->commit();
                $success = true;
                logAction($pdo, $_SESSION['user']['id'], "Создан заказ №$order_id");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка: ' . $e->getMessage();
            }
        }
    }
}

include '../templates/header.php';
?>
<div class="container mt-4">
    <h2><i class="fas fa-cart-plus me-2"></i>Создание нового заказа</h2>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ Заказ №<?= $order_id ?> успешно создан!
            <a href="calc_order.php?id=<?= $order_id ?>" class="alert-link">Посмотреть детали</a>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="orderForm">
        <div class="mb-3">
            <label for="customer_id" class="form-label">Покупатель</label>
            <select name="customer_id" id="customer_id" class="form-select" required>
                <option value="">-- Выберите --</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['id'] ?>" <?= (($_POST['customer_id'] ?? '') == $cust['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cust['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <h4>Позиции заказа</h4>
        <div id="itemsContainer">
            <div class="row mb-2 item-row">
                <div class="col-md-5">
                    <select name="items[0][product_id]" class="form-select product-select" required>
                        <option value="">-- Выберите продукт --</option>
                        <?php foreach ($products as $prod): ?>
                            <option value="<?= $prod['id'] ?>" data-price="<?= $prod['price'] ?>">
                                <?= htmlspecialchars($prod['name']) ?> (<?= $prod['price'] ?> руб./<?= $prod['unit'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="items[0][quantity]" class="form-control quantity" step="0.01" min="0.01" placeholder="Количество" required>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control item-price" readonly placeholder="Цена">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-item">Удалить</button>
                </div>
            </div>
        </div>
        <button type="button" id="addItem" class="btn btn-secondary mb-3">+ Добавить позицию</button>
        <br>
        <button type="submit" class="btn btn-primary">Создать заказ</button>
        <a href="../index.php" class="btn btn-outline-secondary">Отмена</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = 1;

    function updatePrice(select, priceInput) {
        let price = select.options[select.selectedIndex]?.getAttribute('data-price') || 0;
        priceInput.value = parseFloat(price).toFixed(2) + ' руб.';
    }

    function bindEvents(row) {
        const select = row.querySelector('.product-select');
        const priceInput = row.querySelector('.item-price');
        if (select && priceInput) {
            select.addEventListener('change', () => updatePrice(select, priceInput));
            if (select.value) updatePrice(select, priceInput);
        }
        const removeBtn = row.querySelector('.remove-item');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                if (document.querySelectorAll('.item-row').length > 1) {
                    row.remove();
                } else {
                    alert('Должна быть хотя бы одна позиция');
                }
            });
        }
    }

    document.querySelectorAll('.item-row').forEach(row => bindEvents(row));

    document.getElementById('addItem').addEventListener('click', () => {
        const container = document.getElementById('itemsContainer');
        const template = document.querySelector('.item-row').cloneNode(true);
        template.querySelectorAll('select, input').forEach(el => {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else if (el.type === 'text' || el.type === 'number') el.value = '';
            const name = el.getAttribute('name');
            if (name) el.setAttribute('name', name.replace(/\[\d+\]/, `[${itemIndex}]`));
        });
        container.appendChild(template);
        bindEvents(template);
        itemIndex++;
    });
});
</script>
<?php include '../templates/footer.php'; ?>