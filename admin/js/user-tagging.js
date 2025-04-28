/**
 * User Tagging System
 * 
 * This file handles the user tagging functionality in claim notes
 * Auto-populates users when typing and highlights matching users
 */

$(document).ready(function() {
    // Variables for user tagging
    let allUsers = [];
    let currentTagPosition = null;
    let currentTagText = '';
    
    // Create dropdown for suggestions
    const suggestionsDropdown = $('<div>')
        .attr('id', 'userSuggestionsDropdown')
        .addClass('dropdown-menu')
        .css({
            'max-height': '200px',
            'overflow-y': 'auto',
            'width': '300px',
            'z-index': 9999
        });
    
    $('body').append(suggestionsDropdown);
    
    // Load users for tagging
    $.ajax({
        url: 'ajax/get_users_for_tagging.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.users) {
                allUsers = response.users;
                setupAutoTagging();
            }
        },
        error: function(xhr, status, error) {
            console.error("Error loading users:", error);
        }
    });
    
    function setupAutoTagging() {
        const noteTextarea = $('#note_text');
        const taggedUsersPreview = $('#taggedUsersPreview');
        const taggedUsersList = $('.tagged-users-list');
        
        // Handle keyup in the note textarea
        noteTextarea.on('keyup', function(e) {
            try {
                const text = $(this).val() || '';
                const cursorPosition = this.selectionStart || 0;
                
                // Check if we're in the middle of typing a tag
                const textUpToCursor = text.substring(0, cursorPosition);
                const lastAtSymbol = textUpToCursor.lastIndexOf('@');
                
                // If we found an @ symbol and it's not part of an email address
                if (lastAtSymbol !== -1 && !isPartOfEmail(textUpToCursor, lastAtSymbol)) {
                    const textAfterAt = textUpToCursor.substring(lastAtSymbol + 1);
                    const match = textAfterAt.match(/^[a-zA-Z0-9._]*/);
                    
                    // Show suggestions immediately when @ is typed
                    currentTagPosition = lastAtSymbol;
                    currentTagText = match ? match[0] : '';
                    
                    // Show all users if no text after @, otherwise filter
                    if (currentTagText === '') {
                        showAllUserSuggestions();
                    } else {
                        showUserSuggestions(currentTagText);
                    }
                    return;
                }
                
                // If we're not in the middle of a tag, hide suggestions
                hideSuggestions();
                
                // Update tagged users preview
                updateTaggedUsersPreview(text);
            } catch (err) {
                console.error('Error in keyup handler:', err);
            }
        });
        
        // Also handle keydown for @ symbol to show suggestions immediately
        noteTextarea.on('keydown', function(e) {
            if (e.key === '@') {
                // We'll handle this in the keyup event
                // This ensures the @ character is in the textarea before showing suggestions
            }
        });
        
        // Check if @ is part of an email address
        function isPartOfEmail(text, atPosition) {
            // Simple check: if there's a period and no spaces before the @, it's likely an email
            const textBeforeAt = text.substring(0, atPosition);
            return /[^\s]+\.[^\s]+$/.test(textBeforeAt);
        }
        
        // Handle keydown to navigate suggestions
        noteTextarea.on('keydown', function(e) {
            // If suggestions are visible
            if (suggestionsDropdown.is(':visible')) {
                const activeItem = suggestionsDropdown.find('.active');
                
                // Down arrow
                if (e.keyCode === 40) {
                    e.preventDefault();
                    navigateDropdown('down', activeItem);
                }
                
                // Up arrow
                else if (e.keyCode === 38) {
                    e.preventDefault();
                    navigateDropdown('up', activeItem);
                }
                
                // Enter or Tab to select
                else if (e.keyCode === 13 || e.keyCode === 9) {
                    if (activeItem.length) {
                        e.preventDefault();
                        selectUser(activeItem.data('username'));
                    }
                }
                
                // Escape to close
                else if (e.keyCode === 27) {
                    e.preventDefault();
                    hideSuggestions();
                }
            }
        });
        
        // Navigate dropdown with arrow keys
        function navigateDropdown(direction, activeItem) {
            if (activeItem.length) {
                activeItem.removeClass('active');
                
                if (direction === 'down') {
                    const next = activeItem.next('.dropdown-item');
                    if (next.length) {
                        next.addClass('active');
                    } else {
                        suggestionsDropdown.find('.dropdown-item:first').addClass('active');
                    }
                } else {
                    const prev = activeItem.prev('.dropdown-item');
                    if (prev.length) {
                        prev.addClass('active');
                    } else {
                        suggestionsDropdown.find('.dropdown-item:last').addClass('active');
                    }
                }
            } else {
                if (direction === 'down') {
                    suggestionsDropdown.find('.dropdown-item:first').addClass('active');
                } else {
                    suggestionsDropdown.find('.dropdown-item:last').addClass('active');
                }
            }
        }
        
        // Show all user suggestions immediately when @ is typed
        function showAllUserSuggestions() {
            if (!allUsers) {
                // Fetch users first if not cached
                $.ajax({
                    url: 'ajax/get_users_for_tagging.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.users) {
                            allUsers = response.users;
                            showAllUserSuggestions();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading users:", error);
                    }
                });
                return;
            }
            
            // Clear previous suggestions
            suggestionsDropdown.empty();
            
            // Add all users to the suggestions dropdown
            allUsers.forEach(user => {
                const item = $('<a>')
                    .addClass('dropdown-item')
                    .attr('href', '#')
                    .attr('data-username', user.username)
                    .html(`
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${user.username}</strong>
                                <small class="text-muted d-block">${user.email}</small>
                            </div>
                            <span class="badge bg-secondary">${user.role}</span>
                        </div>
                    `);
                
                item.on('click', function(e) {
                    e.preventDefault();
                    selectUser(user.username);
                });
                
                suggestionsDropdown.append(item);
            });
            
            // Position and show the suggestions dropdown
            positionDropdown();
            suggestionsDropdown.show();
        }
        
        // Show user suggestions based on input
        function showUserSuggestions(query) {
            if (!allUsers) {
                // Fetch users first if not cached
                $.ajax({
                    url: 'ajax/get_users_for_tagging.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.users) {
                            allUsers = response.users;
                            showUserSuggestions(query);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error loading users:", error);
                    }
                });
                return;
            }
            
            // Filter users based on query
            const filteredUsers = allUsers.filter(user => 
                user.username.toLowerCase().includes(query.toLowerCase()));
            
            if (filteredUsers.length === 0) {
                hideSuggestions();
                return;
            }
            
            // Clear previous suggestions
            suggestionsDropdown.empty();
            
            // Add header
            suggestionsDropdown.append(
                $('<h6>').addClass('dropdown-header').text('Matching users')
            );
            
            // Add matching users
            filteredUsers.forEach(user => {
                // Highlight matching part
                let highlightedUsername = highlightMatch(user.username, query);
                const item = $('<a>')
                    .addClass('dropdown-item')
                    .attr('href', '#')
                    .attr('data-username', user.username)
                    .html(`
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${highlightedUsername}</strong>
                                <small class="text-muted d-block">${user.email}</small>
                            </div>
                            <span class="badge bg-secondary">${user.role}</span>
                        </div>
                    `);
                
                item.on('click', function(e) {
                    e.preventDefault();
                    selectUser(user.username);
                });
                
                suggestionsDropdown.append(item);
            });
            
            // Position the dropdown
            positionDropdown();
        }
        
        // Escape special regex characters
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // Highlight matching part of username
        function highlightMatch(username, query) {
            if (!query) return username;
            
            try {
                const escapedQuery = escapeRegExp(query);
                const regex = new RegExp('(' + escapedQuery + ')', 'gi');
                return username.replace(regex, '<span style="background-color: #fff3cd; font-weight: bold;">$1</span>');
            } catch (err) {
                console.error('Error highlighting match:', err);
                return username;
            }
        }
        
        // Position the dropdown under the cursor
        function positionDropdown() {
            try {
                const textareaOffset = noteTextarea.offset();
                const textareaHeight = noteTextarea.outerHeight();
                
                suggestionsDropdown.css({
                    'position': 'absolute',
                    'top': (textareaOffset.top + textareaHeight) + 'px',
                    'left': textareaOffset.left + 'px',
                    'display': 'block'
                });
            } catch (err) {
                console.error('Error positioning dropdown:', err);
            }
        }
        
        // Hide suggestions dropdown
        function hideSuggestions() {
            suggestionsDropdown.hide();
            currentTagPosition = null;
            currentTagText = '';
        }
        
        // Select a user from suggestions
        function selectUser(username) {
            try {
                if (!noteTextarea.length || !noteTextarea[0]) return;
                
                const textarea = noteTextarea[0];
                const text = textarea.value || '';
                
                if (currentTagPosition === null) return;
                
                // Replace the current tag with the selected username
                const beforeTag = text.substring(0, currentTagPosition);
                const afterTag = text.substring(currentTagPosition + currentTagText.length + 1); // +1 for @ symbol
                const newText = beforeTag + '@' + username + ' ' + afterTag;
                
                textarea.value = newText;
                
                // Move cursor after the inserted tag
                const newCursorPosition = currentTagPosition + username.length + 2; // +2 for @ and space
                try {
                    textarea.setSelectionRange(newCursorPosition, newCursorPosition);
                } catch (e) {
                    console.warn('Could not set selection range:', e);
                }
                
                // Hide suggestions
                hideSuggestions();
                
                // Update tagged users preview
                updateTaggedUsersPreview(newText);
                
                // Focus back on textarea
                noteTextarea.focus();
            } catch (err) {
                console.error('Error selecting user:', err);
            }
        }
        
        // Update the tagged users preview
        function updateTaggedUsersPreview(text) {
            try {
                // Extract @username mentions
                const mentions = text.match(/@([a-zA-Z0-9._]+)/g) || [];
                
                if (mentions.length > 0) {
                    // Extract usernames without @ symbol
                    const taggedUsers = mentions.map(mention => mention.substring(1));
                    
                    // Update preview
                    taggedUsersList.empty();
                    
                    taggedUsers.forEach(username => {
                        const badge = $('<span>')
                            .addClass('badge bg-primary me-2 mb-1')
                            .html(`<i class="fas fa-user"></i> ${username}`);
                        
                        taggedUsersList.append(badge);
                    });
                    
                    // Show the preview
                    taggedUsersPreview.show();
                } else {
                    // Hide the preview if no users are tagged
                    taggedUsersPreview.hide();
                }
            } catch (err) {
                console.error('Error updating tagged users preview:', err);
            }
        }
        
        // Close suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#userSuggestionsDropdown, #note_text').length) {
                hideSuggestions();
            }
        });
    }
});
