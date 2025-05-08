/**
 * Claim Notes and Status Management
 * 
 * This file handles AJAX functionality for claim notes and status updates
 * Includes protection against duplicate form submissions
 */

$(document).ready(function() {
    // Global submission flags to prevent duplicates
    let isSubmittingStatus = false;
    let isSubmittingNote = false;
    let noteSubmissionId = null;
    
    // ====== STATUS UPDATE HANDLING ======
    
    // Handle status update form submission
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        if (!isSubmittingStatus) {
            updateClaimStatus();
        }
    });
    
    // Handle the update status button click
    $('.update-status-btn').on('click', function(e) {
        e.preventDefault();
        if (!isSubmittingStatus) {
            updateClaimStatus();
        }
    });
    
    function updateClaimStatus() {
        // Set flag to prevent duplicate submissions
        isSubmittingStatus = true;
        
        const formData = $('#updateStatusForm').serialize();
        const claimId = $('#updateStatusForm input[name="claim_id"]').val();
        const modal = $('#updateStatusModal');
        
        // Show loading state
        const submitBtn = modal.find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'ajax/update_claim_status_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                // Hide modal
                modal.modal('hide');
                
                // Reset form
                $('#updateStatusForm')[0].reset();
                
                // Show success message
                if (response.success) {
                    // Show success message
                    showAlert('success', response.message);
                    
                    // Refresh the page after a short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1000); // 1 second delay to show the success message
                } else {
                    // Show error message
                    showAlert('danger', response.message || 'An error occurred while updating the status.');
                    
                    // Reset submission flag on error
                    isSubmittingStatus = false;
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                console.log("Response:", xhr.responseText);
                
                // Show error message
                showAlert('danger', 'An error occurred while updating the status. Please try again.');
                
                // Reset submission flag on error
                isSubmittingStatus = false;
            },
            complete: function() {
                // Reset button state
                submitBtn.html(originalBtnText);
                submitBtn.prop('disabled', false);
            }
        });
    }
    
    // ====== NOTE ADDITION HANDLING ======
    
    // Handle add note form submission
    $('#addNoteForm').on('submit', function(e) {
        e.preventDefault();
        if (!isSubmittingNote) {
            addClaimNote();
        }
    });
    
    // Handle the add note button click
    $('.add-note-btn').on('click', function(e) {
        e.preventDefault();
        console.log('Add Note button clicked');
        if (!isSubmittingNote) {
            // Call addClaimNote directly instead of submitting the form
            addClaimNote();
        }
    });
    
    function addClaimNote() {
        // Set flag to prevent duplicate submissions
        isSubmittingNote = true;
        
        // Generate a unique submission ID
        noteSubmissionId = Date.now() + Math.floor(Math.random() * 1000);
        const currentSubmissionId = noteSubmissionId;
        
        const formData = $('#addNoteForm').serialize();
        const modal = $('#addNoteModal');
        
        // Show loading state
        const submitBtn = modal.find('.add-note-btn');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'ajax/add_claim_note.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                // Only process if this is still the current submission
                // This prevents race conditions with multiple clicks
                if (currentSubmissionId !== noteSubmissionId) {
                    console.log('Ignoring outdated response');
                    return;
                }
                
                console.log('AJAX Response:', response);
                
                // Reset submission flag
                isSubmittingNote = false;
                
                // Extensive debugging of the response
                console.log('AJAX SUCCESS RESPONSE:', response);
                if (response.note && response.note.formatted_note) {
                    console.log('Formatted note from server:', response.note.formatted_note);
                }
                
                // Hide modal
                modal.modal('hide');
                
                // Reset form
                $('#addNoteForm')[0].reset();
                $('#taggedUsersPreview').hide();
                
                // Process response
                if (response.success) {
                    // Show success message
                    showAlert('success', 'Note added successfully! Refreshing page...');
                    console.log('Note added successfully, reloading page for proper formatting...');
                    
                    // FORCE PAGE RELOAD: This is the most reliable way to ensure proper formatting
                    setTimeout(function() {
                        // Get the current URL and claim ID
                        var claimId = $('#addNoteForm input[name="claim_id"]').val();
                        var currentUrl = window.location.pathname;
                        var timestamp = new Date().getTime();
                        
                        // Construct the URL with the claim ID and a timestamp to prevent caching
                        var reloadUrl = currentUrl + '?id=' + claimId + '&t=' + timestamp;
                        
                        console.log('Reloading page with URL:', reloadUrl);
                        
                        // Use location.href for a complete page reload
                        window.location.href = reloadUrl;
                    }, 1000); // Short delay to show the success message
                } else {
                    // Show error message
                    showAlert('danger', response.message || 'An error occurred while adding the note.');
                    
                    // Reset submission flag on error
                    isSubmittingNote = false;
                }
            },
            error: function(xhr, status, error) {
                // Only process if this is still the current submission
                if (currentSubmissionId !== noteSubmissionId) {
                    console.log('Ignoring outdated error');
                    return;
                }
                
                console.error("AJAX Error:", status, error);
                console.log("Response:", xhr.responseText);
                
                // Show error message
                showAlert('danger', 'An error occurred while adding the note. Please try again.');
                
                // Reset submission flag on error
                isSubmittingNote = false;
            },
            complete: function() {
                // Only process if this is still the current submission
                if (currentSubmissionId !== noteSubmissionId) {
                    console.log('Ignoring outdated completion');
                    return;
                }
                
                // Reset button state
                submitBtn.html(originalBtnText);
                submitBtn.prop('disabled', false);
                
                // Reset submission flag after a short delay
                setTimeout(function() {
                    if (currentSubmissionId === noteSubmissionId) {
                        isSubmittingNote = false;
                    }
                }, 1000);
            }
        });
    }
    
    // Function to update status badge in the UI
    function updateStatusBadge(status) {
        const statusText = status.replace('_', ' ');
        const statusClass = getStatusClass(status);
        
        // Update status badge if it exists
        if ($('.claim-status-badge').length) {
            $('.claim-status-badge').removeClass().addClass('badge claim-status-badge ' + statusClass).text(statusText);
        }
        
        // Update status in the form if it exists
        if ($('select[name="status"]').length) {
            $('select[name="status"]').val(status);
        }
    }
    
    // Function to get status badge class
    function getStatusClass(status) {
        switch (status) {
            case 'new':
                return 'bg-primary';
            case 'in_progress':
                return 'bg-info';
            case 'on_hold':
                return 'bg-warning';
            case 'approved':
                return 'bg-success';
            case 'rejected':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
    
    // Function to add a new note to the notes table
    function addNoteToTable(note) {
        console.log('Adding note to table:', note);
        
        const notesTable = $('.notes-table tbody');
        const rowCount = notesTable.find('tr').length;
        
        // Create new row
        const newRow = $('<tr>');
        if (note.status_changed === 'yes') {
            newRow.addClass('table-info');
        }
        
        // Add cells
        newRow.append($('<td>').text(rowCount + 1));
        
        // Format the note text to properly highlight tagged users
        let noteText;
        
        // Check if the server provided a formatted note
        if (note.formatted_note) {
            console.log('Using server-formatted note:', note.formatted_note);
            // Use the pre-formatted note directly without any processing
            noteText = note.formatted_note;
        } else {
            console.log('Formatting note client-side from:', note.note);
            // Format the note client-side
            noteText = note.note;
            // Escape HTML to prevent XSS
            noteText = $('<div>').text(noteText).html();
            console.log('After HTML escaping:', noteText);
            
            // Replace @username with highlighted badge - EXACT format as requested
            noteText = noteText.replace(/@([a-zA-Z0-9._]+)/g, '<span class="badge bg-info text-dark">@$1</span>');
            console.log('After highlighting tags:', noteText);
            
            // Convert line breaks to <br>
            noteText = noteText.replace(/\n/g, '<br>');
            console.log('Final formatted note:', noteText);
        }
        
        // Create a temporary div to hold the note text
        const noteCell = $('<td>');
        
        // Set the HTML content
        noteCell.html(noteText);
        
        // Add the cell to the row
        newRow.append(noteCell);
        newRow.append($('<td>').text(note.created_by_name || 'System'));
        newRow.append($('<td>').text(formatDate(note.created_at)));
        
        // Add row to table
        notesTable.prepend(newRow);
        
        // Update row numbers
        updateRowNumbers();
    }
    
    // Function to update row numbers in the notes table
    function updateRowNumbers() {
        $('.notes-table tbody tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    // Function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        };
        return date.toLocaleDateString('en-US', options);
    }
    
    // Function to show alert message
    function showAlert(type, message) {
        const alertDiv = $('<div>').addClass('alert alert-' + type + ' alert-dismissible fade show')
            .attr('role', 'alert')
            .html(message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
        
        // Add alert to the page
        $('.card-body').first().prepend(alertDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            alertDiv.alert('close');
        }, 5000);
    }
});
