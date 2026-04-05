// Global variables
let currentSection = 'dashboard';
let chartInstance = null;

// DOM Elements
const navItems = document.querySelectorAll('.nav-item');
const contentSections = document.querySelectorAll('.content-section');
const pageTitle = document.getElementById('page-title');
const pageSubtitle = document.getElementById('page-subtitle');
const chatInput = document.getElementById('chatInput');
const sendButton = document.getElementById('sendMessage');

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeChart();
    initializeChatbot();
    startRealTimeUpdates();
    
    // Add loading animations
    document.body.classList.add('fade-in');
});

// Navigation functionality
function initializeNavigation() {
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('data-section');
            switchSection(section);
        });
    });
}

function switchSection(section) {
    // Update active navigation item
    navItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-section') === section) {
            item.classList.add('active');
        }
    });

    // Update content sections
    contentSections.forEach(contentSection => {
        contentSection.classList.remove('active');
        if (contentSection.id === section + '-section') {
            contentSection.classList.add('active');
            contentSection.classList.add('slide-up');
        }
    });

    // Update page title and subtitle
    updatePageHeader(section);
    currentSection = section;

    // Initialize section-specific functionality
    if (section === 'dashboard') {
        updateChart();
    }
}

function updatePageHeader(section) {
    const titles = {
        'dashboard': {
            title: 'Dashboard Overview',
            subtitle: 'Selamat datang kembali, Admin!'
        },
        'users': {
            title: 'Manajemen Pengguna',
            subtitle: 'Kelola data pengguna TravelSave'
        },
        'groups': {
            title: 'Grup Tabungan',
            subtitle: 'Monitor dan kelola grup tabungan'
        },
        'transactions': {
            title: 'Monitoring Transaksi',
            subtitle: 'Track semua transaksi keuangan'
        },
        'goals': {
            title: 'Target Travel',
            subtitle: 'Pantau progress target perjalanan'
        },
        'analytics': {
            title: 'Analytics & Insights',
            subtitle: 'Analisis mendalam data aplikasi'
        },
        'reports': {
            title: 'Laporan',
            subtitle: 'Generate laporan komprehensif'
        },
        'chatbot': {
            title: 'AI Assistant',
            subtitle: 'Customer support dengan AI'
        },
        'settings': {
            title: 'Pengaturan Sistem',
            subtitle: 'Konfigurasi aplikasi TravelSave'
        }
    };

    if (titles[section]) {
        pageTitle.textContent = titles[section].title;
        pageSubtitle.textContent = titles[section].subtitle;
    }
}

// Chart initialization and management
function initializeChart() {
    const ctx = document.getElementById('savingsChart');
    if (!ctx) return;

    const chartData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [{
            label: 'Total Tabungan (Miliar Rp)',
            data: [0.8, 0.9, 1.0, 1.1, 1.15, 1.18, 1.2],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(59, 130, 246)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5
        }, {
            label: 'Target Tercapai',
            data: [120, 125, 135, 142, 148, 152, 156],
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(16, 185, 129)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    };

    const config = {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            },
            elements: {
                point: {
                    hoverRadius: 8
                }
            }
        }
    };

    chartInstance = new Chart(ctx, config);
}

function updateChart() {
    if (!chartInstance) return;
    
    // Simulate real-time data update
    const newData = generateRandomData();
    chartInstance.data.datasets[0].data = newData.savings;
    chartInstance.data.datasets[1].data = newData.goals;
    chartInstance.update('active');
}

function generateRandomData() {
    const baseData = {
        savings: [0.8, 0.9, 1.0, 1.1, 1.15, 1.18, 1.2],
        goals: [120, 125, 135, 142, 148, 152, 156]
    };
    
    // Add small random variations
    return {
        savings: baseData.savings.map(val => val + (Math.random() - 0.5) * 0.05),
        goals: baseData.goals.map(val => Math.floor(val + (Math.random() - 0.5) * 10))
    };
}

// Chatbot functionality
function initializeChatbot() {
    if (chatInput && sendButton) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        sendButton.addEventListener('click', sendMessage);
    }
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;

    // Add user message to chat
    addMessageToChat('user', message);
    
    // Clear input
    chatInput.value = '';
    
    // Show typing indicator
    showTypingIndicator();
    
    // Simulate AI response after delay
    setTimeout(() => {
        hideTypingIndicator();
        generateAIResponse(message);
    }, 1500);
}

