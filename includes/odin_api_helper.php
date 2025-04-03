<?php
/**
 * ODIN API Helper
 * 
 * This file contains helper functions for interacting with the ODIN API
 * for order validation and warranty claims processing.
 */

// Include API configuration if not already included
if (!defined('ODIN_API_BASE_URL')) {
    require_once __DIR__ . '/../config/api_config.php';
}

/**
 * Get ODIN API authentication data (JSESSIONID and Authorization token)
 * 
 * @return array|bool Authentication data or false on failure
 */
function getOdinApiAuth() {
    // Create log directory if it doesn't exist
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log file path
    $logFile = $logDir . '/api_requests.log';
    
    // Log the request
    $logMessage = "[" . date('Y-m-d H:i:s') . "] API Request: getOdinApiAuth\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    try {
        // Initialize cURL session
        $curl = curl_init();
        
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => ODIN_API_LOGIN_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => ODIN_API_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'userNo' => ODIN_API_USER_NO,
                'userPassword' => ODIN_API_USER_PASSWORD
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_HEADER => true
        ));
        
        // Execute the request
        $response = curl_exec($curl);
        
        // Check for cURL errors
        if (curl_errno($curl)) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] cURL Error: " . curl_error($curl) . "\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            curl_close($curl);
            return false;
        }
        
        // Get header size and separate headers from body
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Close cURL session
        curl_close($curl);
        
        // Extract JSESSIONID from headers
        $jsessionid = '';
        if (preg_match('/JSESSIONID=([^;]+)/', $headers, $matches)) {
            $jsessionid = $matches[1];
        } else {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] JSESSIONID not found in response headers\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            return false;
        }
        
        // Parse response body
        $responseData = json_decode($body, true);
        
        // Log the response
        $responseLog = "[" . date('Y-m-d H:i:s') . "] API Response: getOdinApiAuth\n";
        $responseLog .= "Headers: " . $headers . "\n";
        $responseLog .= "Body: " . $body . "\n\n";
        file_put_contents($logFile, $responseLog, FILE_APPEND);
        
        // Check if login was successful
        if (!isset($responseData['success']) || $responseData['success'] !== true) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] Login failed: " . json_encode($responseData) . "\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            return false;
        }
        
        // Extract session information
        if (!isset($responseData['returnObject']['sessionId']) || !isset($responseData['returnObject']['sessionPassword'])) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] Missing session information in response\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            return false;
        }
        
        $sessionId = $responseData['returnObject']['sessionId'];
        $sessionPassword = $responseData['returnObject']['sessionPassword'];
        
        // Create authorization token
        $authToken = base64_encode($sessionId . ':' . $sessionPassword);
        
        // Return authentication data
        return [
            'jsessionid' => $jsessionid,
            'auth_token' => $authToken
        ];
        
    } catch (Exception $e) {
        // Log the exception
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] Exception in getOdinApiAuth: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        return false;
    }
}

/**
 * Get order details from ODIN API
 * 
 * @param string $orderId Order ID
 * @return array|bool Order details or false on failure
 */
function getOrderDetails($orderId) {
    // Create log directory if it doesn't exist
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log file path
    $logFile = $logDir . '/api_requests.log';
    
    // Log the request
    $logMessage = "[" . date('Y-m-d H:i:s') . "] API Request: getOrderDetails\n";
    $logMessage .= "Request: " . json_encode(['orderId' => $orderId], JSON_PRETTY_PRINT) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    try {
        // Get authentication data
        $authData = getOdinApiAuth();
        
        if (!$authData) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] Failed to get authentication data\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            return false;
        }
        
        // Initialize cURL session
        $curl = curl_init();
        
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => ODIN_API_ORDER_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => ODIN_API_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'storageClientNo' => ODIN_API_STORAGE_CLIENT_NO,
                'orderId' => $orderId
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: ' . $authData['auth_token'],
                'Cookie: JSESSIONID=' . $authData['jsessionid']
            )
        ));
        
        // Execute the request
        $response = curl_exec($curl);
        
        // Check for cURL errors
        if (curl_errno($curl)) {
            $errorMessage = "[" . date('Y-m-d H:i:s') . "] cURL Error: " . curl_error($curl) . "\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            curl_close($curl);
            return false;
        }
        
        // Close cURL session
        curl_close($curl);
        
        // Parse response
        $responseData = json_decode($response, true);
        
        // Log the response
        $responseLog = "[" . date('Y-m-d H:i:s') . "] API Response: getOrderDetails\n";
        $responseLog .= "Response: " . $response . "\n\n";
        file_put_contents($logFile, $responseLog, FILE_APPEND);
        
        // Process order details
        return processOrderDetails($responseData);
        
    } catch (Exception $e) {
        // Log the exception
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] Exception in getOrderDetails: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        return false;
    }
}

