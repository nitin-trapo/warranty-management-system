<?php
/**
 * Settings
 * 
 * This file will contain system settings for the Warranty Management System.
 * Currently under development.
 */

// Set page title
$pageTitle = 'Settings';

// Include required files
require_once '../includes/auth_helper.php';

// Enforce admin-only access
enforceAdminOnly();

// Include header
require_once 'includes/header.php';

?>

<div class="page-title">
    <h1>Settings</h1>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">System Settings</h5>
            </div>
            <div class="card-body text-center py-5">
                <div class="coming-soon-container">
                    <i class="fas fa-cogs fa-5x text-muted mb-3"></i>
                    <h2 class="mb-3">Coming Soon</h2>
                    <p class="lead text-muted">
                        The settings page is currently under development. 
                        Check back later for system configuration options.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Planned Features</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        System Configuration
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        Email Templates
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        Notification Settings
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        SLA Configuration
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check-circle text-muted me-3"></i>
                        File Upload Limits
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">System Version:</div>
                    <div class="col-sm-8">1.0.0</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">PHP Version:</div>
                    <div class="col-sm-8"><?php echo phpversion(); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Database:</div>
                    <div class="col-sm-8">MySQL</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Server:</div>
                    <div class="col-sm-8"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Last Updated:</div>
                    <div class="col-sm-8"><?php echo date('F d, Y'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.coming-soon-container {
    padding: 30px 0;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>
