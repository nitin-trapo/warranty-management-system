<?php
/**
 * Admin Header
 * 
 * This file contains the header for the admin section of the Warranty Management System.
 */

// Include required files
require_once __DIR__ . '/../../config/config.php';

// Ensure timezone is set correctly
date_default_timezone_set(TIMEZONE);

require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../includes/alert_helper.php';

// Require admin or CS agent privileges
requireAdminOrCsAgent();

// Get admin user data
$user = getUserById($_SESSION['user_id']);

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo isCsAgent() ? 'WMS Agent' : 'Admin Dashboard'; ?> - <?php echo MAIL_FROM_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --background-color: #f8fafc;
            --sidebar-color: #1e293b;
            --sidebar-hover-color: #334155;
            --card-color: #ffffff;
            --text-color: #1e293b;
            --light-text-color: #64748b;
        }
        
        /* DataTables Select Styling */
        div.dataTables_wrapper div.dataTables_length select {
            width: auto;
            display: inline-block;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-color);
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            appearance: none;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        
        /* Remove focus styles */
        div.dataTables_wrapper div.dataTables_length select:focus {
            outline: none;
            box-shadow: none;
            border-color: #ced4da;
        }
        
        /* Remove focus styles from all buttons */
        button:focus, 
        .btn:focus,
        .page-link:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        
        /* Improved DataTable styling */
        .table.dataTable {
            border-collapse: collapse !important;
            border: none !important;
        }
        
        .table.dataTable tbody tr:hover {
            background-color: transparent !important;
        }
        
        .table.dataTable.no-footer {
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .table.dataTable thead th, 
        .table.dataTable thead td {
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        /* Remove all table borders */
        .table.table-bordered,
        .table.table-bordered th,
        .table.table-bordered td {
            border: none !important;
        }
        
        /* Pagination styling */
        .dataTables_paginate .paginate_button {
            border: none !important;
            background: transparent !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f8f9fa !important;
            border: none !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #007bff !important;
            color: white !important;
            border: none !important;
        }
        
        .dataTables_paginate .paginate_button.current:hover {
            background: #0069d9 !important;
            color: white !important;
        }
        
        /* Fix for horizontal scrolling */
        .dataTables_wrapper {
            overflow-x: hidden;
            width: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            font-size: 0.9rem;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background-color: var(--sidebar-color);
            color: #fff;
            width: 200px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-collapsed {
            width: 50px;
        }
        
        .sidebar-header {
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            height: 50px;
        }
        
        .sidebar-header h3 {
            font-size: 1rem;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .sidebar-header .logo-icon {
            font-size: 1.4rem;
            margin-right: 10px;
            min-width: 24px;
        }
        
        .sidebar-menu {
            padding: 0;
            list-style: none;
            margin-top: 15px;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 8px 15px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-size: 0.85rem;
        }
        
        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover-color);
            color: #fff;
            border-left-color: var(--accent-color);
        }
        
        .sidebar-menu a.active {
            background-color: var(--sidebar-hover-color);
            color: #fff;
            border-left-color: var(--accent-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            font-size: 0.95rem;
            width: 18px;
            text-align: center;
        }
        
        .sidebar-menu .menu-text {
            white-space: nowrap;
            overflow: hidden;
        }
        
        .sidebar-divider {
            height: 1px;
            margin: 10px 15px;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-footer {
            padding: 10px 15px;
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 200px;
            transition: all 0.3s;
            width: calc(100% - 200px);
        }
        
        .main-content-expanded {
            margin-left: 50px;
            width: calc(100% - 50px);
        }
        
        /* Navbar Styles */
        .top-navbar {
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
            height: 50px;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1rem;
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .navbar-nav {
            display: flex;
            align-items: center;
        }
        
        .navbar-nav .nav-item {
            position: relative;
        }
        
        .navbar-nav .nav-link {
            color: var(--text-color);
            padding: 0.25rem 0.75rem;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .navbar-nav .nav-link i {
            font-size: 1rem;
        }
        
        .navbar-nav .badge {
            position: absolute;
            top: -2px;
            right: 0;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }
        
        .user-dropdown img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Page Content Styles */
        .page-content {
            padding: 1rem;
        }
        
        .page-title {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0.75rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .stat-card {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .stat-card .card-body {
            padding: 1rem;
        }
        
        .stat-card .stat-icon {
            font-size: 1.8rem;
            opacity: 0.8;
        }
        
        .stat-card .stat-title {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.25rem;
        }
        
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        /* Dropdown Styles */
        .dropdown-menu {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            padding: 0.5rem 0;
            font-size: 0.85rem;
        }
        
        .dropdown-item {
            padding: 0.4rem 1rem;
        }
        
        .dropdown-divider {
            margin: 0.3rem 0;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                width: 50px;
            }
            
            .sidebar-collapsed {
                width: 0;
                left: -50px;
            }
            
            .main-content {
                margin-left: 50px;
                width: calc(100% - 50px);
            }
            
            .main-content-expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .menu-text, .sidebar-header h3 {
                display: none;
            }
            
            .sidebar-header {
                justify-content: center;
                padding: 10px 0;
            }
            
            .sidebar-header .logo-icon {
                margin-right: 0;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 10px 0;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.1rem;
            }
            
            .sidebar-footer {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-title .btn {
                margin-top: 0.5rem;
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-shield-alt logo-icon"></i>
            <h3 class="menu-text"><?php echo isCsAgent() ? 'WMS Agent' : 'WMS Admin'; ?></h3>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="claims.php" class="<?php echo $currentPage == 'claims.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span class="menu-text">Claims Management</span>
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li>
                <a href="users.php" class="<?php echo $currentPage == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">User Management</span>
                </a>
            </li>
            <li>
                <a href="categories.php" class="<?php echo $currentPage == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span class="menu-text">Categories</span>
                </a>
            </li>
            <li>
                <a href="warranty_rules.php" class="<?php echo $currentPage == 'warranty_rules.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span class="menu-text">Warranty Rules</span>
                </a>
            </li>
            <?php endif; ?>
            
            <div class="sidebar-divider"></div>
            
            <li>
                <a href="reports.php" class="<?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reports</span>
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li>
                <a href="audit_logs.php" class="<?php echo $currentPage == 'audit_logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span class="menu-text">Audit Logs</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span class="menu-text">Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div>
                <button class="toggle-sidebar" id="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <ul class="navbar-nav d-flex flex-row">
                <li class="nav-item dropdown me-2">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="badge rounded-pill bg-danger">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">New claim submitted</a></li>
                        <li><a class="dropdown-item" href="#">SLA breach alert</a></li>
                        <li><a class="dropdown-item" href="#">System update available</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-none d-md-inline me-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <div class="user-dropdown">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . '+' . $user['last_name']); ?>&background=random" alt="User Avatar">
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <!-- Alert Container -->
        <div class="container-fluid mt-3">
            <?php echo displayAlert(); ?>
        </div>
        
        <!-- Page Content -->
        <div class="container-fluid p-3">
