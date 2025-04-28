<?php
/**
 * Fixed version of the claims.php file with proper try-catch blocks
 * Copy the contents of this file to replace claims.php
 */

// Send email notifications to approvers based on category
foreach ($insertedClaimIds as $index => $claimId) {
    // Get the category ID for this claim
    $categoryId = $categoryIds[$index];
    
    try {
        // Get category details including approver
        $categoryStmt = $conn->prepare("SELECT * FROM claim_categories WHERE id = ?");
        $categoryStmt->execute([$categoryId]);
        $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            error_log("Category not found for ID: $categoryId");
            continue;
        }
        
        $approverRole = $category['approver'] ?? null;
        error_log("Category ID: $categoryId, Name: {$category['name']}, Approver Role: " . ($approverRole ?: 'None'));
        
        if (empty($approverRole)) {
            error_log("No approver role set for category ID: $categoryId");
            continue;
        }
        
        // Get users with this approver role
        $approverStmt = $conn->prepare("SELECT id, username, email, first_name, last_name, approver_role FROM users WHERE approver_role = ? AND status = 'active'");
        $approverStmt->execute([$approverRole]);
        $approvers = $approverStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($approvers) . " approvers for role: $approverRole");
        error_log("Approvers: " . json_encode($approvers));
        
        if (!empty($approvers)) {
            // Get claim details for email
            $claimStmt = $conn->prepare("SELECT c.*, u.email as created_by_email, u.first_name as created_by_first_name, u.last_name as created_by_last_name 
                                       FROM claims c 
                                       LEFT JOIN users u ON c.created_by = u.id 
                                       WHERE c.id = ?");
            $claimStmt->execute([$claimId]);
            $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get claim items
            $itemsStmt = $conn->prepare("SELECT ci.*, cc.name as category_name 
                                      FROM claim_items ci 
                                      LEFT JOIN claim_categories cc ON ci.category_id = cc.id 
                                      WHERE ci.claim_id = ?");
            $itemsStmt->execute([$claimId]);
            $claimItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare email recipients
            $recipients = [];
            foreach ($approvers as $approver) {
                if (!empty($approver['email'])) {
                    $recipients[] = $approver['email'];
                    error_log("Added recipient: {$approver['email']} (Approver Role: {$approver['approver_role']})");
                } else {
                    error_log("Approver without email: " . json_encode($approver));
                }
            }
            error_log("Final recipients list: " . json_encode($recipients));
            
            // Get the creator's user details
            $creatorUser = getUserDetailsById($userId);
            if ($creatorUser) {
                $claim['created_by_name'] = $creatorUser['first_name'] . ' ' . $creatorUser['last_name'];
                $claim['created_by_email'] = $creatorUser['email'];
            }
            
            // Add category approver information to claim data for email template
            $claim['category_approver'] = $approverRole;
            
            // Send notification email
            if (!empty($recipients)) {
                error_log("Sending email notification for claim ID: $claimId to recipients: " . json_encode($recipients));
                $emailResult = sendClaimNotificationEmail($claim, $claimItems, $recipients, true, true);
                error_log("Email notification result: " . ($emailResult ? 'Success' : 'Failed'));
            } else {
                error_log("No recipients found for claim ID: $claimId with approver role: $approverRole");
            }
        }
    } catch (Exception $e) {
        error_log("Error processing notification for claim ID $claimId: " . $e->getMessage());
    }
}
?>
