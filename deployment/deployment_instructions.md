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