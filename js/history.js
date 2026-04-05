// Sample transaction data
const transactionData = [
    {
        id: 1,
        name: "GitHub",
        date: "6 Sep 2023 at 4:10 PM",
        amount: -360.00,
        type: "expense",
        status: "completed",
        icon: "GH",
        iconColor: "#333333"
    },
    {
        id: 2,
        name: "PayPal Transfer",
        date: "5 Sep 2023 at 2:30 PM",
        amount: 1250.00,
        type: "income",
        status: "completed",
        icon: "PP",
        iconColor: "#0070ba"
    },
    {
        id: 3,
        name: "Stripe Payment",
        date: "4 Sep 2023 at 11:45 AM",
        amount: 789.50,
        type: "income",
        status: "completed",
        icon: "ST",
        iconColor: "#635bff"
    },
    {
        id: 4,
        name: "AWS Services",
        date: "3 Sep 2023 at 9:20 AM",
        amount: -156.75,
        type: "expense",
        status: "completed",
        icon: "AWS",
        iconColor: "#ff9900"
    },
    {
        id: 5,
        name: "Client Invoice #1234",
        date: "2 Sep 2023 at 3:15 PM",
        amount: 2500.00,
        type: "income",
        status: "pending",
        icon: "CI",
        iconColor: "#22c55e"
    },
    {
        id: 6,
        name: "Office Supplies",
        date: "1 Sep 2023 at 10:30 AM",
        amount: -89.99,
        type: "expense",
        status: "completed",
        icon: "OS",
        iconColor: "#8b5cf6"
    },
    {
        id: 7,
        name: "Refund - Product Return",
        date: "31 Aug 2023 at 4:45 PM",
        amount: 299.99,
        type: "income",
        status: "failed",
        icon: "RF",
        iconColor: "#ef4444"
    },
    {
        id: 8,
        name: "Subscription Fee",
        date: "30 Aug 2023 at 12:00 PM",
        amount: -49.99,
        type: "expense",
        status: "completed",
        icon: "SF",
        iconColor: "#f59e0b"
    },
    {
        id: 9,
        name: "Freelance Project",
        date: "29 Aug 2023 at 6:20 PM",
        amount: 1800.00,
        type: "income",
        status: "completed",
        icon: "FP",
        iconColor: "#06b6d4"
    },
    {
        id: 10,
        name: "Marketing Tools",
        date: "28 Aug 2023 at 8:15 AM",
        amount: -199.00,
        type: "expense",
        status: "completed",
        icon: "MT",
        iconColor: "#ec4899"
    }
];

// Global variables
let currentTransactions = [];
let filteredTransactions = [];
let currentPage = 1;
const itemsPerPage = 5;

// DOM elements
const transactionList = document.getElementById('transactionList');
const loadMoreBtn = document.getElementById('loadMoreBtn');
const seeAllBtn = document.getElementById('seeAllBtn');
const dateFilter = document.getElementById('dateFilter');
const typeFilter = document.getElementById('typeFilter');
const searchInput = document.getElementById('searchInput');

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeTransactions();
    setupEventListeners();
    renderTransactions();
});

// Initialize transactions
function initializeTransactions() {
    currentTransactions = [...transactionData];
    filteredTransactions = [...transactionData];
}

// Setup event listeners
function setupEventListeners() {
    // Filter event listeners
    dateFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', debounce(applyFilters, 300));
    
    // Button event listeners
    loadMoreBtn.addEventListener('click', loadMoreTransactions);
    seeAllBtn.addEventListener('click', toggleShowAll);
    
    // Balance toggle
    const balanceToggle = document.getElementById('balance-toggle');
    balanceToggle.addEventListener('change', toggleBalanceVisibility);
}

// Render transactions
function renderTransactions() {
    const startIndex = 0;
    const endIndex = currentPage * itemsPerPage;
    const transactionsToShow = filteredTransactions.slice(startIndex, endIndex);
    
    if (transactionsToShow.length === 0) {
        transactionList.innerHTML = `
            <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.6);">
                <p>No transactions found matching your criteria.</p>
            </div>
        `;
        loadMoreBtn.style.display = 'none';
        return;
    }
    
    transactionList.innerHTML = transactionsToShow.map(transaction => 
        createTransactionElement(transaction)
    ).join('');
    
    // Update load more button visibility
    if (endIndex >= filteredTransactions.length) {
        loadMoreBtn.style.display = 'none';
    } else {
        loadMoreBtn.style.display = 'block';
    }
}

