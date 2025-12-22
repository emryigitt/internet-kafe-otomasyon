<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . " - " : "" ?>İnternet Kafe Yönetim Sistemi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <!-- Bootstrap Icons (monitor ikonu için) -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>
        body {
            background: #111827;
            color: #e5e7eb;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .card { border-radius: 1rem; }
        .navbar-brand { font-weight: 700; }

        /* Bilgisayar kartları */
        .pc-card {
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
            border: 1px solid rgba(255,255,255,.06);
        }
        .pc-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0,0,0,.35);
        }
        .pc-icon {
            width: 52px; height: 52px;
            display: grid; place-items: center;
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.08);
        }
        .pc-title {
            font-weight: 700;
            letter-spacing: .2px;
        }
        .pc-sub {
            font-size: .9rem;
            color: rgba(229,231,235,.75);
        }
        .pc-metric {
            font-size: .9rem;
            color: rgba(229,231,235,.8);
        }
        .pc-metric b { color: #fff; }
        .pc-actions .btn { border-radius: .8rem; }
        .badge-soft {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.10);
            color: #e5e7eb;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">İnternet Kafe</a>
        <div class="d-flex">
            <?php if (!empty($_SESSION['kullanici_adi'])): ?>
                <span class="navbar-text me-3">
                    Hoş geldin, <?= htmlspecialchars($_SESSION['kullanici_adi']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Çıkış</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container my-4">