/**
 * Process order details from ODIN API response
 * 
 * @param array $responseData Raw API response data
 * @return array Processed order details with warranty information
 */
function processOrderDetails($responseData) {
    if (!isset($responseData['success']) || $responseData['success'] !== true) {
        return ['error' => 'API error: ' . ($responseData['msgList']['msgList'][0]['msgType'] ?? 'Unknown error')];
    }
    
    if (!isset($responseData['returnObject'])) {
        return ['error' => 'Order not found'];
    }
    
    $orderData = $responseData['returnObject'];
    
    // Convert timestamp to date with time
    $orderDate = isset($orderData['orderDate']) 
        ? date('Y-m-d H:i:s', $orderData['orderDate'] / 1000) 
        : date('Y-m-d H:i:s');
    
    // Get customer information from deliverToCustAddr
    $customerName = '';
    $customerEmail = '';
    $customerPhone = '';
    
    if (isset($orderData['deliverToCustAddr'])) {
        $customerName = $orderData['deliverToCustAddr']['customerDesc'] ?? '';
        $customerEmail = $orderData['deliverToCustAddr']['email'] ?? '';
        $customerPhone = $orderData['deliverToCustAddr']['telNo'] ?? '';
    }
    
    // Format the order data
    $formattedOrder = [
        'order_id' => $orderData['documentNo'] ?? '',
        'customer_order_no' => $orderData['custOrderNo'] ?? '',
        'order_date' => $orderDate,
        'order_date_display' => date('F j, Y, g:i A', strtotime($orderDate)), // Formatted date with AM/PM
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'items' => []
    ];
    
    // Process order items and check warranty
    if (isset($orderData['orderDetailViewList']) && is_array($orderData['orderDetailViewList'])) {
        foreach ($orderData['orderDetailViewList'] as $item) {
            $sku = $item['storageClientSkuNo'] ?? '';
            $productName = $item['skuDesc'] ?? '';
            $quantity = isset($item['orderQty']) ? (int)$item['orderQty'] : 1;
            
            // Determine product type and warranty period based on SKU
            $productType = determineProductType($sku);
            $warrantyPeriod = getWarrantyPeriod($productType);
            
            // Use only the date part for warranty calculations
            $orderDateOnly = substr($orderDate, 0, 10);
            $warrantyEndDate = calculateWarrantyEndDate($orderDateOnly, $warrantyPeriod);
            $isWarrantyValid = isWarrantyValid($warrantyEndDate);
            
            $formattedOrder['items'][] = [
                'sku' => $sku,
                'product_name' => $productName,
                'quantity' => $quantity,
                'product_type' => $productType,
                'warranty_period' => $warrantyPeriod,
                'warranty_end_date' => $warrantyEndDate,
                'is_warranty_valid' => $isWarrantyValid,
                'warranty_status' => $isWarrantyValid ? 'Valid' : 'Expired'
            ];
        }
    }
    
    return $formattedOrder;
}

/**
 * Determine product type based on SKU
 * 
 * @param string $sku Product SKU
 * @return string Product type
 */
