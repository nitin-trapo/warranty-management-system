/**
 * Warranty Management System
 * Claim Submission JavaScript
 */

// Global function to handle checkbox clicks - must be accessible from inline handlers
function itemCheckboxClicked(checkbox) {
    console.log('Checkbox clicked via inline handler:', checkbox.id, 'Checked:', checkbox.checked);
    
    // Highlight the selected item card
    const card = checkbox.closest('.card');
    if (card) {
        if (checkbox.checked) {
            card.classList.add('border-primary');
            card.classList.remove('border-success', 'border-danger');
        } else {
            // Restore original border
            if (card.classList.contains('border-danger')) {
                card.classList.add('border-danger');
            } else {
                card.classList.add('border-success');
            }
            card.classList.remove('border-primary');
        }
    }
    
    // Validate selection and generate forms
    validateItemSelection();
    generateItemForms();
}

// Global function to generate item forms - must be accessible from inline handlers
function generateItemForms() {
    console.log('Generating item forms for selected items');
    
    // Clear any existing form containers first
    const itemsContainer = document.getElementById('selected_items_container');
    if (!itemsContainer) {
        console.error('Selected items container not found!');
        return;
    }
    
    // Clear the container
    itemsContainer.innerHTML = '';
    
    // Get selected items
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    console.log('Selected items count:', selectedItems.length);
    
    if (selectedItems.length === 0) {
        // Show a message when no items are selected
        itemsContainer.innerHTML = '<div class="alert alert-warning mt-3">Please select at least one item to generate claim details.</div>';
        return;
    }
    
    // Get categories from PHP variable passed to window object
    const categories = window.claimCategories || [];
    
    // Get order number for claim number generation
    const orderId = document.getElementById('claim_order_id').value;
    // Extract numeric part from order ID (e.g., TMR-O335533 -> 335533)
    const orderMatch = orderId.match(/[A-Za-z]?(\d+)/);
    const orderNum = orderMatch && orderMatch[1] ? orderMatch[1] : '';
    
    console.log('Order ID:', orderId, 'Extracted order number:', orderNum);
    
    // For each selected item, create a form section
    selectedItems.forEach(function(item, index) {
        // Get data attributes
        const sku = item.getAttribute('data-sku');
        const productName = item.getAttribute('data-product-name');
        const productType = item.getAttribute('data-product-type');
        
        // Generate a claim number
        const claimNum = `CLAIM-${orderNum}-${sku.substring(0, 4).toUpperCase()}`;
        
        // Create form section
        const formSection = document.createElement('div');
        formSection.className = 'item-form bg-light p-3 mb-3 border rounded';
        
        formSection.innerHTML = `
            <h5 class="border-bottom pb-2 mb-3 text-primary">Item: ${productName}</h5>
            <input type="hidden" name="item_sku[]" value="${sku}">
            <input type="hidden" name="item_product_name[]" value="${productName}">
            <input type="hidden" name="item_product_type[]" value="${productType}">
            <input type="hidden" name="claim_number[]" value="${claimNum}">
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <p><strong>SKU:</strong> ${sku} <span class="mx-3">|</span> <strong>Product Type:</strong> ${productType || 'N/A'}</p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Claim Category</label>
                    <select class="form-select" name="category_id[]" required>
                        <option value="">Select Category</option>
                        ${categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold">Description</label>
                    <textarea class="form-control" name="description[]" rows="3" required placeholder="Describe the issue..."></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Photos</label>
                    <input type="file" class="form-control" name="photos_${index}[]" multiple accept="image/*">
                    <div class="form-text">Max size: 2MB per image</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Videos</label>
                    <input type="file" class="form-control" name="videos_${index}[]" multiple accept="video/mp4,video/quicktime">
                    <div class="form-text">Max size: 10MB (MP4/MOV only)</div>
                </div>
            </div>
        `;
        
        // Add to container
        itemsContainer.appendChild(formSection);
    });
    
    // Add delivery date hidden field
    const orderData = window.orderData || {};
    if (orderData.order) {
        const deliveryDate = orderData.order.delivery_date || orderData.order.order_date || '';
        const dateInput = document.createElement('input');
        dateInput.type = 'hidden';
        dateInput.name = 'delivery_date';
        dateInput.value = deliveryDate;
        itemsContainer.appendChild(dateInput);
    }
    
    console.log('Item forms generated successfully');
}

// Function to validate item selection
function validateItemSelection() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    const noItemsWarning = document.getElementById('noItemsWarning');
    
    if (selectedItems.length === 0) {
        if (noItemsWarning) noItemsWarning.style.display = 'block';
        return false;
    } else {
        if (noItemsWarning) noItemsWarning.style.display = 'none';
        return true;
    }
}

