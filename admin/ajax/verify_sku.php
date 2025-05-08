<?php
/**
 * AJAX handler for SKU verification
 * This file processes AJAX requests for SKU verification and returns JSON responses
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
if (!isset($input['sku']) || empty($input['sku'])) {
    echo json_encode([
        'success' => false,
        'message' => 'SKU is required'
    ]);
    exit;
}

$sku = trim($input['sku']);

// Create log directory if it doesn't exist
$logDir = '../../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log file path
$logFile = $logDir . '/api_requests.log';

// Log the request
$logMessage = "[" . date('Y-m-d H:i:s') . "] SKU verification request: " . $sku . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

try {
    // API URL for SKU verification
    $apiUrl = ODIN_API_BASE_URL . '/InvEntity/doQueryStorageClientInventoryPage';
    
    // Prepare request data as direct JSON string (matching Postman)
    $requestJson = '{
"storageClientInventoryQuery" : {
"storageClientNo" : "' . ODIN_API_STORAGE_CLIENT_NO . '",
"country" : "MALAYSIA",
"storageClientSkuNo" : "' . $sku . '",
"skuStatus" : "ACTIVE"
},
"pageData" : {
"currentLength" : 1,
"currentOffset" : 0
}
}';
    
    // Log the request data
    $requestLog = "[" . date('Y-m-d H:i:s') . "] SKU verification API request data: " . $requestJson . "\n";
    file_put_contents($logFile, $requestLog, FILE_APPEND);
    
    // Initialize cURL session
    $curl = curl_init();
    
    // Set cURL options - exactly matching your working Postman example
    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,  // No timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $requestJson,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Cookie: JSESSIONID=f2584d47dcf24cefb72ba3a5a2f3'
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
        curl_close($curl);
        throw new Exception('API connection error: ' . curl_error($curl));
    }
    
    // Get HTTP status code
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($curl);
    
    // Log the response
    $responseLog = "[" . date('Y-m-d H:i:s') . "] SKU verification API response: " . $response . "\n";
    file_put_contents($logFile, $responseLog, FILE_APPEND);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Log the raw response for debugging
    $responseLog = "[" . date('Y-m-d H:i:s') . "] Raw API Response: " . $response . "\n";
    file_put_contents($logFile, $responseLog, FILE_APPEND);
    
    // Check if response is valid
    if ($httpCode != 200) {
        throw new Exception('API error: HTTP code ' . $httpCode);
    }
    
    if (!isset($responseData['success']) || $responseData['success'] !== true) {
        $errorMessage = isset($responseData['msgList']) && isset($responseData['msgList'][0]) && isset($responseData['msgList'][0]['msgText']) 
            ? $responseData['msgList'][0]['msgText'] 
            : 'Invalid response from API';
        
        throw new Exception('API error: ' . $errorMessage);
    }
    
    // Check if SKU was found
    if (!isset($responseData['returnObject']) || 
        !isset($responseData['returnObject']['currentPageData']) || 
        empty($responseData['returnObject']['currentPageData'])) {
        throw new Exception('SKU not found in inventory');
    }
    
    // Get SKU data from response
    $skuData = $responseData['returnObject']['currentPageData'][0];
    
    // Return successful response with SKU data
    echo json_encode([
        'success' => true,
        'message' => 'SKU verified successfully',
        'data' => [
            'storageClientNo' => $skuData['storageClientNo'] ?? 'BOT1545',
            'storageClientSkuNo' => $skuData['storageClientSkuNo'] ?? $sku,
            'country' => $skuData['country'] ?? 'MALAYSIA',
            'skuDesc' => $skuData['skuDesc'] ?? '',
            'skuStatus' => $skuData['skuStatus'] ?? 'ACTIVE',
            'availableQty' => $skuData['availableQty'] ?? 0
        ],
        'raw_response' => $responseData // Include raw response for debugging
    ]);
    
} catch (Exception $e) {
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
