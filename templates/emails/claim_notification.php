<?php
/**
 * Claim Notification Email Template
 * 
 * Available variables:
 * $claim - Array of claim data
 * $claimItems - Array of claim items data
 * $companyName - Company name from settings
 * $isCustomer - Boolean indicating if the recipient is the customer
 * $isStaffCreator - Boolean indicating if the recipient is the staff member who created the claim
 */

// Set default values if not provided
$isCustomer = $isCustomer ?? false;
$isStaffCreator = $isStaffCreator ?? false;
$subject = "{$companyName} - New Warranty Claim #{$claim['id']} ({$claim['claim_number']})";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #2563eb;
            color: #fff;
            padding: 20px;
            text-align: center;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px;
        }
        .claim-details {
            margin-bottom: 20px;
        }
        .claim-details table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .claim-details th, .claim-details td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .claim-details th {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 30%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
           
            <?php if ($isCustomer): ?>
                <h3>Your Warranty Claim Has Been Submitted</h3>
                <p>Thank you for submitting your warranty claim. We have received your request and will begin processing it shortly.</p>
            <?php elseif ($isStaffCreator): ?>
                <h3>Warranty Claim Created Successfully</h3>
                <p>You have successfully created a new warranty claim with the following details:</p>
            <?php else: ?>
                <h3>New Warranty Claim Submitted</h3>
                <p>A new warranty claim has been submitted with the following details:</p>
            <?php endif; ?>
            
            <div class="claim-details">
                <table>
                    <tr>
                        <th>Claim Number</th>
                        <td><?php echo htmlspecialchars($claim['claim_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Order ID</th>
                        <td><?php echo htmlspecialchars($claim['order_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Customer Name</th>
                        <td><?php echo htmlspecialchars($claim['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Customer Email</th>
                        <td><?php echo htmlspecialchars($claim['customer_email']); ?></td>
                    </tr>
                    <?php if (!empty($claim['customer_phone'])): ?>
                    <tr>
                        <th>Customer Phone</th>
                        <td><?php echo htmlspecialchars($claim['customer_phone']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Delivery Date</th>
                        <td><?php echo htmlspecialchars($claim['delivery_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $claim['status']))); ?></td>
                    </tr>
                    <tr>
                        <th>Submission Date</th>
                        <td><?php echo htmlspecialchars($claim['created_at']); ?></td>
                    </tr>
                    <?php if (!$isCustomer && !empty($claim['created_by_name'])): ?>
                    <tr>
                        <th>Created By</th>
                        <td><?php echo htmlspecialchars($claim['created_by_name']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <h4>Claim Items</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Product Type</th>
                            <th>Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claimItems as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_type']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($isCustomer): ?>
                <p>We will review your claim and get back to you as soon as possible. Your claim reference number is <strong><?php echo htmlspecialchars($claim['claim_number']); ?></strong>. Please keep this for your records.</p>
                <p>If you have any questions about your claim, please contact our customer service team.</p>
            <?php else: ?>
                <p>You can view and manage this claim in the Warranty Management System.</p>
                <?php if (!empty($adminUrl)): ?>
                <p><a href="<?php echo htmlspecialchars($adminUrl); ?>" class="button">View Claim</a></p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p>Thank you,<br><?php echo htmlspecialchars($companyName); ?> Team</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