// Function to validate file uploads
function validateFileUploads() {
    console.log('Validating file uploads');
    
    // Constants for file size limits
    const MAX_PHOTO_SIZE = 2 * 1024 * 1024; // 2MB in bytes
    const MAX_VIDEO_SIZE = 10 * 1024 * 1024; // 10MB in bytes
    
    // Allowed video formats
    const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'mov'];
    
    // Get error container
    const errorContainer = document.getElementById('file-upload-errors');
    if (!errorContainer) {
        console.error('File upload error container not found');
        return false;
    }
    
    // Clear previous errors
    errorContainer.innerHTML = '';
    errorContainer.style.display = 'none';
    
    // Get all file inputs
    const photoInputs = document.querySelectorAll('input[type="file"][name^="photos_"]');
    const videoInputs = document.querySelectorAll('input[type="file"][name^="videos_"]');
    
    let errors = [];
    
    // Validate photo uploads
    photoInputs.forEach(input => {
        if (input.files.length > 0) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                
                // Check file size
                if (file.size > MAX_PHOTO_SIZE) {
                    errors.push(`Photo "${file.name}" exceeds the maximum size limit of 2MB.`);
                }
            }
        }
    });
    
    // Validate video uploads
    videoInputs.forEach(input => {
        if (input.files.length > 0) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                
                // Check file size
                if (file.size > MAX_VIDEO_SIZE) {
                    errors.push(`Video "${file.name}" exceeds the maximum size limit of 10MB.`);
                }
                
                // Check file extension
                const extension = file.name.split('.').pop().toLowerCase();
                if (!ALLOWED_VIDEO_EXTENSIONS.includes(extension)) {
                    errors.push(`Video "${file.name}" is not in an allowed format (MP4 or MOV only).`);
                }
            }
        }
    });
    
    // Display errors if any
    if (errors.length > 0) {
        errorContainer.innerHTML = '<strong>File Upload Errors:</strong><ul>' + 
            errors.map(error => `<li>${error}</li>`).join('') + 
            '</ul>';
        errorContainer.style.display = 'block';
        
        // Scroll to error container
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Claim Submission JS Loaded');
    
    // Get elements
    const lookupButton = document.getElementById('lookupOrderBtn');
    const lookupBtnText = document.getElementById('lookupBtnText');
    const errorDiv = document.getElementById('orderLookupError');
    const orderDetailsDiv = document.getElementById('orderDetailsDiv');
    const changeOrderBtn = document.getElementById('changeOrderBtn');
    const claimForm = document.getElementById('claimForm');
    const noItemsWarning = document.getElementById('noItemsWarning');
    
    // Order lookup
    if (lookupButton) {
        console.log('Adding event listener to lookup button');
        lookupButton.addEventListener('click', function() {
            console.log('Lookup button clicked');
            const orderId = document.getElementById('order_id_lookup').value.trim();
            
            if (!orderId) {
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Please enter an order ID';
                return;
            }
            
            // Disable button and show loading
            lookupButton.disabled = true;
            lookupBtnText.textContent = "Loading...";
            errorDiv.style.display = 'none';
            
            console.log('Making AJAX request to:', 'ajax/order_lookup.php');
            
            // Make AJAX request
            fetch('ajax/order_lookup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('AJAX response:', data);
                
                // Reset button text
                lookupButton.disabled = false;
                lookupBtnText.textContent = "Lookup";
                
                if (data.success) {
                    // Store order data in window object for access by other scripts
                    window.orderData = data;
                    
                    // Display order details
                    orderDetailsDiv.style.display = 'block';
                    
                    // Fill in order details
                    document.getElementById('order_id_display').textContent = data.order.order_id;
                    document.getElementById('customer_name_display').textContent = data.order.customer_name;
                    document.getElementById('customer_email_display').textContent = data.order.customer_email;
                    document.getElementById('customer_phone_display').textContent = data.order.customer_phone;
                    document.getElementById('order_date').textContent = data.order.order_date_display || data.order.order_date;
                    
                    // Set hidden input values for the form
                    document.getElementById('claim_order_id').value = data.order.order_id;
                    
                    // Set values in the visible customer information fields
                    const customerNameField = document.getElementById('customer_name_input');
                    const customerEmailField = document.getElementById('customer_email_input');
                    const customerPhoneField = document.getElementById('customer_phone_input');
                    
                    if (customerNameField) {
                        customerNameField.value = data.order.customer_name || '';
                        console.log('Set customer name to:', customerNameField.value);
                    }
                    
                    if (customerEmailField) {
                        customerEmailField.value = data.order.customer_email || '';
                        console.log('Set customer email to:', customerEmailField.value);
                    }
                    
                    if (customerPhoneField) {
                        customerPhoneField.value = data.order.customer_phone || '';
                        console.log('Set customer phone to:', customerPhoneField.value);
                    }
                    
                    // Log the values being set for debugging
                    console.log('Setting customer data:', {
                        'order_id': data.order.order_id,
                        'name': data.order.customer_name,
                        'email': data.order.customer_email,
                        'phone': data.order.customer_phone
                    });
                    
                    // Hide lookup section
                    document.getElementById('orderLookupSection').style.display = 'none';
                    
                    // Display item selection
                    const itemSelectionDiv = document.getElementById('item_selection');
                    itemSelectionDiv.innerHTML = data.items_html;
                    
                    // Use event delegation for checkbox clicks
                    itemSelectionDiv.addEventListener('click', function(e) {
                        // Check if the clicked element is a checkbox
                        if (e.target && e.target.classList.contains('item-checkbox')) {
                            console.log('Checkbox clicked via delegation:', e.target.id, 'Checked:', e.target.checked);
                            
                            // Highlight the selected item card
                            const card = e.target.closest('.card');
                            if (card) {
                                if (e.target.checked) {
                                    card.classList.add('border-primary');
                                    card.classList.remove('border-success', 'border-danger');
                                } else {
                                    // Restore original border
                                    if (card.classList.contains('border-danger')) {
                                        card.classList.add('border-danger');
                                    } else {
                                        card.classList.add('border-success');
                                    }
                                    card.classList.remove('border-primary');
                                }
                            }
                            
                            // Update the form when checkbox state changes
                            validateItemSelection();
                            generateItemForms();
                        }
                    });
                } else {
                    // Display error message
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = data.message || 'An error occurred while fetching order details. Please try again.';
                    
                    // Hide order details
                    orderDetailsDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Reset button text
                lookupButton.disabled = false;
                lookupBtnText.textContent = "Lookup";
                
                // Display error message
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'An error occurred while fetching order details. Please try again.';
            });
        });
    }
    
    // Change order button
    if (changeOrderBtn) {
        changeOrderBtn.addEventListener('click', function() {
            // Show lookup section
            document.getElementById('orderLookupSection').style.display = 'block';
            
            // Hide order details
            orderDetailsDiv.style.display = 'none';
            
            // Clear item selection
            document.getElementById('item_selection').innerHTML = '';
            
            // Clear form
            if (claimForm) {
                claimForm.reset();
            }
            
            // Remove hidden items
            const hiddenItemsContainer = document.getElementById('hidden_items_container');
            if (hiddenItemsContainer) {
                hiddenItemsContainer.innerHTML = '';
            }
        });
    }
    
    // Form submission validation
    if (claimForm) {
        const submitButton = document.getElementById('submitClaimBtn');
        
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Ensure customer information is set from display elements if needed
                const customerName = document.getElementById('customer_name_input');
                const customerEmail = document.getElementById('customer_email_input');
                const customerPhone = document.getElementById('customer_phone_input');
                
                // Get display elements
                const customerNameDisplay = document.getElementById('customer_name_display');
                const customerEmailDisplay = document.getElementById('customer_email_display');
                const customerPhoneDisplay = document.getElementById('customer_phone_display');
                
                // Copy values from display elements if hidden fields are empty
                if (customerName && customerNameDisplay && (!customerName.value || customerName.value.trim() === '')) {
                    customerName.value = customerNameDisplay.textContent.trim();
                    console.log('Copied customer name from display:', customerName.value);
                }
                
                if (customerEmail && customerEmailDisplay && (!customerEmail.value || customerEmail.value.trim() === '')) {
                    customerEmail.value = customerEmailDisplay.textContent.trim();
                    console.log('Copied customer email from display:', customerEmail.value);
                }
                
                if (customerPhone && customerPhoneDisplay && (!customerPhone.value || customerPhone.value.trim() === '')) {
                    customerPhone.value = customerPhoneDisplay.textContent.trim();
                    console.log('Copied customer phone from display:', customerPhone.value);
                }
                
                // If we still don't have customer data and we have window.orderData, use that
                if (window.orderData && window.orderData.order) {
                    if (customerName && (!customerName.value || customerName.value.trim() === '') && window.orderData.order.customer_name) {
                        customerName.value = window.orderData.order.customer_name;
                        console.log('Set customer name from orderData:', customerName.value);
                    }
                    
                    if (customerEmail && (!customerEmail.value || customerEmail.value.trim() === '') && window.orderData.order.customer_email) {
                        customerEmail.value = window.orderData.order.customer_email;
                        console.log('Set customer email from orderData:', customerEmail.value);
                    }
                    
                    if (customerPhone && (!customerPhone.value || customerPhone.value.trim() === '') && window.orderData.order.customer_phone) {
                        customerPhone.value = window.orderData.order.customer_phone;
                        console.log('Set customer phone from orderData:', customerPhone.value);
                    }
                }
                
                // Log customer data before validation
                console.log('Customer data before validation:', {
                    name: customerName ? customerName.value : 'not found',
                    email: customerEmail ? customerEmail.value : 'not found',
                    phone: customerPhone ? customerPhone.value : 'not found'
                });
                
                // Validate form before submission
                if (validateItemSelection() && validateCustomerInfo() && validateFileUploads()) {
                    // Submit form via AJAX
                    const formData = new FormData(claimForm);
                    
                    // Explicitly check and log customer information fields
                    const customerNameInput = document.getElementById('customer_name_input');
                    const customerEmailInput = document.getElementById('customer_email_input');
                    const customerPhoneInput = document.getElementById('customer_phone_input');
                    
                    console.log('Customer information fields before submission:');
                    console.log('customer_name_input element:', customerNameInput);
                    console.log('customer_email_input element:', customerEmailInput);
                    console.log('customer_phone_input element:', customerPhoneInput);
                    
                    if (customerNameInput) {
                        console.log('customer_name_input value:', customerNameInput.value);
                        // Ensure customer name is in the form data
                        if (!formData.has('customer_name')) {
                            formData.append('customer_name', customerNameInput.value);
                            console.log('Added customer_name to formData:', customerNameInput.value);
                        }
                    }
                    
                    if (customerEmailInput) {
                        console.log('customer_email_input value:', customerEmailInput.value);
                        // Ensure customer email is in the form data
                        if (!formData.has('customer_email')) {
                            formData.append('customer_email', customerEmailInput.value);
                            console.log('Added customer_email to formData:', customerEmailInput.value);
                        }
                    }
                    
                    if (customerPhoneInput) {
                        console.log('customer_phone_input value:', customerPhoneInput.value);
                        // Ensure customer phone is in the form data
                        if (!formData.has('customer_phone')) {
                            formData.append('customer_phone', customerPhoneInput.value);
                            console.log('Added customer_phone to formData:', customerPhoneInput.value);
                        }
                    }
                    
                    // Log to console for debugging
                    console.log('Submitting claim form...');
                    
                    // Submit the form
                    $.ajax({
                        url: 'ajax/submit_claim.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        beforeSend: function() {
                            // Show loading indicator
                            submitButton.disabled = true;
                            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
                            
                            // Log form data for debugging
                            console.log('Form data being sent:');
                            for (let pair of formData.entries()) {
                                console.log(pair[0] + ': ' + pair[1]);
                            }
                        },
                        success: function(response) {
                            console.log('Server response:', response);
                            try {
                                const data = typeof response === 'string' ? JSON.parse(response) : response;
                                console.log('Parsed response data:', data);
                                
                                if (data.success) {
                                    // Show success message
                                    alert('Claim submitted successfully!');
                                    // Redirect to claims list
                                    window.location.href = 'claims.php';
                                } else {
                                    // Show error message in the error container
                                    const errorContainer = document.getElementById('error_container');
                                    if (errorContainer) {
                                        // Clear previous errors
                                        errorContainer.innerHTML = '';
                                        
                                        // Create error message header
                                        const errorHeader = document.createElement('div');
                                        errorHeader.className = 'alert alert-danger';
                                        errorHeader.innerHTML = '<strong>Please fix the following errors:</strong>';
                                        errorContainer.appendChild(errorHeader);
                                        
                                        // Create error list
                                        const errorList = document.createElement('ul');
                                        errorList.className = 'list-group mt-2';
                                        
                                        // Add each error as a list item
                                        if (data.errors && Array.isArray(data.errors)) {
                                            data.errors.forEach(function(error) {
                                                const errorItem = document.createElement('li');
                                                errorItem.className = 'list-group-item list-group-item-danger';
                                                errorItem.textContent = error;
                                                errorList.appendChild(errorItem);
                                            });
                                        } else {
                                            // If no errors array, use the message
                                            const errorItem = document.createElement('li');
                                            errorItem.className = 'list-group-item list-group-item-danger';
                                            errorItem.textContent = data.message || 'An error occurred while submitting the claim.';
                                            errorList.appendChild(errorItem);
                                        }
                                        
                                        errorContainer.appendChild(errorList);
                                        errorContainer.style.display = 'block';
                                        
                                        // Scroll to error container
                                        errorContainer.scrollIntoView({ behavior: 'smooth' });
                                    } else {
                                        // Fallback to alert if error container not found
                                        alert(data.message || 'An error occurred while submitting the claim.');
                                    }
                                    
                                    // Reset button
                                    submitButton.disabled = false;
                                    submitButton.innerHTML = 'Submit Claim';
                                }
                            } catch (error) {
                                console.error('Error parsing response:', error);
                                alert('An unexpected error occurred. Please try again.');
                                // Reset button
                                submitButton.disabled = false;
                                submitButton.innerHTML = 'Submit Claim';
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', error);
                            // Show error message in the error container
                            const errorContainer = document.getElementById('error_container');
                            if (errorContainer) {
                                // Clear previous errors
                                errorContainer.innerHTML = '';
                                
                                // Create error message header
                                const errorHeader = document.createElement('div');
                                errorHeader.className = 'alert alert-danger';
                                errorHeader.innerHTML = '<strong>An error occurred while submitting the claim:</strong>';
                                errorContainer.appendChild(errorHeader);
                                
                                // Create error list
                                const errorList = document.createElement('ul');
                                errorList.className = 'list-group mt-2';
                                
                                // Add error as a list item
                                const errorItem = document.createElement('li');
                                errorItem.className = 'list-group-item list-group-item-danger';
                                errorItem.textContent = 'An error occurred while submitting the claim. Please try again.';
                                errorList.appendChild(errorItem);
                                
                                errorContainer.appendChild(errorList);
                                errorContainer.style.display = 'block';
                                
                                // Scroll to error container
                                errorContainer.scrollIntoView({ behavior: 'smooth' });
                            } else {
                                // Fallback to alert if error container not found
                                alert('An error occurred while submitting the claim. Please try again.');
                            }
                            
                            // Reset button
                            submitButton.disabled = false;
                            submitButton.innerHTML = 'Submit Claim';
                        }
                    });
                }
            });
        }
    }
    
    // Function to validate customer information
    function validateCustomerInfo() {
        const customerName = document.getElementById('customer_name_input');
        const customerEmail = document.getElementById('customer_email_input');
        const errorContainer = document.getElementById('error_container');
        let isValid = true;
        let errors = [];
        
        // Clear previous errors
        if (errorContainer) {
            errorContainer.innerHTML = '';
            errorContainer.style.display = 'none';
        }
        
        // Validate customer name
        if (!customerName || !customerName.value || customerName.value.trim() === '') {
            if (customerName) {
                customerName.classList.add('is-invalid');
            }
            errors.push('Customer name is required.');
            isValid = false;
        } else if (customerName) {
            customerName.classList.remove('is-invalid');
            customerName.classList.add('is-valid');
        }
        
        // Validate customer email
        if (!customerEmail || !customerEmail.value || customerEmail.value.trim() === '') {
            if (customerEmail) {
                customerEmail.classList.add('is-invalid');
            }
            errors.push('Customer email is required.');
            isValid = false;
        } else if (customerEmail) {
            customerEmail.classList.remove('is-invalid');
            customerEmail.classList.add('is-valid');
        }
        
        // Display errors if any
        if (!isValid && errorContainer) {
            // Create error message header
            const errorHeader = document.createElement('div');
            errorHeader.className = 'alert alert-danger';
            errorHeader.innerHTML = '<strong>Please fix the following errors:</strong>';
            errorContainer.appendChild(errorHeader);
            
            // Create error list
            const errorList = document.createElement('ul');
            errorList.className = 'list-group mt-2';
            
            // Add each error as a list item
            errors.forEach(function(error) {
                const errorItem = document.createElement('li');
                errorItem.className = 'list-group-item list-group-item-danger';
                errorItem.textContent = error;
                errorList.appendChild(errorItem);
            });
            
            errorContainer.appendChild(errorList);
            errorContainer.style.display = 'block';
            
            // Scroll to error container
            errorContainer.scrollIntoView({ behavior: 'smooth' });
        }
        
        return isValid;
    }
});

// Add event listener for form submission
document.addEventListener('DOMContentLoaded', function() {
    const claimForm = document.getElementById('claimForm');
    if (claimForm) {
        claimForm.addEventListener('submit', function(e) {
            // Prevent default form submission
            e.preventDefault();
            
            // Validate item selection
            if (!validateItemSelection()) {
                alert('Please select at least one item for the warranty claim.');
                return false;
            }
            
            // Validate file uploads
            if (!validateFileUploads()) {
                return false;
            }
            
            // If all validations pass, submit the form
            this.submit();
        });
    }
});