// Create transaction element
function createTransactionElement(transaction) {
    const amountClass = transaction.amount >= 0 ? 'positive' : 'negative';
    const amountPrefix = transaction.amount >= 0 ? '+' : '';
    const formattedAmount = `${amountPrefix}$${Math.abs(transaction.amount).toFixed(2)}`;
    
    return `
        <div class="transaction-item" data-id="${transaction.id}">
            <div class="transaction-icon" style="background-color: ${transaction.iconColor}20; color: ${transaction.iconColor}; border: 1px solid ${transaction.iconColor}30;">
                ${transaction.icon}
            </div>
            <div class="transaction-details">
                <div class="transaction-name">${transaction.name}</div>
                <div class="transaction-date">${transaction.date}</div>
            </div>
            <div class="transaction-amount ${amountClass}">
                ${formattedAmount}
            </div>
            <div class="transaction-status status-${transaction.status}">
                ${transaction.status}
            </div>
        </div>
    `;
}

// Apply filters
function applyFilters() {
    let filtered = [...currentTransactions];
    
    // Date filter
    const dateFilterValue = dateFilter.value;
    if (dateFilterValue !== 'all') {
        filtered = filterByDate(filtered, dateFilterValue);
    }
    
    // Type filter
    const typeFilterValue = typeFilter.value;
    if (typeFilterValue !== 'all') {
        filtered = filtered.filter(transaction => transaction.type === typeFilterValue);
    }
    
    // Search filter
    const searchTerm = searchInput.value.toLowerCase().trim();
    if (searchTerm) {
        filtered = filtered.filter(transaction => 
            transaction.name.toLowerCase().includes(searchTerm) ||
            transaction.date.toLowerCase().includes(searchTerm)
        );
    }
    
    filteredTransactions = filtered;
    currentPage = 1;
    renderTransactions();
}

// Filter by date
function filterByDate(transactions, dateFilter) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    return transactions.filter(transaction => {
        const transactionDate = new Date(transaction.date);
        
        switch (dateFilter) {
            case 'today':
                return transactionDate >= today;
            case 'week':
                const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                return transactionDate >= weekAgo;
            case 'month':
                const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                return transactionDate >= monthAgo;
            case 'year':
                const yearAgo = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                return transactionDate >= yearAgo;
            default:
                return true;
        }
    });
}

// Load more transactions
function loadMoreTransactions() {
    showLoading();
    
    setTimeout(() => {
        currentPage++;
        renderTransactions();
        hideLoading();
    }, 500);
}

// Toggle show all
function toggleShowAll() {
    if (seeAllBtn.textContent === 'See All') {
        currentPage = Math.ceil(filteredTransactions.length / itemsPerPage);
        seeAllBtn.textContent = 'Show Less';
    } else {
        currentPage = 1;
        seeAllBtn.textContent = 'See All';
    }
    renderTransactions();
}

// Toggle balance visibility
function toggleBalanceVisibility() {
    const balanceAmount = document.querySelector('.balance-amount');
    const isHidden = balanceAmount.textContent === '••••••••';
    
    if (isHidden) {
        balanceAmount.textContent = '$8,130.64M';
    } else {
        balanceAmount.textContent = '••••••••';
    }
}

// Show loading
function showLoading() {
    loadMoreBtn.innerHTML = `
        <div class="loading-spinner"></div>
        Loading...
    `;
    loadMoreBtn.disabled = true;
}

// Hide loading
function hideLoading() {
    loadMoreBtn.innerHTML = 'Load More Transactions';
    loadMoreBtn.disabled = false;
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Animation utilities
function animateIn(element) {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    element.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        element.style.opacity = '1';
        element.style.transform = 'translateY(0)';
    }, 100);
}

// Add click animation to transaction items
document.addEventListener('click', function(e) {
    const transactionItem = e.target.closest('.transaction-item');
    if (transactionItem) {
        transactionItem.style.transform = 'scale(0.98)';
        setTimeout(() => {
            transactionItem.style.transform = '';
        }, 150);
    }
});

