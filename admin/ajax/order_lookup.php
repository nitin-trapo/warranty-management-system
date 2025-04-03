<?php
/**
 * AJAX handler for order lookup
 * This file processes AJAX requests for order lookup and returns JSON responses
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
if (!isset($input['order_id']) || empty($input['order_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

$orderId = trim($input['order_id']);

// Create log directory if it doesn't exist
$logDir = '../../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log file path
$logFile = $logDir . '/api_requests.log';

// Log the request
$logMessage = "[" . date('Y-m-d H:i:s') . "] Order lookup request: " . $orderId . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

try {
    // Get order details from ODIN API
    $orderDetails = getOrderDetails($orderId);
    
    if (!$orderDetails || isset($orderDetails['error'])) {
        throw new Exception(isset($orderDetails['error']) ? $orderDetails['error'] : 'Failed to retrieve order details');
    }
    
    // Generate HTML for item selection
    $itemsHtml = '';
    if (!empty($orderDetails['items'])) {
        $itemsHtml .= '<div class="row">'; // Start row for items
        
        $itemCount = 0; // Track valid items count
        
        foreach ($orderDetails['items'] as $index => $item) {
            // Skip items with SKU 'pickup-in-store'
            if (strtolower($item['sku']) === 'pickup-in-store') {
                continue;
            }
            
            $disabled = !$item['is_warranty_valid'] ? 'disabled' : '';
            $warningText = !$item['is_warranty_valid'] ? 'Expired' : 'Valid';
            $borderClass = !$item['is_warranty_valid'] ? 'border-danger' : 'border-success';
            $textClass = !$item['is_warranty_valid'] ? 'text-danger' : 'text-success';
            
            // Each item takes up 4 columns (3 items per row in a 12-column grid)
            $itemsHtml .= '<div class="col-md-4 mb-3">';
            $itemsHtml .= '<div class="card h-100 shadow-sm ' . $borderClass . '" style="border-width: 1px;">';
            $itemsHtml .= '<div class="card-body py-3 px-3">';
            
            // Radio button and title in one row
            $itemsHtml .= '<div class="d-flex align-items-center mb-2">';
            $itemsHtml .= '<div class="custom-control custom-radio mr-3">';
            $itemsHtml .= '<input type="radio" class="custom-control-input" name="claim_items" value="' . $index . '" id="item_' . $index . '" ' . $disabled . '>';
            $itemsHtml .= '<label class="custom-control-label" for="item_' . $index . '"></label>';
            $itemsHtml .= '</div>';
            $itemsHtml .= '<label for="item_' . $index . '" class="mb-0 text-truncate font-weight-bold pl-2 cursor-pointer" style="max-width: 85%; cursor: pointer;padding-left: 5px;font-weight:700" title="' . htmlspecialchars($item['product_name']) . '">' . htmlspecialchars($item['product_name']) . '</label>';
            $itemsHtml .= '</div>';
            
            // Item details
            $itemsHtml .= '<div class="small mb-2 ml-4">';
            $itemsHtml .= '<p class="mb-1"><strong>SKU:</strong> ' . htmlspecialchars($item['sku']) . '</p>';
            $itemsHtml .= '<p class="mb-1"><strong>Type:</strong> ' . htmlspecialchars($item['product_type']) . '</p>';
            $itemsHtml .= '<p class="mb-1"><strong>Warranty:</strong> ' . $item['warranty_period'] . ' months</p>';
            $itemsHtml .= '<p class="mb-1"><strong>Valid Until:</strong> ' . $item['warranty_end_date'] . '</p>';
            $itemsHtml .= '</div>';
            
            // Warranty status at the bottom
            $itemsHtml .= '<div class="text-right">';
            $itemsHtml .= '<span class="' . $textClass . ' font-weight-bold">Warranty: ' . $warningText . '</span>';
            $itemsHtml .= '</div>';
            
            $itemsHtml .= '</div>'; // End card-body
            $itemsHtml .= '</div>'; // End card
            $itemsHtml .= '</div>'; // End column
        }
        
        // Fill remaining columns if needed to maintain grid layout
        $remainingCols = 3 - (count($orderDetails['items']) % 3);
        if (count($orderDetails['items']) > 0 && $remainingCols < 3) {
            for ($i = 0; $i < $remainingCols; $i++) {
                $itemsHtml .= '<div class="col-md-4 mb-3"></div>';
            }
        }
        
        $itemsHtml .= '</div>'; // End row for items
        
        // Small note at the bottom
        $itemsHtml .= '<div class="text-muted small mt-2 mb-3">';
        $itemsHtml .= '<i class="fa fa-info-circle"></i> Please select one item for warranty claim processing.';
        $itemsHtml .= '</div>';
    }
    
    // Return successful response with order details
    echo json_encode([
        'success' => true,
        'message' => 'Order details retrieved successfully',
        'order' => $orderDetails,
        'items_html' => $itemsHtml,
        'raw_response' => json_encode($orderDetails)
    ]);
    
} catch (Exception $e) {
    // Log the exception
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
