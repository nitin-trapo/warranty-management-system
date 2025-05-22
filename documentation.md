# Warranty Management System Documentation

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
   - [System Requirements](#system-requirements)
   - [Login](#login)
   - [User Roles](#user-roles)
3. [Dashboard](#dashboard)
   - [Overview](#overview)
   - [Time Period Filtering](#time-period-filtering)
   - [Key Statistics](#key-statistics)
4. [Claims Management](#claims-management)
   - [Viewing Claims](#viewing-claims)
   - [Creating New Claims](#creating-new-claims)
   - [Claim Validation](#claim-validation)
   - [Updating Claim Status](#updating-claim-status)
   - [Assigning Claims](#assigning-claims)
   - [Adding Notes](#adding-notes)
   - [File Upload Requirements](#file-upload-requirements)
5. [Reports](#reports)
   - [Claim Performance Report](#claim-performance-report)
   - [SKU Analysis Report](#sku-analysis-report)
   - [Product Type Analysis](#product-type-analysis)
   - [Category Analysis](#category-analysis)
   - [Exporting Reports](#exporting-reports)
6. [User Management](#user-management)
7. [Categories Management](#categories-management)
8. [Warranty Rules Management](#warranty-rules-management)
   - [Viewing Warranty Rules](#viewing-warranty-rules)
   - [Adding New Warranty Rules](#adding-new-warranty-rules)
   - [Editing Warranty Rules](#editing-warranty-rules)
   - [Deleting Warranty Rules](#deleting-warranty-rules)
   - [Using Warranty Rules](#using-warranty-rules)
9. [Notifications](#notifications)
9. [Troubleshooting](#troubleshooting)
10. [Appendix](#appendix)

## Introduction

The Warranty Management System is a comprehensive web-based application designed to streamline the process of handling warranty claims. It provides tools for claim submission, tracking, reporting, and analysis, helping organizations efficiently manage their warranty service operations.

Key features include:
- Centralized claim management
- Automated workflow for claim processing
- Comprehensive reporting and analytics
- File attachment support for claim documentation
- User role-based access control
- Email notifications
- SLA (Service Level Agreement) tracking

## Getting Started

### System Requirements

- Web server with PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled

### Login

1. Navigate to the system URL in your web browser
2. Enter your username and password
3. Click "Login"

If you've forgotten your password, contact your system administrator for assistance.

### User Roles

The system supports multiple user roles with different permissions:

- **Administrator**: Full access to all system features
- **Product Admin**: Can manage claims and approve/reject them
- **CS Agent**: Can create and process claims
- **Approver**: Can review and approve/reject claims

## Dashboard

### Overview

The dashboard provides a quick overview of the warranty claim system's current status and key metrics. It's designed to give users immediate insights into claim activity and performance.

### Time Period Filtering

The dashboard data can be filtered by different time periods:
- This Week
- This Month
- This Year (default)

Use the dropdown in the Claims Overview section to change the time period.

### Key Statistics

The dashboard displays several key statistics:
- Total Claims
- New Claims
- In Progress Claims
- On Hold Claims
- Resolved Claims
- Rejected Claims
- SLA Breaches
- Claims by Category
- Claims by Product Type
- Monthly Claim Trends

## Claims Management

### Viewing Claims

The Claims Management page displays all warranty claims in a sortable and filterable table. You can:
- Search for specific claims
- Sort by any column
- Filter by status
- View claim details

### Creating New Claims

To create a new warranty claim:

1. Click the "Add New Claim" button
2. Enter the Order ID and click "Look Up Order"
3. Verify customer information (name, email, phone)
4. Select the items to include in the claim by checking the checkboxes
5. For each selected item:
   - Choose the claim category
   - Enter a detailed description of the issue
   - Upload supporting files (photos/videos)
6. Click "Submit Claim"

### Claim Validation

The system validates claims to ensure all required information is provided:
- Customer information (name, email, phone) must be complete
- At least one item must be selected
- Each selected item must have a category and description
- File uploads must meet size and format requirements
- Duplicate claims for the same order ID and SKU combination are prevented

### Updating Claim Status

Claims can have the following statuses:
- New
- In Progress
- On Hold
- Approved
- Rejected

To update a claim's status:
1. Click on the claim to view details
2. Select the new status from the dropdown
3. Add a note explaining the status change
4. Click "Update Status"

### Assigning Claims

Claims can be assigned to specific CS Agents for processing:
1. Click the "Assign" button for a claim
2. Select a user from the dropdown
3. Click "Assign"

### Adding Notes

Notes can be added to claims to document the claim processing:
1. Navigate to the claim details page
2. Enter your note in the text area
3. Click "Add Note"

### File Upload Requirements

When uploading files for claims, the following restrictions apply:
- **Images**: Maximum size of 2MB
- **Videos**: Maximum size of 10MB, restricted to MP4 and MOV formats only

## Reports

The system provides several reports to analyze warranty claim data.

### Claim Performance Report

This report shows:
- Claims by status
- Average resolution time
- SLA compliance rate
- Escalated claims (exceeding SLA)

### SKU Analysis Report

This report shows:
- Most claimed SKUs
- Claims by category for each SKU
- Claim rate by SKU

### Product Type Analysis

This report shows:
- Most claimed product types
- Claims by category for each product type

### Category Analysis

This report shows:
- Claims by category
- SLA days for each category
- Monthly breakdown of claims by category

### Exporting Reports

All reports can be exported to Excel format:
1. Navigate to the desired report
2. Click the "Excel" button
3. The report will be downloaded with the date range in the filename

## User Management

Administrators can manage system users:
- Add new users
- Edit existing users
- Deactivate users
- Assign user roles

To access User Management:
1. Navigate to the Admin menu
2. Select "Users"

## Categories Management

Claim categories can be managed by administrators:
- Add new categories
- Edit existing categories
- Set SLA days for each category

To access Categories Management:
1. Navigate to the Admin menu
2. Select "Categories"

## Warranty Rules Management

The Warranty Rules Management module allows administrators to define and manage warranty policies for different product types. This ensures consistent application of warranty terms across all claims.

### Viewing Warranty Rules

The Warranty Rules page displays all defined warranty policies in a sortable and filterable table. Each rule includes:
- Product Type
- Warranty Duration (in months)
- Coverage Details
- Exclusions
- Creation/Update Information

### Adding New Warranty Rules

To add a new warranty rule:

1. Click the "Add New Rule" button
2. Enter the following information:
   - Product Type (must be unique)
   - Duration (in months)
   - Coverage Details (what is covered under warranty)
   - Exclusions (what is not covered under warranty)
3. Click "Save Rule"

### Editing Warranty Rules

To edit an existing warranty rule:

1. Click the "Edit" button for the rule you want to modify
2. Update the information as needed
3. Click "Update Rule"

### Deleting Warranty Rules

To delete a warranty rule:

1. Click the "Delete" button for the rule you want to remove
2. Confirm the deletion when prompted

### Using Warranty Rules

Warranty rules are used during the claim processing workflow to:
- Verify if a product is still under warranty based on its delivery date
- Determine what types of issues are covered
- Provide guidelines for approving or rejecting claims

## Notifications

The system sends email notifications for various events:
- New claim submission
- Claim status updates
- SLA breaches
- Claim assignments

Users can view their notifications in the system by clicking the bell icon in the top navigation bar.

## Troubleshooting

### Common Issues

1. **File Upload Errors**
   - Ensure files meet the size and format requirements
   - Check that the upload directory has proper permissions

2. **Order Lookup Failures**
   - Verify the order ID is correct
   - Check the system's connection to the order database

3. **Email Notification Issues**
   - Verify email settings in the system configuration
   - Check spam folders for missed notifications

### Support

For additional support, contact your system administrator or IT department.

## Appendix

### Glossary

- **Claim**: A warranty service request submitted by a customer
- **SLA (Service Level Agreement)**: The timeframe within which a claim should be resolved
- **SKU (Stock Keeping Unit)**: A unique identifier for a product
- **CS Agent**: Customer Service Agent responsible for processing claims
