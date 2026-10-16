<?php

declare(strict_types=1);

return [

    /*
     * Menu samping aplikasi. Ditambah halaman = tambah satu baris di sini,
     * tidak lagi menyunting layouts/app.blade.php.
     */

    'menu' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bx-grid-alt'],
        ['label' => 'CRM', 'route' => 'pages.crm', 'icon' => 'bx-group'],
        ['label' => 'Project', 'route' => 'pages.projects', 'icon' => 'bx-briefcase'],
        ['label' => 'Request Order', 'route' => 'pages.request', 'icon' => 'bx-list-plus'],
        ['label' => 'Workspace', 'route' => 'pages.project-workspace', 'icon' => 'bx-cube'],
        ['label' => 'Finance', 'route' => 'pages.finance', 'icon' => 'bx-wallet'],
        ['label' => 'Reports', 'route' => 'pages.reports', 'icon' => 'bx-bar-chart-alt-2'],
    ],

];
