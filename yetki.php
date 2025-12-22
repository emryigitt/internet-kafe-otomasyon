<?php
// yetki.php

// Aktif kullanıcının rol adını döndürür (örn: "Yönetici", "Kasiyer" ...)
function aktifRolAdi(): ?string
{
    return $_SESSION['rol_adi'] ?? null;
}

// Aktif kullanıcının rol_id'sini döndürür
function aktifRolId(): ?int
{
    return isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : null;
}

/**
 * Rol bazlı izinler (permission)
 *
 * Kurallar:
 * - Yönetici: her şey
 * - Personel: pc kartlarını gör, durum gör, oturum aç/kapat, ürün satışı
 * - Kasiyer : pc kartlarını gör (işlem için), oturum aç/kapat, ürün satışı
 * - Teknisyen: pc kartlarını gör, durum gör, bakım kaydı ekle/düzenle/tamamla
 */
function rolePermissions(): array
{
    return [
        'Yönetici' => ['*'], // her şey

        'Personel' => [
            'pc.view',
            'pc.status.view',
            'session.manage',
            'sale.create',
        ],

        'Kasiyer' => [
            'pc.view',
            'pc.status.view',
            'session.manage',
            'sale.create',
        ],

        'Teknisyen' => [
            'pc.view',
            'pc.status.view',
            'maintenance.manage',
        ],
    ];
}

/**
 * Kullanıcı şu izne sahip mi?
 * Örn: can('session.manage')
 */
function can(string $permission): bool
{
    if (empty($_SESSION['kullanici_id'])) return false;

    $rol = aktifRolAdi();
    if (!$rol) return false;

    $map = rolePermissions();
    $perms = $map[$rol] ?? [];

    // Yönetici wildcard
    if (in_array('*', $perms, true)) return true;

    return in_array($permission, $perms, true);
}

/**
 * Bu sayfaya sadece belli rol adları girebilsin (eski sistem)
 */
function requireRol(array $izinliRoller)
{
    if (empty($_SESSION['kullanici_id'])) {
        header("Location: login.php");
        exit;
    }

    $rolAdi = aktifRolAdi();

    if ($rolAdi === null || !in_array($rolAdi, $izinliRoller, true)) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Bu işlemi/sayfayı yapabilmek için izin şartı
 * Örn: requirePermission('maintenance.manage');
 */
function requirePermission(string $permission)
{
    if (empty($_SESSION['kullanici_id'])) {
        header("Location: login.php");
        exit;
    }

    if (!can($permission)) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * En az bir izne sahipse geçsin
 * Örn: requireAnyPermission(['sale.create','session.manage']);
 */
function requireAnyPermission(array $permissions)
{
    if (empty($_SESSION['kullanici_id'])) {
        header("Location: login.php");
        exit;
    }

    foreach ($permissions as $p) {
        if (can($p)) return;
    }

    header("Location: dashboard.php");
    exit;
}

