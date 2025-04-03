<?php
/**
 * Deployment Script for Warranty Management System
 * 
 * This script prepares the application for deployment by:
 * 1. Creating a deployment package (ZIP archive)
 * 2. Generating production configuration files
 */

// Configuration
$app_name = 'warranty-management-system';
$version = '1.0.0';
$timestamp = date('Y-m-d_H-i-s');
$output_dir = 'deployment';
$output_filename = "{$app_name}_{$version}_{$timestamp}.zip";
$output_path = "{$output_dir}/{$output_filename}";

// Create output directory if it doesn't exist
if (!file_exists($output_dir)) {
    mkdir($output_dir, 0755, true);
    echo "Created deployment directory: {$output_dir}\n";
}

// Files and directories to include in the deployment package
$include = [
    'admin',
    'ajax',
    'api',
    'assets',
    'config',
    'controllers',
    'database',
    'includes',
    'models',
    'uploads',
    'vendor',
    'views',
    'index.php',
    'login.php',
    'logout.php',
    'composer.json',
    'README.md'
];

// Files and directories to exclude from the deployment package
$exclude = [
    'vendor/*/test*',
    'vendor/*/doc*',
    'vendor/*/example*',
    '.git',
    'deployment',
    'export_database.php',
    'deploy.php'
];

// Create a new ZIP archive
$zip = new ZipArchive();
if ($zip->open($output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create ZIP archive: {$output_path}\n");
}

// Function to recursively add files to the ZIP archive
function addFilesToZip($zip, $dir, $base_dir = '') {
    global $exclude;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        // Skip excluded files and directories
        $file_path = str_replace('\\', '/', $file->getPathname());
        $should_exclude = false;
        
        foreach ($exclude as $pattern) {
            if (fnmatch($pattern, $file_path)) {
                $should_exclude = true;
                break;
            }
        }
        
        if ($should_exclude) {
            continue;
        }
        
        // Get real and relative path for current file
        $file_path = $file->getRealPath();
        $relative_path = ($base_dir === '') ? $file->getFilename() : $base_dir . '/' . $file->getFilename();
        
        // Add current file to archive
        if ($file->isFile()) {
            $zip->addFile($file_path, $relative_path);
            echo "Added file: {$relative_path}\n";
        } elseif ($file->isDir()) {
            // Add empty directory
            $zip->addEmptyDir($relative_path);
            echo "Added directory: {$relative_path}\n";
            
            // Add files in this directory
            addFilesToZip($zip, $file_path, $relative_path);
        }
    }
}

// Add files to the ZIP archive
foreach ($include as $item) {
    if (file_exists($item)) {
        if (is_dir($item)) {
            $zip->addEmptyDir($item);
            echo "Added directory: {$item}\n";
            addFilesToZip($zip, $item, $item);
        } else {
            $zip->addFile($item, $item);
            echo "Added file: {$item}\n";
        }
    } else {
        echo "Warning: {$item} does not exist and will not be included in the deployment package.\n";
    }
}

// Create a production configuration file
$prod_config_dir = 'deployment/config';
if (!file_exists($prod_config_dir)) {
    mkdir($prod_config_dir, 0755, true);
}

// Create production database configuration
$prod_db_config = <<<EOT
<?php
/**
 * Production Database Configuration
 * 
 * This file contains the database connection settings for the Warranty Management System.
 */

// Database credentials
define('DB_HOST', 'localhost'); // Change this to your production database host
define('DB_NAME', 'warranty_management_system'); // Change this to your production database name
define('DB_USER', 'db_username'); // Change this to your production database username
define('DB_PASS', 'db_password'); // Change this to your production database password

// Create database connection
function getDbConnection() {
    try {
        \$conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        // Set the PDO error mode to exception
        \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Set default fetch mode to associative array
        \$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return \$conn;
    } catch(PDOException \$e) {
        die("Connection failed: " . \$e->getMessage());
    }
}
EOT;

file_put_contents("{$prod_config_dir}/database.php", $prod_db_config);
echo "Created production database configuration file: {$prod_config_dir}/database.php\n";

// Create .htaccess file for security
$htaccess_content = <<<EOT
# Disable directory browsing
Options -Indexes

# Protect files and directories
<FilesMatch "^\.ht">
    Order allow,deny
    Deny from all
    Satisfy All
</FilesMatch>

# Protect config files
<FilesMatch "^(database\.php|api_config\.php)$">
    Order allow,deny
    Deny from all
    Satisfy All
</FilesMatch>

# Redirect to HTTPS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# PHP settings
<IfModule mod_php7.c>
    php_flag display_errors Off
    php_flag log_errors On
    php_value error_log /path/to/error.log
    php_value max_execution_time 60
    php_value memory_limit 128M
    php_value post_max_size 20M
    php_value upload_max_filesize 10M
</IfModule>
EOT;

file_put_contents("{$prod_config_dir}/.htaccess", $htaccess_content);
echo "Created production .htaccess file: {$prod_config_dir}/.htaccess\n";

// Add the production configuration files to the ZIP archive
$zip->addFile("{$prod_config_dir}/database.php", "config/database.php.production");
$zip->addFile("{$prod_config_dir}/.htaccess", ".htaccess");

// Create deployment instructions
$instructions = <<<EOT
# Warranty Management System Deployment Instructions

## Pre-requisites
- Web server with PHP 7.4+ and MySQL 5.7+
- Composer (for dependency management)

## Deployment Steps

1. **Extract the deployment package**
   Extract the contents of this ZIP file to your web server's document root or a subdirectory.

2. **Configure the database**
   - Create a new MySQL database for the application
   - Import the database schema from `database/warranty_management_system_export.sql`
   - Rename `config/database.php.production` to `config/database.php`
   - Edit `config/database.php` and update the database credentials

3. **Configure API settings**
   - Edit `config/api_config.php` and update the API credentials for your production environment

4. **Set file permissions**
   - Make sure the web server has write permissions to the `uploads` directory
   - `chmod -R 755 uploads`

5. **Update .htaccess**
   - Review and update the .htaccess file as needed for your server configuration

6. **Test the application**
   - Access the application in your web browser
   - Log in with your admin credentials
   - Verify that all features are working correctly

## Troubleshooting
- Check the web server error logs for any PHP errors
- Verify that the database connection is working
- Ensure that all required PHP extensions are enabled

## Support
For support, please contact the development team.
EOT;

file_put_contents("{$output_dir}/deployment_instructions.md", $instructions);
echo "Created deployment instructions: {$output_dir}/deployment_instructions.md\n";
$zip->addFile("{$output_dir}/deployment_instructions.md", "deployment_instructions.md");

// Close the ZIP archive
if ($zip->close()) {
    echo "Deployment package created successfully: {$output_path}\n";
    echo "Size: " . round(filesize($output_path) / (1024 * 1024), 2) . " MB\n";
} else {
    echo "Error creating deployment package.\n";
}

// Clean up temporary files
unlink("{$prod_config_dir}/database.php");
unlink("{$prod_config_dir}/.htaccess");
rmdir($prod_config_dir);

echo "\nDeployment preparation completed.\n";
echo "Next steps:\n";
echo "1. Export your database using export_database.php\n";
echo "2. Upload the deployment package to your production server\n";
echo "3. Follow the deployment instructions to complete the installation\n";
