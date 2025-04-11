<?php
/**
 * Generate Dummy Claims
 * 
 * This script generates 50 dummy claims with realistic data for testing the reporting functionality.
 */

// Set page title
$pageTitle = 'Generate Dummy Claims';

// Include header
require_once 'includes/header.php';

// Database connection
$conn = getDbConnection();

// Initialize variables
$success = false;
$error = false;
$message = '';
$generatedCount = 0;

// Define arrays for random data generation
$customerNames = [
    'John Smith', 'Mary Johnson', 'Robert Williams', 'Patricia Brown', 'Michael Jones',
    'Linda Davis', 'James Miller', 'Jennifer Wilson', 'David Moore', 'Elizabeth Taylor',
    'Richard Anderson', 'Susan Thomas', 'Joseph Jackson', 'Margaret White', 'Charles Harris',
    'Jessica Martin', 'Thomas Thompson', 'Sarah Garcia', 'Christopher Martinez', 'Karen Robinson'
];

$emails = [
    'john@example.com', 'mary@example.com', 'robert@example.com', 'patricia@example.com',
    'michael@example.com', 'linda@example.com', 'james@example.com', 'jennifer@example.com',
    'david@example.com', 'elizabeth@example.com', 'richard@example.com', 'susan@example.com',
    'joseph@example.com', 'margaret@example.com', 'charles@example.com', 'jessica@example.com',
    'thomas@example.com', 'sarah@example.com', 'christopher@example.com', 'karen@example.com'
];

$phones = [
    '555-123-4567', '555-234-5678', '555-345-6789', '555-456-7890', '555-567-8901',
    '555-678-9012', '555-789-0123', '555-890-1234', '555-901-2345', '555-012-3456',
    '555-111-2222', '555-222-3333', '555-333-4444', '555-444-5555', '555-555-6666',
    '555-666-7777', '555-777-8888', '555-888-9999', '555-999-0000', '555-000-1111'
];

$addresses = [
    '123 Main St, Anytown, CA 12345', '456 Oak Ave, Springfield, NY 67890',
    '789 Pine Rd, Lakeside, TX 23456', '321 Maple Dr, Mountain View, WA 78901',
    '654 Elm Blvd, Riverside, FL 34567', '987 Cedar Ln, Hillside, IL 89012',
    '147 Birch Ct, Oceanview, OR 45678', '258 Spruce Way, Desert City, AZ 90123',
    '369 Willow Path, Snowville, CO 56789', '741 Fir Circle, Meadowland, MI 01234'
];

$productTypes = [
    'Smartphone', 'Laptop', 'Tablet', 'Desktop', 'Monitor',
    'Printer', 'Camera', 'Headphones', 'Speaker', 'Keyboard',
    'Mouse', 'Router', 'External Drive', 'Smart Watch', 'TV'
];

$productNames = [
    'TechPro X5', 'UltraBook Pro', 'SlimTab 10', 'PowerStation Desktop', 'ClearView Monitor',
    'ColorJet Printer', 'SnapShot Camera', 'SoundMax Headphones', 'AudioBlast Speaker', 'TypeMaster Keyboard',
    'SwiftPoint Mouse', 'NetConnect Router', 'StoragePlus Drive', 'FitTrack Watch', 'VisionPlus TV'
];

$skus = [
    'TP-X5-2023', 'UB-PRO-2023', 'ST-10-2023', 'PS-DT-2023', 'CV-MON-2023',
    'CJ-PRT-2023', 'SS-CAM-2023', 'SM-HPH-2023', 'AB-SPK-2023', 'TM-KBD-2023',
    'SP-MOU-2023', 'NC-RTR-2023', 'SP-DRV-2023', 'FT-WCH-2023', 'VP-TV-2023'
];

$issueDescriptions = [
    'Device won\'t power on', 'Screen is cracked', 'Battery drains too quickly',
    'Overheating during normal use', 'Wi-Fi connectivity issues',
    'USB ports not working', 'Strange noise during operation', 'Software crashes frequently',
    'Keyboard keys not responding', 'Touchscreen not responsive',
    'Color distortion on display', 'Device shuts down unexpectedly', 'Charging port damaged',
    'Bluetooth pairing issues', 'Audio quality is poor'
];

