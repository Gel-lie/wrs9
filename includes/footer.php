    </div>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script>
        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Enable Bootstrap popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Confirm delete actions
        $('.confirm-delete').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });

        // Handle AJAX form submissions
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(response.message || 'An error occurred');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

        // Real-time chat updates
        function updateChat() {
            if ($('.chat-messages').length) {
                $.get('/chat_updates.php', function(data) {
                    $('.chat-messages').html(data);
                    setTimeout(updateChat, 5000);
                });
            }
        }
        if ($('.chat-messages').length) {
            updateChat();
        }

        // Inventory low stock alerts
        function checkLowStock() {
            if ($('.inventory-alerts').length) {
                $.get('/check_low_stock.php', function(data) {
                    $('.inventory-alerts').html(data);
                });
            }
        }
        if ($('.inventory-alerts').length) {
            checkLowStock();
            setInterval(checkLowStock, 300000); // Check every 5 minutes
        }
    </script>
</body>
</html> 