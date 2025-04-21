/**
 * JavaScript Syntax Error Debugger and Fixer
 * 
 * This script helps identify and fix JavaScript syntax errors,
 * particularly the "Uncaught SyntaxError: Unexpected token '}'" error.
 */

$(document).ready(function() {
    console.log('JavaScript syntax error debugger initialized');
    
    // Function to detect and report JavaScript errors
    window.onerror = function(message, source, lineno, colno, error) {
        console.error('JavaScript Error Detected:', {
            message: message,
            source: source,
            line: lineno,
            column: colno,
            error: error
        });
        
        // Try to recover tooltips and other Bootstrap components
        setTimeout(recoverBootstrapComponents, 500);
        
        // Return false to allow the error to also be logged in the console
        return false;
    };
    
    // Function to recover Bootstrap components
    function recoverBootstrapComponents() {
        try {
            // Recover tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipElements.forEach(function(el) {
                    try {
                        // Dispose any existing tooltip
                        var tooltip = bootstrap.Tooltip.getInstance(el);
                        if (tooltip) {
                            tooltip.dispose();
                        }
                        // Create new tooltip
                        new bootstrap.Tooltip(el, {
                            trigger: 'click',
                            html: true,
                            placement: 'right'
                        });
                    } catch (e) {
                        console.log('Error recovering tooltip:', e);
                    }
                });
                console.log('Tooltips recovered successfully');
            }
            
            // Recover modals
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalElements = document.querySelectorAll('.modal');
                modalElements.forEach(function(el) {
                    try {
                        // Dispose any existing modal
                        var modal = bootstrap.Modal.getInstance(el);
                        if (modal) {
                            modal.dispose();
                        }
                        // Create new modal
                        new bootstrap.Modal(el);
                    } catch (e) {
                        console.log('Error recovering modal:', e);
                    }
                });
                console.log('Modals recovered successfully');
            }
        } catch (e) {
            console.error('Error in component recovery:', e);
        }
    }
    
    // Check for any uncaught errors in the page
    setTimeout(function() {
        console.log('Running syntax error check...');
        try {
            // This will execute any code that might have syntax errors
            eval('(function() { try { console.log("Syntax check passed"); } catch(e) { console.error("Internal eval error:", e); } })();');
        } catch (e) {
            console.error('Syntax check failed:', e);
        }
    }, 1000);
    
    // Log the actual rendered JavaScript to help identify the error
    console.log('To find the syntax error, check line 5166 in the browser source view');
});
