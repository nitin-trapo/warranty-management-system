        </div><!-- End of Page Content -->
    </div><!-- End of Main Content -->
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    
    <!-- DataTables Buttons Extensions -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Notification Functions -->
    <script>
        // Mark a single notification as read
        function markNotificationAsRead(notificationId, element) {
            // Prevent default action if it's a link
            if (element) {
                event.preventDefault();
            }
            
            // Send AJAX request to mark notification as read
            $.ajax({
                url: 'ajax/mark_notification_read.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    notification_id: notificationId
                },
                success: function(result) {
                    if (result.success) {
                        // Update notification count
                        updateNotificationCount();
                        
                        // If element is provided, redirect to the link
                        if (element && element.href && element.href !== 'javascript:void(0);') {
                            window.location.href = element.href;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error marking notification as read:', error);
                }
            });
        }
        
        // Mark all notifications as read
        function markAllNotificationsAsRead() {
            // Send AJAX request to mark all notifications as read
            $.ajax({
                url: 'ajax/mark_all_notifications_read.php',
                type: 'POST',
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        // Update notification count
                        updateNotificationCount();
                        
                        // Hide all notifications in dropdown
                        $('.notification-item').remove();
                        $('.notification-dropdown').append('<div class="notification-empty"><p>No new notifications</p></div>');
                        $('.mark-all-read').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error marking all notifications as read:', error);
                }
            });
        }
        
        // Update notification count
        function updateNotificationCount() {
            // Send AJAX request to get notification count
            $.ajax({
                url: 'ajax/get_notification_count.php',
                type: 'GET',
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        const badge = $('#notificationsDropdown .notification-badge');
                        
                        if (result.count > 0) {
                            // Update or create badge
                            if (badge.length > 0) {
                                badge.text(result.count);
                            } else {
                                $('#notificationsDropdown').append('<span class="badge rounded-pill bg-danger notification-badge">' + result.count + '</span>');
                            }
                        } else {
                            // Remove badge if count is 0
                            badge.remove();
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            });
        }
    </script>
    
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            // Toggle sidebar
            $('#toggle-sidebar').click(function() {
                $('#sidebar').toggleClass('sidebar-collapsed');
                $('#main-content').toggleClass('main-content-expanded');
            });
            
            // Initialize DataTables
            if (window.location.pathname.indexOf('claims.php') === -1) {
                // Only initialize datatables for non-claims pages
                $('.datatable').DataTable({
                    responsive: true,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)"
                    }
                });
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert-auto-dismiss').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>
