/* 
   SmartVee POS - Premium JS Client Logic
   Handles responsive navigation overlay, mobile bottom sheets, table filters, and UI animations.
*/

document.addEventListener('DOMContentLoaded', function() {
    // 1. Mobile Sidebar Overlay Control
    const sidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebarToggle && sidebar && backdrop) {
        // Toggle Sidebar
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                backdrop.style.display = 'block';
            } else {
                backdrop.style.display = 'none';
            }
        });
        
        // Dismiss Sidebar on Backdrop Tap
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('active');
            backdrop.style.display = 'none';
        });
    }

    // 2. Mobile POS Cart Drawer Panel Control
    const cartBarTrigger = document.getElementById('floatingCartBarTrigger');
    const cartPanel = document.querySelector('.pos-cart-panel');
    const cartCloseBtn = document.getElementById('closeCartDrawerBtn');
    
    if (cartBarTrigger && cartPanel) {
        cartBarTrigger.addEventListener('click', function() {
            cartPanel.classList.add('open');
        });
    }
    
    if (cartCloseBtn && cartPanel) {
        cartCloseBtn.addEventListener('click', function() {
            cartPanel.classList.remove('open');
        });
    }

    // 3. Dynamic Live Table Search Helper
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(input => {
        const tableSelector = input.getAttribute('data-search-table');
        const targetTable = document.querySelector(tableSelector);
        if (targetTable) {
            input.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                const rows = targetTable.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    let match = false;
                    const cells = row.querySelectorAll('td');
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().indexOf(term) > -1) {
                            match = true;
                        }
                    });
                    
                    if (match) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });

    // 4. Fade dismissible alerts out after 3 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000);
    });
});
