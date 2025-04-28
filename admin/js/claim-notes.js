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
        if (!isSubmittingNote) {
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
        const submitBtn = modal.find('button[type="submit"]');
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
                
                // Hide modal
                modal.modal('hide');
                
                // Reset form
                $('#addNoteForm')[0].reset();
                $('#taggedUsersPreview').hide();
                
                // Show success message
                if (response.success) {
                    // Show success message
                    showAlert('success', response.message);
                    
                    // Handle the note display - either add to table or reload page
                    // but never both to avoid duplicates
                    if ($('.notes-table').length) {
                        // Add to existing table without page reload
                        addNoteToTable(response.note);
                    } else {
                        // No table exists, reload page to show the notes section
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
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
        const notesTable = $('.notes-table tbody');
        const rowCount = notesTable.find('tr').length;
        
        // Create new row
        const newRow = $('<tr>');
        if (note.status_changed === 'yes') {
            newRow.addClass('table-info');
        }
        
        // Add cells
        newRow.append($('<td>').text(rowCount + 1));
        newRow.append($('<td>').html(note.note.replace(/\n/g, '<br>')));
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