// Add some sample notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 20px',
        borderRadius: '8px',
        color: '#ffffff',
        fontWeight: '500',
        zIndex: '1000',
        transform: 'translateX(100%)',
        transition: 'transform 0.3s ease'
    });
    
    // Set background color based on type
    switch (type) {
        case 'success':
            notification.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
            break;
        case 'error':
            notification.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            break;
        case 'warning':
            notification.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
            break;
        default:
            notification.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
    }
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Export/Import functionality
function exportTransactions() {
    const dataStr = JSON.stringify(filteredTransactions, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'transactions_export.json';
    link.click();
    URL.revokeObjectURL(url);
    
    showNotification('Transactions exported successfully!', 'success');
}

// Statistics calculation
function calculateStats() {
    const totalIncome = filteredTransactions
        .filter(t => t.amount > 0)
        .reduce((sum, t) => sum + t.amount, 0);
    
    const totalExpense = filteredTransactions
        .filter(t => t.amount < 0)
        .reduce((sum, t) => sum + Math.abs(t.amount), 0);
    
    const netAmount = totalIncome - totalExpense;
    
    return {
        totalIncome,
        totalExpense,
        netAmount,
        transactionCount: filteredTransactions.length
    };
}

// Update stats display (if you want to add a stats section)
function updateStatsDisplay() {
    const stats = calculateStats();
    const statsElement = document.getElementById('statsDisplay');
    
    if (statsElement) {
        statsElement.innerHTML = `
            <div class="stat-item">
                <span class="stat-label">Total Income</span>
                <span class="stat-value positive">+${stats.totalIncome.toFixed(2)}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Expenses</span>
                <span class="stat-value negative">-${stats.totalExpense.toFixed(2)}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Net Amount</span>
                <span class="stat-value ${stats.netAmount >= 0 ? 'positive' : 'negative'}">
                    ${stats.netAmount >= 0 ? '+' : ''}${stats.netAmount.toFixed(2)}
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Transactions</span>
                <span class="stat-value">${stats.transactionCount}</span>
            </div>
        `;
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        searchInput.focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape' && document.activeElement === searchInput) {
        searchInput.value = '';
        applyFilters();
        searchInput.blur();
    }
});

// Intersection Observer for animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe transaction items for scroll animations
function observeTransactionItems() {
    const items = document.querySelectorAll('.transaction-item');
    items.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(item);
    });
}

// Call this after rendering transactions
function enhanceRendering() {
    renderTransactions();
    setTimeout(() => {
        observeTransactionItems();
        updateStatsDisplay();
    }, 100);
}

// Update the original renderTransactions call
const originalRenderTransactions = renderTransactions;
renderTransactions = function() {
    originalRenderTransactions.call(this);
    setTimeout(() => {
        observeTransactionItems();
    }, 100);
};

// Add refresh functionality
function refreshTransactions() {
    showLoading();
    setTimeout(() => {
        // Simulate fetching new data
        initializeTransactions();
        applyFilters();
        hideLoading();
        showNotification('Transactions refreshed!', 'success');
    }, 1000);
}

// Add keyboard shortcut for refresh
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        refreshTransactions();
    }
});

// Performance optimization - Virtual scrolling for large datasets
class VirtualScroller {
    constructor(container, items, itemHeight, visibleCount) {
        this.container = container;
        this.items = items;
        this.itemHeight = itemHeight;
        this.visibleCount = visibleCount;
        this.scrollTop = 0;
        this.startIndex = 0;
        this.endIndex = Math.min(visibleCount, items.length);
        
        this.init();
    }
    
    init() {
        this.container.style.height = `${this.items.length * this.itemHeight}px`;
        this.container.style.position = 'relative';
        this.render();
    }
    
    render() {
        const visibleItems = this.items.slice(this.startIndex, this.endIndex);
        this.container.innerHTML = visibleItems.map((item, index) => {
            const actualIndex = this.startIndex + index;
            const element = createTransactionElement(item);
            return `<div style="position: absolute; top: ${actualIndex * this.itemHeight}px; width: 100%;">${element}</div>`;
        }).join('');
    }
    
    updateScroll(scrollTop) {
        this.scrollTop = scrollTop;
        this.startIndex = Math.floor(scrollTop / this.itemHeight);
        this.endIndex = Math.min(this.startIndex + this.visibleCount, this.items.length);
        this.render();
    }
}