# Warranty Management System (WMS)

A comprehensive system designed to streamline the process of validating customer purchases, managing warranty claims, and tracking claim resolutions.

## Features

- Claim Processing & Validation
- Admin Panel & Claim Approval
- Reporting & Analytics
- ODIN API Integration (Real-Time Validation & Order Creation)

## Technology Stack

- **Frontend**: Web-based Dashboard (JQuery/Bootstrap)
- **Backend**: PHP
- **Database**: MySQL
- **API Integration**: REST API (ODIN/iStoreiSend)
- **Authentication**: OAuth2 / JWT

## Installation

1. Clone the repository to your local XAMPP htdocs folder
2. Import the database schema from `database/wms_db.sql`
3. Configure database connection in `config/database.php`
4. Configure API settings in `config/api_config.php`
5. Access the application at `http://localhost/warranty-management-system`

## User Roles

- **Admin**: Manages claims, warranty rules, users, audit logs, reporting, SLA tracking, and categories
- **Customer Service (CS) Agent**: Creates and updates warranty claims, validates claims via the ODIN API, tracks order fulfillment

## License

Proprietary - All rights reserved
