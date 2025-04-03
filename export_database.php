<?php
/**
 * Database Export Script
 * 
 * This script exports the warranty management system database to an SQL file
 * for deployment to a production environment.
 */

// Include database configuration
require_once 'config/database.php';

// Set the output file path
$output_file = 'database/warranty_management_system_export.sql';

// Set the path to mysqldump (adjust this path based on your XAMPP installation)
$mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Create the backup command
$command = sprintf(
    '"%s" -h %s -u %s %s %s > %s',
    $mysqldump_path,
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    DB_PASS ? '-p' . escapeshellarg(DB_PASS) : '',
    escapeshellarg(DB_NAME),
    escapeshellarg($output_file)
);

// Execute the command
echo "Exporting database to $output_file...\n";
system($command, $return_var);

// Check if the export was successful
if ($return_var === 0) {
    echo "Database export completed successfully.\n";
} else {
    echo "Error exporting database. Return code: $return_var\n";
    echo "Please ensure that mysqldump.exe is located at: $mysqldump_path\n";
    echo "If not, please update the \$mysqldump_path variable in this script.\n";
}
