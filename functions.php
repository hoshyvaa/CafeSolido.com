<?php

function canDo($role, $capability)
{
    $permissions = [
        'add_product' => ['Admin'],
        'edit_product' => ['Admin'],
        'delete_product' => ['Admin'],
        'manage_categories' => ['Admin'],
        'view_products' => ['Admin', 'Manager', 'Cashier'],
        'manage_inventory' => ['Admin', 'Manager'],
        'view_sales' => ['Admin', 'Manager', 'Cashier'],
        'process_orders' => ['Cashier'],
        'place_order' => ['Customer'],
        'manage_users' => ['Admin'],
    ];

    return in_array($role, $permissions[$capability] ?? [], true);
}

function roleDashboard($role)
{
    if ($role === 'Admin') {
        return 'admin';
    }

    if ($role === 'Manager') {
        return 'manager';
    }

    if ($role === 'Cashier') {
        return 'cashier';
    }

    return 'home';
}

function currentRole()
{
    $role = trim($_SESSION['role'] ?? 'Customer');
    $role = ucfirst(strtolower($role));

    if (!in_array($role, ['Customer', 'Cashier', 'Manager', 'Admin'], true)) {
        $role = 'Customer';
    }

    return $role;
}
