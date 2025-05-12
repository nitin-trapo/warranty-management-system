<?php
/**
 * AJAX handler for creating manual orders
 * This file processes AJAX requests for creating manual orders and returns JSON responses
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/odin_api_helper.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['claim_id']) || empty($input['claim_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Claim ID is required'
    ]);
    exit;
}

// Check if verified SKUs exist in session
if (!isset($_SESSION['verified_skus']) || empty($_SESSION['verified_skus'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No verified SKUs found. Please verify SKUs first.'
    ]);
    exit;
}

$claimId = (int)$input['claim_id'];
$verifiedSkus = $_SESSION['verified_skus'];

// Create log directory if it doesn't exist
$logDir = '../../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log file path
$logFile = $logDir . '/api_requests.log';

// Log the request
$logMessage = "[" . date('Y-m-d H:i:s') . "] Manual order creation request for claim ID: " . $claimId . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

try {
    // Establish database connection
    $conn = getDbConnection();
    
    // Get claim data
    $claimQuery = "SELECT * FROM claims WHERE id = ?";
    $stmt = $conn->prepare($claimQuery);
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$claim) {
        throw new Exception("Claim not found");
    }
    
    // Get order details from ODIN API
    $orderDetails = getOrderDetails($claim['order_id']);
    
    if (!$orderDetails || isset($orderDetails['error'])) {
        throw new Exception(isset($orderDetails['error']) ? $orderDetails['error'] : 'Failed to retrieve order details');
    }
    
    // Generate document number - just use the order ID without any additional digits
    $documentNo = 'MO-' . $claim['order_id'];
    
    // Check if a manual order with this document number already exists
    $checkQuery = "SELECT id FROM manual_orders WHERE document_no = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->execute([$documentNo]);
    
    // If a document with this number already exists, we'll still use the same number
    // The database will still create a unique record with a unique ID
    // This approach allows for multiple manual orders with the same document number
    // if needed for the same order
    
    // Ensure we have a phone number (required by API)
    $customerPhone = !empty($claim['customer_phone']) ? $claim['customer_phone'] : '+60123456789';
    
    // Prepare order data for API
    $orderData = [
        'storageClientNo' => ODIN_API_STORAGE_CLIENT_NO,
        'orderOrigin' => 'OTHER',
        'documentNo' => $documentNo,
        'timeFrame' => 'next',
        'orderType' => 'MO',
        'deliveryType' => 'standard',
        'deliveryDate' => null,
        'warehouseOrder' => 'yes',
        'cod' => 'no',
        'codAmount' => 0,
        'insuranceAmount' => 0,
        'selfCollect' => 'no',
        'isInPost' => '0',
        'addressEmail' => $claim['customer_email'] ?? '',
        'senderEmail' => '',
        'boxMachineName' => '',
        'boxSize' => '',
        'phoneNumber' => $customerPhone,
        'courierService' => '',
        'trackingCode' => '',
        'currency' => 'MYR',
        'remark' => 'Manual Order for Claim #' . $claimId,
        'shipperAddr' => [
            'customerNo' => 'Bilal-Hayat',
            'customerDesc' => 'Ahmad Khusairi Ali',
            'addrTypeNo' => 'ADDRESS_TYPE_HOME',
            'addr1' => 'Pejabat Dekan Fakulti Sains Teknologi, Universiti Kebangsaan M',
            'addr2' => 'Hulu Langat',
            'addr3' => '',
            'city' => 'Bandar Baru Bangi',
            'postcode' => '43600',
            'state' => 'Selangor',
            'country' => 'Malaysia',
            'telNo' => '+60261744063',
            'faxNo' => '',
            'email' => 'test@email.com',
            'contactPerson' => 'Ahmad Khusairi Ali',
            'defaultAddr' => false
        ],
        'receiverAddr' => [
            'customerNo' => 'Client01',
            'customerDesc' => $claim['customer_name'] ?? 'Customer',
            'addrTypeNo' => 'ADDRESS_TYPE_HOME',
            'addr1' => 'No. 47, Jalan SP 8/8, Bandar Saujana Putra',
            'addr2' => '',
            'addr3' => '',
            'city' => 'Jenjarom',
            'postcode' => '42610',
            'state' => 'Selangor',
            'country' => 'Malaysia',
            'telNo' => $customerPhone, // Using the placeholder phone number if customer_phone is empty
            'faxNo' => '',
            'email' => $claim['customer_email'] ?? '',
            'contactPerson' => '',
            'defaultAddr' => false
        ],
        'detailList' => $verifiedSkus
    ];
    
    // Get authentication data
    $authData = getOdinApiAuth();
    
    if (!$authData) {
        throw new Exception('Failed to authenticate with ODIN API');
    }
    
    // API URL for creating manual order
    $apiUrl = ODIN_API_BASE_URL . '/WebApiOrder/doAddWebApiMOOrder';
    
    // Log the request data
    $requestLog = "[" . date('Y-m-d H:i:s') . "] Manual order API request data: " . json_encode($orderData, JSON_PRETTY_PRINT) . "\n";
    file_put_contents($logFile, $requestLog, FILE_APPEND);
    
    // Initialize cURL session
    $curl = curl_init();
    
    // Set cURL options
    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60, // 60 seconds timeout
        CURLOPT_CONNECTTIMEOUT => 30, // 30 seconds connection timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($orderData),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: ' . $authData['auth_token'],
            'Cookie: JSESSIONID=' . $authData['jsessionid']
        ),
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for testing
        CURLOPT_SSL_VERIFYHOST => 0      // Disable host verification for testing
    ));
    
    // Execute the request
    $response = curl_exec($curl);
    
    // Check for cURL errors
    if (curl_errno($curl)) {
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] cURL Error: " . curl_error($curl) . "\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        
        // Get the specific error
        $errorCode = curl_errno($curl);
        $errorMsg = curl_error($curl);
        curl_close($curl);
        
        // Provide a more user-friendly message for timeout errors
        if ($errorCode == CURLE_OPERATION_TIMEDOUT) {
            throw new Exception('API connection error: The request timed out. Please try again later.');
        } else {
            throw new Exception('API connection error: ' . $errorMsg);
        }
    }
    
    // Get HTTP status code
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($curl);
    
    // Log the response
    $responseLog = "[" . date('Y-m-d H:i:s') . "] Manual order API response: " . $response . "\n";
    file_put_contents($logFile, $responseLog, FILE_APPEND);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if response is valid
    if ($httpCode != 200) {
        throw new Exception('API error: HTTP code ' . $httpCode);
    }
    
    if (!isset($responseData['success']) || $responseData['success'] !== true) {
        // Log the full response for debugging
        $debugLog = "[" . date('Y-m-d H:i:s') . "] API Error Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        file_put_contents($logFile, $debugLog, FILE_APPEND);
        
        // Extract error message with more detailed fallbacks
        if (isset($responseData['msgList']) && is_array($responseData['msgList']) && !empty($responseData['msgList'])) {
            if (isset($responseData['msgList'][0]['msgText'])) {
                $errorMessage = $responseData['msgList'][0]['msgText'];
            } else {
                $errorMessage = json_encode($responseData['msgList'][0]);
            }
        } elseif (isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        } elseif (isset($responseData['error'])) {
            $errorMessage = $responseData['error'];
        } else {
            $errorMessage = 'Unknown error from API';
        }
        
        throw new Exception('API error: ' . $errorMessage);
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Store manual order in database
    $insertQuery = "INSERT INTO manual_orders (claim_id, document_no, order_data, api_response, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->execute([
        $claimId,
        $documentNo,
        json_encode($orderData),
        $response,
        'created',
        $_SESSION['user_id']
    ]);
    
    // Get the inserted order ID
    $orderId = $conn->lastInsertId();
    
    // Add a note to the claim with better formatting
    $noteQuery = "INSERT INTO claim_notes (claim_id, note, created_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($noteQuery);
    $stmt->execute([
        $claimId,
        "✅ Manual order created successfully\nDocument Number: " . $documentNo,
        $_SESSION['user_id']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Clear verified SKUs from session
    unset($_SESSION['verified_skus']);
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'message' => 'Manual order created successfully',
        'document_no' => $documentNo,
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if started
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log the exception
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
