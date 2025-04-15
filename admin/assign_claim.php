<?php
/**
 * Assign Claim
 * 
 * This file allows administrators to assign warranty claims to CS agents.
 */

// Set page title
$pageTitle = 'Assign Claim';

// Include required files
require_once '../includes/auth_helper.php';
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Enforce admin-only access
enforceAdminOnly();

// Include header
require_once 'includes/header.php';

// Establish database connection
$conn = getDbConnection();

// Get all active CS agents
try {
    $stmt = $conn->prepare("
        SELECT id, username, first_name, last_name
        FROM users
        WHERE role = 'cs_agent' AND status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $csAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Error retrieving CS agents: " . $e->getMessage();
    $csAgents = [];
}
?>

<div class="page-title">
    <h1>Assign Claim</h1>
    <div class="button-container">
        <a href="claims.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Claims
        </a>
    </div>
</div>

<!-- Debug Info (Remove in production) -->
<div class="alert alert-info mb-4" id="debugInfo">
    <h5>Debug Information</h5>
    <p>This section helps diagnose any issues with claim assignment.</p>
    <div id="debugOutput">Loading debug information...</div>
</div>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header py-3">
                <h6 class="mb-0">Assign Claim to CS Agent</h6>
            </div>
            <div class="card-body">
                <div id="assignClaimAlert" class="alert" style="display: none;"></div>
                
                <form id="assignClaimForm">
                    <div class="mb-3">
                        <label for="claim_id" class="form-label">Claim Reference Number</label>
                        <input type="text" class="form-control" id="claim_id" name="claim_id" required>
                        <div class="form-text">Enter the claim's unique reference number (e.g. #123 or external claim number)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="agent_id" class="form-label">Select CS Agent</label>
                        <select class="form-select" id="agent_id" name="agent_id" required>
                            <option value="">-- Select Agent --</option>
                            <?php foreach ($csAgents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>">
                                <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name'] . ' (' . $agent['username'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Assignment Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="assignClaimBtn">
                            <i class="fas fa-user-check me-1"></i> Assign Claim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Handle form submission
        $('#assignClaimForm').on('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const claimId = $('#claim_id').val().trim();
            const agentId = $('#agent_id').val();
            const notes = $('#notes').val().trim();
            
            // Validate form
            if (!claimId || !agentId) {
                showAlert('danger', 'Please fill in all required fields');
                return;
            }
            
            // Disable button and show loading state
            const assignBtn = $('#assignClaimBtn');
            const originalBtnText = assignBtn.html();
            assignBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Assigning...');
            assignBtn.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: 'ajax/assign_claim.php',
                type: 'POST',
                data: {
                    claim_id: claimId,
                    agent_id: agentId,
                    notes: notes
                },
                dataType: 'json',
                success: function(response) {
                    // Reset button
                    assignBtn.html(originalBtnText);
                    assignBtn.prop('disabled', false);
                    
                    if (response.success) {
                        // Show success message
                        showAlert('success', response.message);
                        
                        // Reset form
                        $('#assignClaimForm')[0].reset();
                    } else {
                        // Show error message
                        showAlert('danger', response.message || 'An error occurred while assigning the claim.');
                    }
                },
                error: function(xhr, status, error) {
                    // Reset button
                    assignBtn.html(originalBtnText);
                    assignBtn.prop('disabled', false);
                    
                    // Show error message
                    let errorMsg = 'An error occurred while assigning the claim. Please try again.';
                    
                    // Try to get more specific error message
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        console.error('Error parsing error response:', e);
                    }
                    
                    showAlert('danger', errorMsg);
                    console.error('AJAX Error:', error, xhr.responseText);
                }
            });
        });
        
        // Function to show alert
        function showAlert(type, message) {
            const alertEl = $('#assignClaimAlert');
            alertEl.removeClass('alert-success alert-danger alert-warning alert-info')
                  .addClass('alert-' + type)
                  .html(message)
                  .show();
            
            // Scroll to alert
            $('html, body').animate({
                scrollTop: alertEl.offset().top - 100
            }, 200);
            
            // Add console log for debugging
            console.log('Alert shown:', { type, message });
        }
    });
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
