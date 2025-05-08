/**
 * Reload Helper
 * 
 * This file provides a reliable way to reload the page after adding a note
 */

// Function to force a page reload
function forcePageReload() {
    console.log('Force reload function called');
    
    // Try multiple approaches to ensure the page reloads
    try {
        // First approach: Hard reload
        window.location.reload(true);
    } catch (e) {
        console.error('First reload approach failed:', e);
        
        // Second approach: Change location
        try {
            window.location.href = window.location.href;
        } catch (e2) {
            console.error('Second reload approach failed:', e2);
            
            // Third approach: Replace location
            try {
                window.location.replace(window.location.href);
            } catch (e3) {
                console.error('All reload approaches failed');
                alert('Please manually refresh the page to see your new note.');
            }
        }
    }
}
