/**
 * Warranty Management System
 * File Upload Validation
 */

document.addEventListener('DOMContentLoaded', function() {
    // Constants for file size limits
    const MAX_PHOTO_SIZE = 2 * 1024 * 1024; // 2MB in bytes
    const MAX_VIDEO_SIZE = 10 * 1024 * 1024; // 10MB in bytes
    
    // Allowed video formats
    const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'mov'];
    
    // Get file input elements
    const photoInput = document.getElementById('photos');
    const videoInput = document.getElementById('videos');
    
    // Get error container
    const errorContainer = document.getElementById('file-upload-errors');
    
    // Add validation to photo input
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            validateFiles(this, MAX_PHOTO_SIZE, null, 'photo');
        });
    }
    
    // Add validation to video input
    if (videoInput) {
        videoInput.addEventListener('change', function() {
            validateFiles(this, MAX_VIDEO_SIZE, ALLOWED_VIDEO_EXTENSIONS, 'video');
        });
    }
    
    // Form submission handler
    const claimForm = document.getElementById('claimForm');
    if (claimForm) {
        claimForm.addEventListener('submit', function(e) {
            // Clear previous errors
            if (errorContainer) {
                errorContainer.innerHTML = '';
                errorContainer.style.display = 'none';
            }
            
            // Ensure customer information is set
            const customerName = document.getElementById('customer_name_input');
            const customerEmail = document.getElementById('customer_email_input');
            const customerPhone = document.getElementById('customer_phone_input');
            
            // Try to get customer info from the order lookup response
            try {
                // If we have the data in the window object from the AJAX response
                if (window.orderData && window.orderData.order) {
                    if (!customerName.value && window.orderData.order.customer_name) {
                        customerName.value = window.orderData.order.customer_name;
                    }
                    if (!customerEmail.value && window.orderData.order.customer_email) {
                        customerEmail.value = window.orderData.order.customer_email;
                    }
                    if (!customerPhone.value && window.orderData.order.customer_phone) {
                        customerPhone.value = window.orderData.order.customer_phone;
                    }
                }
            } catch (err) {
                console.error('Error accessing order data:', err);
            }
            
            // Log customer data for debugging
            console.log('Customer data before submission:', {
                name: customerName ? customerName.value : 'not found',
                email: customerEmail ? customerEmail.value : 'not found',
                phone: customerPhone ? customerPhone.value : 'not found'
            });
            
            // Validate customer information
            let customerErrors = [];
            if (!customerName || !customerName.value) {
                customerErrors.push('Customer name is required');
            }
            
            if (!customerEmail || !customerEmail.value) {
                customerErrors.push('Customer email is required');
            }
            
            // Display customer errors if any
            if (customerErrors.length > 0) {
                e.preventDefault();
                
                // Display errors in the modal
                if (errorContainer) {
                    errorContainer.innerHTML = customerErrors.map(error => `<div>${error}</div>`).join('');
                    errorContainer.style.display = 'block';
                    
                    // Scroll to error container
                    errorContainer.scrollIntoView({ behavior: 'smooth' });
                }
                
                return false;
            }
            
            // Validate photos
            let photoErrors = [];
            if (photoInput && photoInput.files.length > 0) {
                photoErrors = validateFilesWithoutUI(photoInput, MAX_PHOTO_SIZE, null, 'photo');
            }
            
            // Validate videos
            let videoErrors = [];
            if (videoInput && videoInput.files.length > 0) {
                videoErrors = validateFilesWithoutUI(videoInput, MAX_VIDEO_SIZE, ALLOWED_VIDEO_EXTENSIONS, 'video');
            }
            
            // Combine errors
            const allErrors = [...customerErrors, ...photoErrors, ...videoErrors];
            
            // If there are errors, prevent form submission and display errors
            if (allErrors.length > 0) {
                e.preventDefault();
                
                // Display errors in the modal
                if (errorContainer) {
                    errorContainer.innerHTML = allErrors.map(error => `<div>${error}</div>`).join('');
                    errorContainer.style.display = 'block';
                    
                    // Scroll to error container
                    errorContainer.scrollIntoView({ behavior: 'smooth' });
                }
                
                return false;
            }
            
            // If using AJAX submission, prevent default form submission
            if (window.useAjaxSubmission) {
                e.preventDefault();
                submitFormViaAjax();
            }
        });
    }
    
    // Function to validate files and update UI
    function validateFiles(fileInput, maxSize, allowedExtensions, fileType) {
        const errors = validateFilesWithoutUI(fileInput, maxSize, allowedExtensions, fileType);
        
        // Display errors if any
        if (errors.length > 0) {
            // Clear the file input
            fileInput.value = '';
            
            // Show errors in the error container
            if (errorContainer) {
                errorContainer.innerHTML = errors.map(error => `<div>${error}</div>`).join('');
                errorContainer.style.display = 'block';
            } else {
                // Fallback to alert if error container doesn't exist
                alert(errors.join('\n'));
            }
            
            return false;
        } else if (errorContainer) {
            // Clear any previous errors
            errorContainer.innerHTML = '';
            errorContainer.style.display = 'none';
        }
        
        return true;
    }
    
    // Function to validate files without updating UI (for form submission)
    function validateFilesWithoutUI(fileInput, maxSize, allowedExtensions, fileType) {
        const errors = [];
        const files = fileInput.files;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileName = file.name;
            const fileSize = file.size;
            const fileExtension = fileName.split('.').pop().toLowerCase();
            
            // Check file size
            if (fileSize > maxSize) {
                const maxSizeMB = maxSize / (1024 * 1024);
                errors.push(`${fileType === 'photo' ? 'Photo' : 'Video'} "${fileName}" exceeds the maximum size limit of ${maxSizeMB}MB.`);
            }
            
            // Check file extension for videos
            if (fileType === 'video' && allowedExtensions && !allowedExtensions.includes(fileExtension)) {
                errors.push(`Video "${fileName}" is not in an allowed format (${allowedExtensions.join(', ')}).`);
            }
        }
        
        return errors;
    }
    
    // Function to submit form via AJAX
    function submitFormViaAjax() {
        const form = document.getElementById('claimForm');
        const formData = new FormData(form);
        
        // Show loading indicator
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
        
        // Clear previous errors
        if (errorContainer) {
            errorContainer.innerHTML = '';
            errorContainer.style.display = 'none';
        }
        
        fetch('ajax/submit_claim.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin' // Include cookies for session handling
        })
        .then(response => response.json())
        .then(data => {
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            if (data.success) {
                // Show success message
                const modal = bootstrap.Modal.getInstance(document.getElementById('addClaimModal'));
                modal.hide();
                
                // Add success alert to the page
                const alertContainer = document.createElement('div');
                alertContainer.className = 'alert alert-success alert-dismissible fade show';
                alertContainer.setAttribute('role', 'alert');
                alertContainer.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insert alert before the claims table
                const claimsCard = document.querySelector('.card');
                claimsCard.parentNode.insertBefore(alertContainer, claimsCard);
                
                // Reload claims table or add the new claim to the table
                if (data.claim) {
                    addClaimToTable(data.claim);
                } else {
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                // Show error message in the modal
                if (errorContainer) {
                    errorContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    errorContainer.style.display = 'block';
                    
                    // Scroll to error container
                    errorContainer.scrollIntoView({ behavior: 'smooth' });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            // Show error message
            if (errorContainer) {
                errorContainer.innerHTML = '<div class="alert alert-danger">An error occurred while submitting the claim. Please try again.</div>';
                errorContainer.style.display = 'block';
            }
        });
    }
    
    // Function to add a new claim to the table without reloading
    function addClaimToTable(claim) {
        const claimsTable = document.querySelector('#claimsTable tbody');
        
        // If there's a "No claims found" row, remove it
        const noClaimsRow = claimsTable.querySelector('tr td[colspan="7"]');
        if (noClaimsRow) {
            noClaimsRow.parentNode.remove();
        }
        
        // Create a new row for the claim
        const newRow = document.createElement('tr');
        
        // Format the status badge
        let statusClass = 'secondary';
        switch (claim.status) {
            case 'new': statusClass = 'info'; break;
            case 'in_progress': statusClass = 'primary'; break;
            case 'on_hold': statusClass = 'warning'; break;
            case 'approved': statusClass = 'success'; break;
            case 'rejected': statusClass = 'danger'; break;
        }
        
        // Format the date
        const createdDate = new Date(claim.created_at);
        const formattedDate = createdDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
        
        // Set the row HTML
        newRow.innerHTML = `
            <td>#${claim.id}</td>
            <td>${claim.order_id}</td>
            <td>${claim.customer_name}</td>
            <td>${claim.category_name || 'N/A'}</td>
            <td>
                <span class="badge bg-${statusClass}">
                    ${claim.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </span>
            </td>
            <td>${formattedDate}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a href="view_claim.php?id=${claim.id}" class="btn btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-outline-secondary update-status" 
                            data-id="${claim.id}"
                            data-status="${claim.status}">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Add the new row to the top of the table
        claimsTable.insertBefore(newRow, claimsTable.firstChild);
    }
});
