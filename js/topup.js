// Data cryptocurrency
const cryptoData = [
    {
        name: 'BNB',
        icon: '🟡',
        price: 'Rp10,432,546.82',
        change: '-1.27%',
        color: '#f3ba2f'
    },
    {
        name: 'BTC',
        icon: '🟠',
        price: 'Rp1,697,687,757.07',
        change: '-0.52%',
        color: '#f7931a'
    },
    {
        name: 'ETH',
        icon: '🔵',
        price: 'Rp39,735,831.04',
        change: '-2.55%',
        color: '#627eea'
    },
    {
        name: 'PEPE',
        icon: '🟢',
        price: 'Rp0.15866991',
        change: '-2.32%',
        color: '#2ecc71'
    },
    {
        name: 'SOL',
        icon: '🟣',
        price: 'Rp2,301,041.58',
        change: '-1.58%',
        color: '#9945ff'
    }
];

// Exchange rate USDT ke IDR (contoh rate)
const USDT_TO_IDR_RATE = 15500;

// DOM Elements
const topupTab = document.getElementById('topupTab');
const transferTab = document.getElementById('transferTab');
const topupContent = document.getElementById('topupContent');
const transferContent = document.getElementById('transferContent');
const spendAmount = document.getElementById('spendAmount');
const receiveAmount = document.getElementById('receiveAmount');
const cryptoList = document.getElementById('cryptoList');

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    renderCryptoList();
    setupEventListeners();
    updateReceiveAmount();
});

// Render crypto list
function renderCryptoList() {
    cryptoList.innerHTML = '';
    
    cryptoData.forEach(crypto => {
        const cryptoItem = document.createElement('div');
        cryptoItem.className = 'crypto-item';
        cryptoItem.innerHTML = `
            <div class="crypto-info">
                <div class="crypto-icon" style="background: ${crypto.color}20; color: ${crypto.color}">
                    ${crypto.icon}
                </div>
                <div class="crypto-name">${crypto.name}</div>
            </div>
            <div class="crypto-price">
                <div class="price">${crypto.price}</div>
                <div class="change ${parseFloat(crypto.change) < 0 ? 'negative' : 'positive'}">
                    ${crypto.change}
                </div>
            </div>
        `;
        
        cryptoItem.addEventListener('click', () => {
            selectCrypto(crypto);
        });
        
        cryptoList.appendChild(cryptoItem);
    });
}

// Setup event listeners
function setupEventListeners() {
    // Tab switching
    topupTab.addEventListener('click', () => switchTab('topup'));
    transferTab.addEventListener('click', () => switchTab('transfer'));
    
    // Amount calculation
    spendAmount.addEventListener('input', updateReceiveAmount);
    
    // Format input numbers
    spendAmount.addEventListener('input', formatNumberInput);
    
    // Action buttons
    document.querySelector('.topup-btn').addEventListener('click', handleTopUp);
    document.querySelector('.transfer-btn').addEventListener('click', handleTransfer);
}

// Switch between tabs
function switchTab(tab) {
    if (tab === 'topup') {
        topupTab.classList.add('active');
        transferTab.classList.remove('active');
        topupContent.classList.remove('hidden');
        transferContent.classList.add('hidden');
    } else {
        transferTab.classList.add('active');
        topupTab.classList.remove('active');
        transferContent.classList.remove('hidden');
        topupContent.classList.add('hidden');
    }
}

// Update receive amount based on spend amount
function updateReceiveAmount() {
    const spend = parseFloat(spendAmount.value.replace(/,/g, '')) || 0;
    const receive = spend / USDT_TO_IDR_RATE;
    receiveAmount.value = receive.toFixed(6);
}

// Format number input with commas
function formatNumberInput(e) {
    let value = e.target.value.replace(/,/g, '');
    if (!isNaN(value) && value !== '') {
        e.target.value = parseInt(value).toLocaleString();
    }
    updateReceiveAmount();
}

// Select cryptocurrency
function selectCrypto(crypto) {
    // Remove previous selection
    document.querySelectorAll('.crypto-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Add selection visual feedback
    event.currentTarget.classList.add('selected');
    
    // Update UI or perform actions based on selected crypto
    console.log('Selected crypto:', crypto.name);
    
    // Add visual feedback
    showNotification(`${crypto.name} selected`);
}

// Handle top up action
function handleTopUp() {
    const spend = spendAmount.value;
    const receive = receiveAmount.value;
    
    if (!spend || parseFloat(spend.replace(/,/g, '')) < 250000) {
        showNotification('Minimum top up amount is Rp 250,000', 'error');
        return;
    }
    
    // Simulate loading
    const btn = document.querySelector('.topup-btn');
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.textContent = originalText;
        btn.disabled = false;
        showNotification(`Top up successful! ${receive} USDT added to your wallet`, 'success');
        
        // Reset form
        spendAmount.value = '';
        receiveAmount.value = '0';
    }, 2000);
}

// Handle transfer action
function handleTransfer() {
    const toAddress = document.getElementById('toAddress').value;
    const amount = document.getElementById('transferAmount').value;
    
    if (!toAddress) {
        showNotification('Please enter a valid wallet address', 'error');
        return;
    }
    
    if (!amount || parseFloat(amount) <= 0) {
        showNotification('Please enter a valid amount', 'error');
        return;
    }
    
    // Validate address format (basic validation)
    if (toAddress.length < 26 || toAddress.length > 35) {
        showNotification('Invalid wallet address format', 'error');
        return;
    }
    
    // Simulate loading
    const btn = document.querySelector('.transfer-btn');
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.textContent = originalText;
        btn.disabled = false;
        showNotification(`Transfer successful! ${amount} USDT sent to ${toAddress.substring(0, 6)}...${toAddress.substring(toAddress.length - 4)}`, 'success');
        
        // Reset form
        document.getElementById('toAddress').value = '';
        document.getElementById('transferAmount').value = '';
    }, 3000);
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">
                ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
            </span>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Add notification styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'linear-gradient(45deg, #27ae60, #2ecc71)' : 
                    type === 'error' ? 'linear-gradient(45deg, #e74c3c, #c0392b)' : 
                    'linear-gradient(45deg, #3498db, #2980b9)'};
        color: white;
        padding: 1rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        z-index: 1000;
        max-width: 350px;
        animation: slideIn 0.3s ease;
    `;
    
    // Add CSS for animation
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .notification-close {
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                margin-left: auto;
                padding: 0.2rem;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .notification-close:hover {
                background: rgba(255,255,255,0.2);
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Add CSS class for selected crypto
const additionalStyles = `
    .crypto-item.selected {
        background: rgba(243, 156, 18, 0.2);
        border: 1px solid #f39c12;
        transform: translateX(10px);
    }
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Add smooth scrolling for mobile
if (window.innerWidth <= 768) {
    document.documentElement.style.scrollBehavior = 'smooth';
}

// Handle window resize
window.addEventListener('resize', () => {
    if (window.innerWidth <= 768) {
        document.documentElement.style.scrollBehavior = 'smooth';
    }
});