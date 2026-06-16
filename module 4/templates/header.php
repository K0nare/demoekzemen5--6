<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Демо-экзамен</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: bold; }
        .card { border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .action-icons a { margin: 0 4px; text-decoration: none; }
        .puzzle-slot, .puzzle-piece { box-sizing: border-box; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-home me-2"></i>ДЭ</a>
        <span class="navbar-text text-white-50">
            <?php if (isset($_SESSION['user'])): ?>
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['user']['login']) ?>
            <?php else: ?>
                Гость
            <?php endif; ?>
        </span>
    </div>
</nav>
<div class="container">