$statuses = ['new', 'in_progress', 'on_hold', 'approved', 'rejected'];
$actions = ['replace', 'repair', 'refund'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Get category IDs and details from claim_categories table
        $stmt = $conn->query("SELECT id, name, sla_days FROM claim_categories");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categories)) {
            throw new Exception("No claim categories found. Please create categories first.");
        }
        
        // Get user IDs for assignment
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'admin' OR role = 'agent'");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($users)) {
            throw new Exception("No admin or agent users found. Please create users first.");
        }
        
        // Get product types from warranty_rules table
        $stmt = $conn->query("SELECT product_type FROM warranty_rules");
        $dbProductTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($dbProductTypes)) {
            throw new Exception("No product types found in warranty_rules table. Please create warranty rules first.");
        }
        
        // Generate 50 claims
        for ($i = 0; $i < 50; $i++) {
            // Generate random dates within the last 90 days
            $daysAgo = rand(0, 90);
            $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
            $updatedAt = date('Y-m-d H:i:s', strtotime("-" . rand(0, $daysAgo) . " days"));
            
            // Random customer data
            $customerName = $customerNames[array_rand($customerNames)];
            $email = $emails[array_rand($emails)];
            $phone = $phones[array_rand($phones)];
            $address = $addresses[array_rand($addresses)];
            
            // Generate a random order ID
            $orderId = 'ORD-' . rand(10000, 99999);
            
            // Generate a random delivery date
            $deliveryDate = date('Y-m-d', strtotime("-" . rand(30, 180) . " days"));
            
            // Random claim data
            $status = $statuses[array_rand($statuses)];
            $assignedTo = $users[array_rand($users)];
            $action = $actions[array_rand($actions)];
            
            // Generate a claim number
            $claimNumber = 'CLM-' . rand(10000, 99999);
            
            // Insert claim - using correct column names from the claims table
            $stmt = $conn->prepare("INSERT INTO claims (order_id, customer_name, customer_email, customer_phone, delivery_date, status, created_at, updated_at, created_by, claim_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $customerName, $email, $phone, $deliveryDate, $status, $createdAt, $updatedAt, $assignedTo, $claimNumber]);
            
            $claimId = $conn->lastInsertId();
            
            // Generate 1-3 items per claim
            $itemCount = rand(1, 3);
            for ($j = 0; $j < $itemCount; $j++) {
                // Use product type from warranty_rules table
                $productType = $dbProductTypes[array_rand($dbProductTypes)];
                
                // Get a matching product name and SKU or generate one if needed
                $productNameIndex = array_search($productType, $productTypes);
                if ($productNameIndex !== false) {
                    $productName = $productNames[$productNameIndex];
                    $sku = $skus[$productNameIndex];
                } else {
                    // Generate a product name and SKU based on the product type
                    $suffixes = ['Pro', 'Ultra', 'Max', 'Elite', 'Premium'];
                    $productName = ucfirst($productType) . ' ' . $suffixes[array_rand($suffixes)];
                    $sku = strtoupper(substr($productType, 0, 2)) . '-' . rand(1000, 9999);
                }
                
                $issueDescription = $issueDescriptions[array_rand($issueDescriptions)];
                
                // Select a random category from the claim_categories table
                $category = $categories[array_rand($categories)];
                $categoryId = $category['id'];
                
                // For some claims, make them exceed SLA to test reporting
                $shouldExceedSLA = (rand(1, 10) <= 3); // 30% chance to exceed SLA
                
                // If this claim should exceed SLA and the status is not 'approved' or 'rejected'
                if ($shouldExceedSLA && !in_array($status, ['approved', 'rejected']) && !empty($category['sla_days'])) {
                    // Make the claim older than its SLA days
                    $slaDays = intval($category['sla_days']);
                    if ($slaDays > 0) {
                        $daysAgo = $slaDays + rand(1, 10); // Exceed by 1-10 days
                        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
                        
                        // Update the claim's created_at date
                        $stmt = $conn->prepare("UPDATE claims SET created_at = ? WHERE id = ?");
                        $stmt->execute([$createdAt, $claimId]);
                    }
                }
                
                // Insert claim item
                $stmt = $conn->prepare("INSERT INTO claim_items (claim_id, sku, product_name, product_type, description, category_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$claimId, $sku, $productName, $productType, $issueDescription, $categoryId]);
            }
            
            // Add a note to some claims
            if (rand(0, 1)) {
                $noteText = "This is a test note for claim #$claimId. Added by system for testing purposes.";
                $stmt = $conn->prepare("INSERT INTO claim_notes (claim_id, created_by, note, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$claimId, $assignedTo, $noteText, $updatedAt]);
            }
            
            // Log the action
            if (function_exists('logAuditAction')) {
                logAuditAction($assignedTo, 'create', 'claim', $claimId, 'Dummy claim created for testing');
            }
            
            $generatedCount++;
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = true;
        $message = "Successfully generated $generatedCount dummy claims for testing.";
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $error = true;
        $message = "Error generating dummy claims: " . $e->getMessage();
        error_log($message);
    }
}
?>

<div class="page-title">
    <h1>Generate Dummy Claims</h1>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Generate Test Data</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    This tool will generate 50 dummy claims with random data for testing the reporting functionality.
                    Each claim will have 1-3 items with various product types, statuses, and dates.
                </p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This will add 50 new claims to your database. Use only in testing environments.
                </div>
                
                <form method="POST" action="generate_dummy_claims.php">
                    <button type="submit" class="btn btn-primary" <?php echo $success ? 'disabled' : ''; ?>>
                        <i class="fas fa-database me-1"></i> Generate 50 Dummy Claims
                    </button>
                    
                    <?php if ($success): ?>
                    <a href="reports.php" class="btn btn-success ms-2">
                        <i class="fas fa-chart-bar me-1"></i> View Reports
                    </a>
                    <a href="dashboard.php" class="btn btn-info ms-2">
                        <i class="fas fa-tachometer-alt me-1"></i> View Dashboard
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Data Generation Details</h5>
            </div>
            <div class="card-body">
                <p>The generated data will include:</p>
                <ul>
                    <li><strong>Claims:</strong> 50 claims with random statuses</li>
                    <li><strong>Claim Items:</strong> 1-3 items per claim</li>
                    <li><strong>Dates:</strong> Random dates within the last 90 days</li>
                    <li><strong>Product Types:</strong> Various electronics (smartphones, laptops, etc.)</li>
                    <li><strong>Statuses:</strong> Mix of new, in progress, on hold, approved, and rejected</li>
                </ul>
                
                <p class="mb-0">This data will help you test:</p>
                <ul>
                    <li>Dashboard statistics</li>
                    <li>Claim performance reports</li>
                    <li>SKU analysis</li>
                    <li>Product type analysis</li>
                    <li>SLA compliance tracking</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