function determineProductType($sku) {
    if (strpos($sku, 'TRH') === 0) {
        return 'TRAPO HEX';
    } elseif (strpos($sku, 'TRC') === 0) {
        return 'TRAPO CLASSIC';
    } elseif (strpos($sku, 'TRE') === 0) {
        return 'TRAPO ECO';
    } elseif (strpos($sku, 'OXWP') === 0) {
        return 'WIPER';
    } elseif (strpos($sku, 'TRHU') === 0) {
        return 'TRAPO HEX ULTIMATE';
    } elseif (strpos($sku, 'TR3D') === 0) {
        return 'TRAPO XTREME';
    } else {
        return 'OTHER';
    }
}

/**
 * Get warranty period in months based on product type from the warranty_rules table
 * 
 * @param string $productType Product type
 * @return int Warranty period in months
 */
function getWarrantyPeriod($productType) {
    // Create log directory if it doesn't exist
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log file path
    $logFile = $logDir . '/warranty_debug.log';
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDbConnection();
        
        // Log the product type being looked up
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Looking up warranty period for product type: " . $productType . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Query the warranty_rules table for the warranty period
        $stmt = $conn->prepare("SELECT duration FROM warranty_rules WHERE product_type = ?");
        $stmt->execute([$productType]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log the database result
        $resultLog = "[" . date('Y-m-d H:i:s') . "] Database result: " . json_encode($result) . "\n";
        file_put_contents($logFile, $resultLog, FILE_APPEND);
        
        // If found in the database, return the warranty period
        if ($result && isset($result['duration'])) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Found in database. Warranty period: " . $result['duration'] . " months\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            return (int)$result['duration'];
        }
        
        // If not found in the database, check if there's a rule for 'OTHER' product type
        if ($productType !== 'OTHER') {
            $stmt = $conn->prepare("SELECT duration FROM warranty_rules WHERE product_type = 'OTHER'");
            $stmt->execute();
            $otherResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otherResult && isset($otherResult['duration'])) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] Using 'OTHER' product type warranty period: " . $otherResult['duration'] . " months\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                return (int)$otherResult['duration'];
            }
        }
        
        // If not found in the database and no 'OTHER' rule, return 0 (no warranty)
        $logMessage = "[" . date('Y-m-d H:i:s') . "] No warranty rule found in database. Returning 0 months.\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return 0;
        
    } catch (Exception $e) {
        // Log the error
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] Error getting warranty period from database: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMessage, FILE_APPEND);
        
        // Return 0 when there's an error (no default warranty)
        return 0;
    }
}

/**
 * Calculate warranty end date based on order date and warranty period
 * 
 * @param string $orderDate Order date (Y-m-d)
 * @param int $warrantyPeriod Warranty period in months
 * @return string Warranty end date (Y-m-d)
 */
function calculateWarrantyEndDate($orderDate, $warrantyPeriod) {
    $date = new DateTime($orderDate);
    $date->modify("+{$warrantyPeriod} months");
    return $date->format('Y-m-d');
}

/**
 * Check if warranty is still valid
 * 
 * @param string $warrantyEndDate Warranty end date (Y-m-d)
 * @return bool True if warranty is valid, false otherwise
 */
function isWarrantyValid($warrantyEndDate) {
    $today = new DateTime();
    $endDate = new DateTime($warrantyEndDate);
    return $today <= $endDate;
}

/**
 * Log API request details for debugging
 * 
 * @param string $endpoint API endpoint
 * @param array $requestData Request data
 * @param mixed $responseData Response data
 * @return void
 */
function logApiRequest($endpoint, $requestData, $responseData) {
    $logDir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/api_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Format log message
    $logMessage = "[$timestamp] API Request: $endpoint\n";
    $logMessage .= "Request: " . json_encode($requestData, JSON_PRETTY_PRINT) . "\n";
    
    // Handle different response types
    if (is_string($responseData)) {
        $logMessage .= "Response: " . $responseData . "\n\n";
    } else {
        $logMessage .= "Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";
    }
    
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>
