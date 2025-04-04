/**
 * Warranty Management System
 * Claim Submission JavaScript
 */

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
                    
                    // Add event listeners to radio buttons
                    const radioButtons = document.querySelectorAll('input[name="claim_items"]');
                    radioButtons.forEach(radio => {
                        radio.addEventListener('change', function() {
                            validateItemSelection();
                            
                            // When an item is selected, add it to the form
                            if (this.checked) {
                                const itemIndex = parseInt(this.value);
                                const item = data.order.items[itemIndex];
                                
                                // Create hidden inputs for the selected item
                                let itemInputs = `
                                    <input type="hidden" name="item_sku[]" value="${item.sku}">
                                    <input type="hidden" name="item_product_name[]" value="${item.product_name}">
                                    <input type="hidden" name="item_product_type[]" value="${item.product_type || ''}">
                                    <input type="hidden" name="delivery_date" value="${data.order.delivery_date || data.order.order_date}">
                                `;
                                
                                // Clear previous selections and add new one
                                const hiddenItemsContainer = document.getElementById('hidden_items_container');
                                if (hiddenItemsContainer) {
                                    hiddenItemsContainer.innerHTML = itemInputs;
                                }
                                
                                // Log to console for debugging
                                console.log('Customer data set:', {
                                    name: document.getElementById('customer_name_input').value,
                                    email: document.getElementById('customer_email_input').value,
                                    phone: document.getElementById('customer_phone_input').value
                                });
                            }
                        });
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
                if (validateItemSelection() && validateCustomerInfo()) {
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
    
    // Function to validate item selection
    function validateItemSelection() {
        const radioButtons = document.querySelectorAll('input[name="claim_items"]:checked');
        const valid = radioButtons.length > 0;
        
        if (!valid) {
            noItemsWarning.style.display = 'block';
        } else {
            noItemsWarning.style.display = 'none';
        }
        
        return valid;
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
            // Get customer information fields
            const customerName = document.getElementById('customer_name_input');
            const customerEmail = document.getElementById('customer_email_input');
            const customerPhone = document.getElementById('customer_phone_input');
            
            // Log customer data before submission
            console.log('Customer data before form submission:', {
                name: customerName ? customerName.value : 'not found',
                email: customerEmail ? customerEmail.value : 'not found',
                phone: customerPhone ? customerPhone.value : 'not found'
            });
        });
    }
});
