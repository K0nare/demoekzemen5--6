<?php
require_once '../config.php';
require_once '../functions.php';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// --- Получаем дату заказа и имя покупателя ---
$stmtOrder = $pdo->prepare("SELECT o.order_date, c.name AS customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
$stmtOrder->execute([$orderId]);
$orderInfo = $stmtOrder->fetch(PDO::FETCH_ASSOC);
$orderDate = $orderInfo ? $orderInfo['order_date'] : '—';
$customerName = $orderInfo ? $orderInfo['customer_name'] : '—';

// --- Детализация заказа с единицами измерения ---
$sqlDetails = "
    SELECT 
        oi.id,
        p.name AS product_name,
        oi.quantity,
        oi.price AS product_price,
        m.name AS material_name,
        m.unit AS material_unit,
        pn.quantity_per_unit,
        m.price AS material_price,
        (pn.quantity_per_unit * m.price) AS material_cost_per_unit,
        (oi.quantity * pn.quantity_per_unit * m.price) AS material_cost_total
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN production_norms pn ON oi.product_id = pn.product_id
    LEFT JOIN materials m ON pn.material_id = m.id
    WHERE oi.order_id = ?
    ORDER BY p.name, m.name
";
$stmtDetails = $pdo->prepare($sqlDetails);
$stmtDetails->execute([$orderId]);
$items = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

// Группировка по продукту с одновременным подсчётом итогов
$groupedItems = [];
$totalRevenue = 0;
$totalMaterialCost = 0;

foreach ($items as $item) {
    $prodKey = $item['product_name'];
    if (!isset($groupedItems[$prodKey])) {
        $groupedItems[$prodKey] = [
            'quantity'          => $item['quantity'],
            'product_price'     => $item['product_price'],
            'product_total_cost'=> $item['quantity'] * $item['product_price'],
            'materials'         => [],
            'material_sum'      => 0
        ];
        $totalRevenue += $item['quantity'] * $item['product_price'];
    }
    if ($item['material_name']) {
        $materialTotal = $item['material_cost_total'];
        $groupedItems[$prodKey]['materials'][] = [
            'material_name'        => $item['material_name'],
            'material_unit'        => $item['material_unit'],
            'quantity_per_unit'    => $item['quantity_per_unit'],
            'material_price'       => $item['material_price'],
            'material_cost_per_unit' => $item['material_cost_per_unit'],
            'material_cost_total'  => $materialTotal
        ];
        $groupedItems[$prodKey]['material_sum'] += $materialTotal;
        $totalMaterialCost += $materialTotal;
    }
}

$profit = $totalRevenue - $totalMaterialCost;

include '../templates/header.php';
?>
<div class="container mt-4">
    <!-- Заголовок с датой справа -->
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h2>Расчёт по заказу №<?= htmlspecialchars($orderId) ?></h2>
        <div>
            <span class="badge bg-secondary fs-6">📅 Дата: <?= htmlspecialchars($orderDate) ?></span>
            <span class="badge bg-info fs-6 ms-2">🏢 Покупатель: <?= htmlspecialchars($customerName) ?></span>
        </div>
    </div>

    <div class="alert alert-info small">
        💡 Выручка рассчитана по ценам, зафиксированным в заказе.<br>
        💰 <strong>Маржинальная прибыль</strong> = Выручка − Себестоимость материалов.
    </div>

    <div class="mb-3">
        <form method="get" class="row g-2">
            <div class="col-auto">
                <label for="orderId" class="visually-hidden">ID заказа</label>
                <input type="number" id="orderId" name="id" class="form-control" value="<?= $orderId ?>" min="1">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Пересчитать</button>
            </div>
        </form>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            Финансовый результат по заказу
        </div>
        <div class="card-body">
            <p><strong>📅 Дата заказа:</strong> <?= htmlspecialchars($orderDate) ?></p>
            <p><strong>🏢 Покупатель:</strong> <?= htmlspecialchars($customerName) ?></p>
            <p><strong>💰 Выручка (стоимость готовой продукции):</strong> <?= number_format($totalRevenue, 2) ?> руб.</p>
            <p><strong>⚙️ Себестоимость материалов:</strong> <?= number_format($totalMaterialCost, 2) ?> руб.</p>
            <p><strong>📈 Маржинальная прибыль:</strong> <?= number_format($profit, 2) ?> руб.</p>
        </div>
    </div>

    <?php if (!empty($groupedItems)): ?>
        <h3>Детализация по продуктам и материалам</h3>
        <?php foreach ($groupedItems as $productName => $data): ?>
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <strong><?= htmlspecialchars($productName) ?></strong> &nbsp; 
                    (кол-во: <?= $data['quantity'] ?> шт., цена в заказе: <?= number_format($data['product_price'], 2) ?> руб.)
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Материал</th>
                                <th>Ед. изм.</th>
                                <th>Норма на 1 шт.</th>
                                <th>Цена за ед.</th>
                                <th>Стоимость материала на 1 шт.</th>
                                <th>Итого стоимость материала</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['materials'])): ?>
                                <tr>
                                    <td colspan="6" class="text-muted">Для этого продукта не заданы нормы расхода материалов</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['materials'] as $mat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mat['material_name']) ?></td>
                                        <td><?= htmlspecialchars($mat['material_unit']) ?></td>
                                        <td><?= $mat['quantity_per_unit'] ?></td>
                                        <td><?= number_format($mat['material_price'], 2) ?> руб.</td>
                                        <td><?= number_format($mat['material_cost_per_unit'], 2) ?> руб.</td>
                                        <td><?= number_format($mat['material_cost_total'], 2) ?> руб.</td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary">
                                    <td colspan="5" class="text-end"><strong>Итого материалов по продукту:</strong></td>
                                    <td><strong><?= number_format($data['material_sum'], 2) ?> руб.</strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Сводная таблица по продуктам -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                Сводка по продуктам (выручка, материалы, прибыль)
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Продукт</th>
                            <th>Кол-во</th>
                            <th>Выручка, руб.</th>
                            <th>Себест. материалов, руб.</th>
                            <th>Прибыль, руб.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sumRev = 0;
                        $sumMat = 0;
                        foreach ($groupedItems as $productName => $data):
                            $rev = $data['product_total_cost'];
                            $mat = $data['material_sum'];
                            $pr = $rev - $mat;
                            $sumRev += $rev;
                            $sumMat += $mat;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($productName) ?></td>
                                <td><?= $data['quantity'] ?></td>
                                <td class="text-end"><?= number_format($rev, 2) ?></td>
                                <td class="text-end"><?= number_format($mat, 2) ?></td>
                                <td class="text-end <?= $pr < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= number_format($pr, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">ИТОГО по заказу:</th>
                            <th class="text-end"><?= number_format($sumRev, 2) ?></th>
                            <th class="text-end"><?= number_format($sumMat, 2) ?></th>
                            <th class="text-end <?= ($sumRev - $sumMat) < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($sumRev - $sumMat, 2) ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="alert alert-success mt-4 text-center">
            <strong>📌 Маржинальная прибыль по заказу:</strong> 
            <?= number_format($profit, 2) ?> руб.
        </div>

    <?php else: ?>
        <div class="alert alert-warning">В заказе №<?= $orderId ?> нет позиций.</div>
    <?php endif; ?>

    <a href="../index.php" class="btn btn-secondary mt-3">← На главную</a>
</div>
<?php include '../templates/footer.php'; ?>