function addMessageToChat(sender, message) {
    const chatMessages = document.querySelector('.chat-messages');
    if (!chatMessages) return;

    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}-message`;
    
    const now = new Date();
    const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                      now.getMinutes().toString().padStart(2, '0');

    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${message}</p>
                <span class="message-time">${timeString}</span>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <p>${message}</p>
                <span class="message-time">${timeString}</span>
            </div>
        `;
    }

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTypingIndicator() {
    const typingIndicator = document.querySelector('.typing-indicator');
    if (typingIndicator) {
        typingIndicator.style.display = 'flex';
        const chatMessages = document.querySelector('.chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
}

function hideTypingIndicator() {
    const typingIndicator = document.querySelector('.typing-indicator');
    if (typingIndicator) {
        typingIndicator.style.display = 'none';
    }
}

function generateAIResponse(userMessage) {
    // Simple AI response logic (in production, this would connect to a real AI service)
    const responses = {
        'withdraw': 'Untuk melakukan withdraw tabungan, Anda perlu memastikan target sudah tercapai minimal 80%. Kemudian buka grup tabungan dan klik "Request Withdrawal". Proses biasanya memakan waktu 1-2 hari kerja.',
        'tabungan': 'TravelSave memungkinkan Anda untuk membuat grup tabungan bersama untuk tujuan perjalanan. Anda bisa mengundang teman dan keluarga untuk menabung bersama mencapai target yang ditentukan.',
        'grup': 'Untuk membuat grup baru, klik tombol "Buat Grup Baru" di dashboard, tentukan tujuan perjalanan, target dana, dan durasi menabung. Kemudian undang anggota untuk bergabung.',
        'help': 'Saya di sini untuk membantu Anda dengan pertanyaan seputar TravelSave. Anda bisa bertanya tentang cara menabung, withdraw, membuat grup, atau fitur lainnya.',
        'default': 'Terima kasih atas pertanyaan Anda. Tim customer service kami akan segera membantu Anda. Apakah ada hal lain yang bisa saya bantu?'
    };

    let response = responses.default;
    const lowerMessage = userMessage.toLowerCase();

    for (const keyword in responses) {
        if (lowerMessage.includes(keyword)) {
            response = responses[keyword];
            break;
        }
    }

    addMessageToChat('ai', response);
}

// Real-time updates
function startRealTimeUpdates() {
    // Update stats every 30 seconds
    setInterval(updateStats, 30000);
    
    // Update activities every 60 seconds
    setInterval(updateActivities, 60000);
    
    // Update chart every 2 minutes
    setInterval(updateChart, 120000);
}

function updateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const currentValue = stat.textContent;
        // Simulate small increases
        if (currentValue.includes('Rp')) {
            // Handle currency values
            const numValue = parseFloat(currentValue.replace(/[^0-9.]/g, ''));
            const newValue = (numValue * 1.001).toFixed(1);
            stat.textContent = currentValue.replace(numValue.toString(), newValue);
        } else if (!isNaN(currentValue.replace(/,/g, ''))) {
            // Handle numeric values
            const numValue = parseInt(currentValue.replace(/,/g, ''));
            const newValue = numValue + Math.floor(Math.random() * 3);
            stat.textContent = newValue.toLocaleString();
        }
    });
}

function updateActivities() {
    // This would typically fetch new activities from an API
    console.log('Updating activities...');
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(date);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Export functions for potential module use
window.TravelSaveDashboard = {
    switchSection,
    updateChart,
    sendMessage,
    formatCurrency,
    formatDate,
    showNotification
};

// Additional MIS Features

// Data Export functionality
function exportData(type) {
    const data = getMISData(type);
    const csv = convertToCSV(data);
    downloadCSV(csv, `travelsave-${type}-${new Date().toISOString().split('T')[0]}.csv`);
}

function getMISData(type) {
    // Simulate MIS data retrieval
    const mockData = {
        'users': [
            { id: 1, name: 'Sarah Johnson', email: 'sarah@email.com', joinDate: '2024-01-15', status: 'Active', totalSavings: 2500000 },
            { id: 2, name: 'Michael Chen', email: 'michael@email.com', joinDate: '2024-01-12', status: 'Active', totalSavings: 1800000 }
        ],
        'transactions': [
            { id: 1, userId: 1, amount: 500000, type: 'Deposit', date: '2024-01-20', status: 'Completed' },
            { id: 2, userId: 2, amount: 300000, type: 'Deposit', date: '2024-01-19', status: 'Completed' }
        ],
        'groups': [
            { id: 1, name: 'Bali Adventure 2024', members: 8, target: 15000000, current: 12750000, status: 'Active' },
            { id: 2, name: 'Japan Trip', members: 5, target: 25000000, current: 25000000, status: 'Completed' }
        ]
    };
    
    return mockData[type] || [];
}

function convertToCSV(data) {
    if (!data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvRows = [headers.join(',')];
    
    for (const row of data) {
        const values = headers.map(header => {
            const escaped = ('' + row[header]).replace(/"/g, '\\"');
            return `"${escaped}"`;
        });
        csvRows.push(values.join(','));
    }
    
    return csvRows.join('\n');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Performance monitoring
function trackPerformance() {
    if ('performance' in window) {
        const navigation = performance.getEntriesByType('navigation')[0];
        const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
        
        console.log(`Page load time: ${loadTime}ms`);
        
        // In production, you would send this data to your analytics service
        return {
            loadTime,
            domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
            firstPaint: performance.getEntriesByType('paint')[0]?.startTime || 0
        };
    }
}

// Initialize performance tracking
window.addEventListener('load', trackPerformance);

// Error handling and logging
window.addEventListener('error', function(e) {
    console.error('Application error:', e.error);
    // In production, send error details to logging service
    showNotification('Terjadi kesalahan. Silakan coba lagi.', 'error');
});

// Service worker registration for offline functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}