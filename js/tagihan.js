        // Toggle balance visibility
        const balanceToggle = document.getElementById('balance-toggle');
        const balanceAmount = document.querySelector('.balance-amount');
        const originalAmount = balanceAmount.textContent;

        balanceToggle.addEventListener('change', function() {
            if (this.checked) {
                balanceAmount.textContent = '••••••••';
            } else {
                balanceAmount.textContent = originalAmount;
            }
        });

        // Add hover effects
        document.querySelectorAll('.billing-card, .transaction-item, .payment-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });