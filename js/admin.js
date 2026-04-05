class MISAdmin {
    constructor() {
        this.currentPage = 'dashboard';
        this.apiKey = localStorage.getItem('openai_api_key') || '';
        this.chatMessages = [];
        this.charts = {};
        this.currentEditingUser = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadStoredMessages();
        this.initializeCharts();
        this.loadSampleData();
        
        // Check if API key exists, if not show modal when AI Assistant is accessed
        if (this.apiKey) {
            this.updateChatStatus('online');
        }
    }

    setupEventListeners() {
        // Sidebar navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                this.switchPage(page);
            });
        });

        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        sidebarToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        // Notification dropdown
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        notificationIcon?.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('active');
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });

        // Mark all notifications as read
        document.querySelector('.mark-all-read')?.addEventListener('click', () => {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            document.querySelector('.notification-badge').textContent = '0';
        });

        // Chat functionality
        const sendButton = document.getElementById('sendButton');
        const chatInput = document.getElementById('chatInput');
        
        sendButton?.addEventListener('click', () => this.sendMessage());
        chatInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Suggestion buttons
        document.querySelectorAll('.suggestion-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const text = btn.dataset.text;
                document.getElementById('chatInput').value = text;
                this.sendMessage();
            });
        });

        // API Key modal
        const closeModal = document.getElementById('closeModal');
        const saveApiKey = document.getElementById('saveApiKey');
        
        closeModal?.addEventListener('click', () => this.closeModal());
        saveApiKey?.addEventListener('click', () => this.saveApiKey());

        // Transaction Detail Modal
        const closeTransactionModal = document.getElementById('closeTransactionModal');
        closeTransactionModal?.addEventListener('click', () => this.closeTransactionModal());

        // Edit User Modal
        const closeEditUserModal = document.getElementById('closeEditUserModal');
        const editUserForm = document.getElementById('editUserForm');
        
        closeEditUserModal?.addEventListener('click', () => this.closeEditUserModal());
        editUserForm?.addEventListener('submit', (e) => this.handleEditUserSubmit(e));

        // Time range selector
        const timeRange = document.getElementById('timeRange');
        timeRange?.addEventListener('change', (e) => {
            this.updateStats(e.target.value);
        });

        // Close modals on backdrop click
        document.getElementById('apiKeyModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'apiKeyModal') {
                this.closeModal();
            }
        });

        document.getElementById('transactionDetailModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'transactionDetailModal') {
                this.closeTransactionModal();
            }
        });

        document.getElementById('editUserModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'editUserModal') {
                this.closeEditUserModal();
            }
        });

        // User search and filter
        const userSearch = document.getElementById('userSearch');
        const userFilter = document.getElementById('userFilter');
        
        userSearch?.addEventListener('input', (e) => this.filterUsers(e.target.value));
        userFilter?.addEventListener('change', (e) => this.filterUsersByStatus(e.target.value));

        // Transaction filters
        const transactionType = document.getElementById('transactionType');
        const transactionDate = document.getElementById('transactionDate');
        
        transactionType?.addEventListener('change', (e) => this.filterTransactions());
        transactionDate?.addEventListener('change', (e) => this.filterTransactions());

        // Analytics timeframe
        const analyticsTimeframe = document.getElementById('analyticsTimeframe');
        analyticsTimeframe?.addEventListener('change', (e) => this.updateAnalytics(e.target.value));

        // Settings
        const saveApiKeyBtn = document.getElementById('saveApiKeyBtn');
        saveApiKeyBtn?.addEventListener('click', () => this.saveSettingsApiKey());
    }

    switchPage(pageName) {
        // Update active nav item
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-page="${pageName}"]`).classList.add('active');

        // Show corresponding page
        document.querySelectorAll('.page-content').forEach(page => {
            page.classList.remove('active');
        });
        document.getElementById(`${pageName}-page`).classList.add('active');

        // Update page title
        const pageTitle = document.querySelector('.page-title');
        const pageTitles = {
            'dashboard': 'Dashboard',
            'users': 'Manajemen User',
            'savings': 'Grup Tabungan',
            'transactions': 'Transaksi',
            'targets': 'Target Travel',
            'analytics': 'Analytics',
            'reports': 'Laporan',
            'ai-assistant': 'AI Assistant',
            'settings': 'Pengaturan'
        };
        pageTitle.textContent = pageTitles[pageName] || 'Dashboard';

        this.currentPage = pageName;

        // Show API key modal if accessing AI Assistant without API key
        if (pageName === 'ai-assistant' && !this.apiKey) {
            this.showApiKeyModal();
        }

        // Load page-specific data
        this.loadPageData(pageName);
    }

    loadPageData(pageName) {
        switch(pageName) {
            case 'users':
                this.loadUsersData();
                break;
            case 'savings':
                this.loadSavingsData();
                break;
            case 'transactions':
                this.loadTransactionsData();
                break;
            case 'targets':
                this.loadTargetsData();
                break;
            case 'analytics':
                this.loadAnalyticsData();
                break;
            case 'reports':
                this.loadReportsData();
                break;
        }
    }

    initializeCharts() {
        // Dashboard Charts
        this.initUserGrowthChart();
        this.initTransactionChart();
        this.initSavingsDistributionChart();
        this.initTargetProgressChart();
        this.initTransactionTrendChart();
        this.initAnalyticsCharts();
    }

    initUserGrowthChart() {
        const ctx = document.getElementById('userGrowthChart');
        if (!ctx) return;

        this.charts.userGrowth = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'User Growth',
                    data: [1200, 1450, 1800, 2100, 2400, 2847],
                    borderColor: '#000000',
                    backgroundColor: 'rgba(0, 0, 0, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#000000'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    },
                    y: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    }
                }
            }
        });
    }

    initTransactionChart() {
        const ctx = document.getElementById('transactionChart');
        if (!ctx) return;

        this.charts.transaction = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Transactions',
                    data: [120, 190, 300, 500, 200, 300, 450],
                    backgroundColor: '#2d3748',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#000000'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    },
                    y: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    }
                }
            }
        });
    }

    initSavingsDistributionChart() {
        const ctx = document.getElementById('savingsDistributionChart');
        if (!ctx) return;

        this.charts.savingsDistribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Travel Domestik', 'Travel Internasional', 'Umroh/Haji', 'Lainnya'],
                datasets: [{
                    data: [35, 25, 30, 10],
                    backgroundColor: ['#000000', '#2d3748', '#4a5568', '#718096'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#000000',
                            padding: 20
                        }
                    }
                }
            }
        });
    }

    initTargetProgressChart() {
        const ctx = document.getElementById('targetProgressChart');
        if (!ctx) return;

        this.charts.targetProgress = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Tercapai', 'Dalam Progress', 'Belum Dimulai'],
                datasets: [{
                    label: 'Target Status',
                    data: [847, 1234, 456],
                    backgroundColor: ['#000000', '#2d3748', '#4a5568'],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    },
                    y: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    }
                }
            }
        });
    }

    initTransactionTrendChart() {
        const ctx = document.getElementById('transactionTrendChart');
        if (!ctx) return;

        this.charts.transactionTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Income',
                    data: [25000000, 30000000, 28000000, 35000000],
                    borderColor: '#000000',
                    backgroundColor: 'rgba(0, 0, 0, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Expense',
                    data: [18000000, 22000000, 20000000, 25000000],
                    borderColor: '#4a5568',
                    backgroundColor: 'rgba(74, 85, 104, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#000000'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#2d3748' },
                        grid: { color: '#4a5568' }
                    },
                    y: {
                        ticks: { 
                            color: '#2d3748',
                            callback: function(value) {
                                return 'Rp ' + (value / 1000000) + 'M';
                            }
                        },
                        grid: { color: '#4a5568' }
                    }
                }
            }
        });
    }

    initAnalyticsCharts() {
        // User Status Chart
        const userStatusCtx = document.getElementById('userStatusChart');
        if (userStatusCtx) {
            this.charts.userStatus = new Chart(userStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Online', 'Offline', 'Away'],
                    datasets: [{
                        data: [1245, 1356, 246],
                        backgroundColor: ['#000000', '#2d3748', '#4a5568'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#000000'
                            }
                        }
                    }
                }
            });
        }

        // Daily Activity Chart
        const dailyActivityCtx = document.getElementById('dailyActivityChart');
        if (dailyActivityCtx) {
            this.charts.dailyActivity = new Chart(dailyActivityCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'Active Users',
                        data: [120, 80, 450, 890, 1200, 800],
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#000000'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#2d3748' },
                            grid: { color: '#4a5568' }
                        },
                        y: {
                            ticks: { color: '#2d3748' },
                            grid: { color: '#4a5568' }
                        }
                    }
                }
            });
        }

        // Demographics Chart
        const demographicsCtx = document.getElementById('demographicsChart');
        if (demographicsCtx) {
            this.charts.demographics = new Chart(demographicsCtx, {
                type: 'bar',
                data: {
                    labels: ['18-25', '26-35', '36-45', '46-55', '55+'],
                    datasets: [{
                        label: 'Age Groups',
                        data: [450, 890, 650, 320, 180],
                        backgroundColor: '#2d3748',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#000000'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#2d3748' },
                            grid: { color: '#4a5568' }
                        },
                        y: {
                            ticks: { color: '#2d3748' },
                            grid: { color: '#4a5568' }
                        }
                    }
                }
            });
        }
    }

    loadSampleData() {
        this.sampleUsers = [
            { id: 1, name: 'Ahmad Rizki', email: 'ahmad@email.com', status: 'active', joined: '2024-01-15', savings: 2500000 },
            { id: 2, name: 'Siti Nurhaliza', email: 'siti@email.com', status: 'premium', joined: '2024-02-20', savings: 5000000 },
            { id: 3, name: 'Budi Santoso', email: 'budi@email.com', status: 'inactive', joined: '2024-03-10', savings: 1200000 },
            { id: 4, name: 'Maya Sari', email: 'maya@email.com', status: 'active', joined: '2024-01-05', savings: 3200000 },
            { id: 5, name: 'Dedi Kurniawan', email: 'dedi@email.com', status: 'premium', joined: '2024-02-28', savings: 7500000 },
            { id: 6, name: 'Rina Wati', email: 'rina@email.com', status: 'active', joined: '2024-03-01', savings: 1800000 },
            { id: 7, name: 'Joko Widodo', email: 'joko@email.com', status: 'inactive', joined: '2024-01-20', savings: 950000 },
            { id: 8, name: 'Ani Susanti', email: 'ani@email.com', status: 'premium', joined: '2024-02-15', savings: 4200000 }
        ];

        this.sampleTransactions = [
            { id: 'TRX001', user: 'Ahmad Rizki', type: 'deposit', amount: 500000, bank: 'BCA', status: 'completed', date: '2024-03-15', description: 'Deposit untuk tabungan Bali' },
            { id: 'TRX002', user: 'Siti Nurhaliza', type: 'withdrawal', amount: 1000000, bank: 'Mandiri', status: 'pending', date: '2024-03-14', description: 'Penarikan untuk pembayaran travel' },
            { id: 'TRX003', user: 'Budi Santoso', type: 'transfer', amount: 250000, bank: 'GoPay', status: 'completed', date: '2024-03-13', description: 'Transfer ke grup tabungan' },
            { id: 'TRX004', user: 'Maya Sari', type: 'deposit', amount: 750000, bank: 'BRI', status: 'completed', date: '2024-03-12', description: 'Deposit bulanan' },
            { id: 'TRX005', user: 'Dedi Kurniawan', type: 'withdrawal', amount: 2000000, bank: 'OVO', status: 'failed', date: '2024-03-11', description: 'Penarikan gagal - saldo tidak cukup' },
            { id: 'TRX006', user: 'Rina Wati', type: 'deposit', amount: 300000, bank: 'DANA', status: 'completed', date: '2024-03-10', description: 'Deposit via DANA' },
            { id: 'TRX007', user: 'Joko Widodo', type: 'transfer', amount: 150000, bank: 'BNI', status: 'completed', date: '2024-03-09', description: 'Transfer antar rekening' },
            { id: 'TRX008', user: 'Ani Susanti', type: 'withdrawal', amount: 800000, bank: 'ShopeePay', status: 'pending', date: '2024-03-08', description: 'Penarikan untuk booking hotel' }
        ];

        this.sampleGroups = [
            { id: 1, name: 'Bali Adventure 2024', members: 12, target: 15000000, current: 8500000, progress: 57 },
            { id: 2, name: 'Umroh Keluarga', members: 8, target: 25000000, current: 18000000, progress: 72 },
            { id: 3, name: 'Japan Trip', members: 15, target: 30000000, current: 12000000, progress: 40 },
            { id: 4, name: 'Lombok Backpacker', members: 6, target: 8000000, current: 6500000, progress: 81 },
            { id: 5, name: 'Europe Tour', members: 10, target: 45000000, current: 22000000, progress: 49 },
            { id: 6, name: 'Singapore Weekend', members: 4, target: 6000000, current: 5200000, progress: 87 }
        ];

        this.sampleTargets = [
            { id: 1, destination: 'Bali', user: 'Ahmad Rizki', target: 5000000, current: 2500000, status: 'in-progress', deadline: '2024-12-31' },
            { id: 2, destination: 'Makkah', user: 'Siti Nurhaliza', target: 25000000, current: 25000000, status: 'achieved', deadline: '2024-06-15' },
            { id: 3, destination: 'Tokyo', user: 'Maya Sari', target: 15000000, current: 3200000, status: 'in-progress', deadline: '2025-03-20' },
            { id: 4, destination: 'Paris', user: 'Dedi Kurniawan', target: 20000000, current: 0, status: 'pending', deadline: '2025-06-10' },
            { id: 5, destination: 'Seoul', user: 'Rina Wati', target: 12000000, current: 1800000, status: 'in-progress', deadline: '2024-11-30' },
            { id: 6, destination: 'London', user: 'Ani Susanti', target: 18000000, current: 4200000, status: 'in-progress', deadline: '2025-04-15' }
        ];

        this.sampleReports = [
            { name: 'Laporan User Bulanan', type: 'users', date: '2024-03-01', size: '2.5 MB' },
            { name: 'Laporan Keuangan Q1', type: 'financial', date: '2024-03-31', size: '1.8 MB' },
            { name: 'Laporan Target Travel', type: 'targets', date: '2024-03-15', size: '3.2 MB' },
            { name: 'Analytics Dashboard', type: 'analytics', date: '2024-03-10', size: '4.1 MB' }
        ];

        // Store original data for filtering
        this.originalUsers = [...this.sampleUsers];
        this.originalTransactions = [...this.sampleTransactions];
    }

    loadUsersData() {
        this.displayUsers(this.sampleUsers);
    }

    displayUsers(users) {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td><span class="status-badge ${user.status}">${this.getStatusText(user.status)}</span></td>
                <td>${new Date(user.joined).toLocaleDateString('id-ID')}</td>
                <td>Rp ${user.savings.toLocaleString('id-ID')}</td>
                <td>
                    <button class="btn-secondary" onclick="misAdmin.editUser(${user.id})">Edit</button>
                    <button class="btn-danger" onclick="misAdmin.deleteUser(${user.id})">Hapus</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    loadSavingsData() {
        const grid = document.getElementById('groupsGrid');
        if (!grid) return;

        grid.innerHTML = '';
        this.sampleGroups.forEach(group => {
            const card = document.createElement('div');
            card.className = 'group-card';
            card.innerHTML = `
                <div class="group-header">
                    <h4 class="group-name">${group.name}</h4>
                    <span class="group-members">${group.members} anggota</span>
                </div>
                <div class="group-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${group.progress}%"></div>
                    </div>
                    <p>${group.progress}% tercapai</p>
                </div>
                <div class="group-amount">
                    Rp ${group.current.toLocaleString('id-ID')} / Rp ${group.target.toLocaleString('id-ID')}
                </div>
            `;
            grid.appendChild(card);
        });
    }

    loadTransactionsData() {
        this.displayTransactions(this.sampleTransactions);
    }

    displayTransactions(transactions) {
        const tbody = document.getElementById('transactionsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.id}</td>
                <td>${transaction.user}</td>
                <td>${this.getTransactionTypeText(transaction.type)}</td>
                <td>Rp ${transaction.amount.toLocaleString('id-ID')}</td>
                <td><span class="bank-badge ${transaction.bank.toLowerCase()}">${transaction.bank}</span></td>
                <td><span class="status-badge ${transaction.status}">${this.getTransactionStatusText(transaction.status)}</span></td>
                <td>${new Date(transaction.date).toLocaleDateString('id-ID')}</td>
                <td>
                    <button class="btn-secondary" onclick="misAdmin.viewTransactionDetail('${transaction.id}')">Detail</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    loadTargetsData() {
        const grid = document.getElementById('targetsGrid');
        if (!grid) return;

        grid.innerHTML = '';
        this.sampleTargets.forEach(target => {
            const progress = Math.round((target.current / target.target) * 100);
            const card = document.createElement('div');
            card.className = 'target-card';
            card.innerHTML = `
                <div class="target-header">
                    <h4 class="target-destination">${target.destination}</h4>
                    <span class="target-status ${target.status}">${this.getTargetStatusText(target.status)}</span>
                </div>
                <p><strong>User:</strong> ${target.user}</p>
                <div class="group-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress}%"></div>
                    </div>
                    <p>${progress}% tercapai</p>
                </div>
                <div class="group-amount">
                    Rp ${target.current.toLocaleString('id-ID')} / Rp ${target.target.toLocaleString('id-ID')}
                </div>
                <p><strong>Deadline:</strong> ${new Date(target.deadline).toLocaleDateString('id-ID')}</p>
            `;
            grid.appendChild(card);
        });
    }

    loadAnalyticsData() {
        // Analytics data is handled by charts
        console.log('Analytics data loaded');
    }

    loadReportsData() {
        const list = document.getElementById('recentReportsList');
        if (!list) return;

        list.innerHTML = '';
        this.sampleReports.forEach(report => {
            const item = document.createElement('div');
            item.className = 'report-item';
            item.innerHTML = `
                <div class="report-item-info">
                    <h4 class="report-item-name">${report.name}</h4>
                    <p class="report-item-date">Generated on ${new Date(report.date).toLocaleDateString('id-ID')} • ${report.size}</p>
                </div>
                <button class="report-item-download" onclick="misAdmin.downloadReport('${report.type}')">
                    <i class="fas fa-download"></i>
                </button>
            `;
            list.appendChild(item);
        });
    }

    // Utility functions
    getStatusText(status) {
        const statusMap = {
            'active': 'Aktif',
            'inactive': 'Tidak Aktif',
            'premium': 'Premium'
        };
        return statusMap[status] || status;
    }

    getTransactionTypeText(type) {
        const typeMap = {
            'deposit': 'Deposit',
            'withdrawal': 'Penarikan',
            'transfer': 'Transfer'
        };
        return typeMap[type] || type;
    }

    getTransactionStatusText(status) {
        const statusMap = {
            'completed': 'Selesai',
            'pending': 'Pending',
            'failed': 'Gagal'
        };
        return statusMap[status] || status;
    }

    getTargetStatusText(status) {
        const statusMap = {
            'achieved': 'Tercapai',
            'in-progress': 'Progress',
            'pending': 'Pending'
        };
        return statusMap[status] || status;
    }

    // Filter functions
    filterUsers(searchTerm) {
        const filteredUsers = this.originalUsers.filter(user => 
            user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.email.toLowerCase().includes(searchTerm.toLowerCase())
        );
        this.displayUsers(filteredUsers);
    }

    filterUsersByStatus(status) {
        const filteredUsers = status === 'all' ? 
            this.originalUsers : 
            this.originalUsers.filter(user => user.status === status);
        this.displayUsers(filteredUsers);
    }

    filterTransactions() {
        const type = document.getElementById('transactionType').value;
        const date = document.getElementById('transactionDate').value;
        
        let filteredTransactions = this.originalTransactions;
        
        if (type !== 'all') {
            filteredTransactions = filteredTransactions.filter(t => t.type === type);
        }
        
        if (date) {
            filteredTransactions = filteredTransactions.filter(t => t.date === date);
        }
        
        this.displayTransactions(filteredTransactions);
    }

    updateAnalytics(timeframe) {
        // Update analytics charts based on timeframe
        console.log('Updating analytics for timeframe:', timeframe);
        // This would typically fetch new data and update charts
    }

    // CRUD Operations
    editUser(userId) {
        const user = this.sampleUsers.find(u => u.id === userId);
        if (!user) return;

        this.currentEditingUser = user;
        
        // Populate form
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserStatus').value = user.status;
        document.getElementById('editUserSavings').value = user.savings;
        
        // Show modal
        document.getElementById('editUserModal').classList.add('active');
    }

    handleEditUserSubmit(e) {
        e.preventDefault();
        
        if (!this.currentEditingUser) return;
        
        // Get form data
        const name = document.getElementById('editUserName').value;
        const email = document.getElementById('editUserEmail').value;
        const status = document.getElementById('editUserStatus').value;
        const savings = parseInt(document.getElementById('editUserSavings').value);
        
        // Update user data
        const userIndex = this.sampleUsers.findIndex(u => u.id === this.currentEditingUser.id);
        if (userIndex !== -1) {
            this.sampleUsers[userIndex] = {
                ...this.sampleUsers[userIndex],
                name,
                email,
                status,
                savings
            };
            
            // Update original data too
            const originalIndex = this.originalUsers.findIndex(u => u.id === this.currentEditingUser.id);
            if (originalIndex !== -1) {
                this.originalUsers[originalIndex] = { ...this.sampleUsers[userIndex] };
            }
        }
        
        // Refresh display
        this.loadUsersData();
        
        // Close modal
        this.closeEditUserModal();
        
        // Show success message
        this.showNotification('User berhasil diupdate!', 'success');
    }

    deleteUser(userId) {
        if (!confirm('Apakah Anda yakin ingin menghapus user ini?')) return;
        
        // Remove from both arrays
        this.sampleUsers = this.sampleUsers.filter(u => u.id !== userId);
        this.originalUsers = this.originalUsers.filter(u => u.id !== userId);
        
        // Refresh display
        this.loadUsersData();
        
        // Show success message
        this.showNotification('User berhasil dihapus!', 'success');
    }

    viewTransactionDetail(transactionId) {
        const transaction = this.sampleTransactions.find(t => t.id === transactionId);
        if (!transaction) return;

        const content = document.getElementById('transactionDetailContent');
        content.innerHTML = `
            <div class="transaction-detail">
                <div class="detail-row">
                    <span class="detail-label">ID Transaksi:</span>
                    <span class="detail-value">${transaction.id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">User:</span>
                    <span class="detail-value">${transaction.user}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tipe Transaksi:</span>
                    <span class="detail-value">${this.getTransactionTypeText(transaction.type)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jumlah:</span>
                    <span class="detail-value amount">Rp ${transaction.amount.toLocaleString('id-ID')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bank/E-Wallet:</span>
                    <span class="bank-badge ${transaction.bank.toLowerCase()}">${transaction.bank}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge ${transaction.status}">${this.getTransactionStatusText(transaction.status)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tanggal:</span>
                    <span class="detail-value">${new Date(transaction.date).toLocaleDateString('id-ID')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Deskripsi:</span>
                    <span class="detail-value">${transaction.description}</span>
                </div>
            </div>
        `;

        document.getElementById('transactionDetailModal').classList.add('active');
    }

    downloadReport(reportType) {
        console.log('Download report:', reportType);
        this.showNotification('Laporan sedang diunduh...', 'success');
    }

    // Modal functions
    showApiKeyModal() {
        document.getElementById('apiKeyModal').classList.add('active');
    }

    closeModal() {
        document.getElementById('apiKeyModal').classList.remove('active');
    }

    closeTransactionModal() {
        document.getElementById('transactionDetailModal').classList.remove('active');
    }

    closeEditUserModal() {
        document.getElementById('editUserModal').classList.remove('active');
        this.currentEditingUser = null;
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    saveApiKey() {
        const apiKeyInput = document.getElementById('apiKeyInput');
        const apiKey = apiKeyInput.value.trim();

        if (!apiKey) {
            this.showNotification('Silakan masukkan API Key yang valid.', 'error');
            return;
        }

        if (!apiKey.startsWith('sk-')) {
            this.showNotification('Format API Key tidak valid. API Key harus dimulai dengan "sk-"', 'error');
            return;
        }

        this.apiKey = apiKey;
        localStorage.setItem('openai_api_key', apiKey);
        this.closeModal();
        this.updateChatStatus('online');
        this.showNotification('API Key berhasil disimpan!', 'success');
        
        // Clear input
        apiKeyInput.value = '';
    }

    updateChatStatus(status) {
        const statusIndicator = document.querySelector('.status-indicator');
        const statusText = statusIndicator?.nextElementSibling;
        
        if (statusIndicator && statusText) {
            statusIndicator.className = `status-indicator ${status}`;
            statusText.textContent = status === 'online' ? 'Online' : 'Offline';
        }
    }

    async sendMessage() {
        const chatInput = document.getElementById('chatInput');
        const message = chatInput.value.trim();

        if (!message) return;

        if (!this.apiKey) {
            this.showApiKeyModal();
            return;
        }

        // Add user message to chat
        this.addMessageToChat(message, 'user');
        chatInput.value = '';

        // Disable send button and show typing indicator
        const sendButton = document.getElementById('sendButton');
        sendButton.disabled = true;
        this.showTypingIndicator();

        try {
            const response = await this.callOpenAI(message);
            this.hideTypingIndicator();
            this.addMessageToChat(response, 'ai');
        } catch (error) {
            this.hideTypingIndicator();
            console.error('Error calling OpenAI:', error);
            this.addMessageToChat('Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.', 'ai');
        } finally {
            sendButton.disabled = false;
        }

        this.saveMessages();
    }

    async callOpenAI(message) {
        const contextMessage = this.buildContextMessage();
        
        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.apiKey}`
            },
            body: JSON.stringify({
                model: 'gpt-3.5-turbo',
                messages: [
                    {
                        role: 'system',
                        content: contextMessage
                    },
                    {
                        role: 'user',
                        content: message
                    }
                ],
                max_tokens: 500,
                temperature: 0.7
            })
        });

        if (!response.ok) {
            if (response.status === 401) {
                throw new Error('API Key tidak valid');
            } else if (response.status === 429) {
                throw new Error('Terlalu banyak permintaan, coba lagi nanti');
            } else {
                throw new Error('Gagal menghubungi OpenAI API');
            }
        }

        const data = await response.json();
        return data.choices[0].message.content;
    }

    buildContextMessage() {
        return `Anda adalah AI Assistant untuk sistem MIS (Management Information System) admin travel. 
        Anda membantu admin dalam mengelola:
        - User management (Total users saat ini: 2,847 dengan pertumbuhan +12.5%)
        - Grup tabungan aktif (1,256 grup dengan pertumbuhan +8.2%)
        - Total tabungan (Rp 45.2M dengan pertumbuhan +15.3%)
        - Target travel tercapai (847 target dengan pertumbuhan +23.1%)
        - Analytics dan laporan
        - Transaksi keuangan digital
        
        Berikan jawaban yang profesional, informatif, dan membantu dalam bahasa Indonesia.
        Fokus pada analisis data, memberikan insight, dan saran actionable untuk meningkatkan performa bisnis travel.`;
    }

    addMessageToChat(message, sender) {
        const chatMessages = document.getElementById('chatMessages');
        const messageElement = document.createElement('div');
        messageElement.className = `message ${sender}-message`;

        const currentTime = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const avatarIcon = sender === 'ai' ? 'fas fa-robot' : 'fas fa-user';

        messageElement.innerHTML = `
            <div class="message-avatar">
                <i class="${avatarIcon}"></i>
            </div>
            <div class="message-content">
                <p>${this.formatMessage(message)}</p>
                <span class="message-time">${currentTime}</span>
            </div>
        `;

        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Store message
        this.chatMessages.push({
            message,
            sender,
            timestamp: new Date().toISOString()
        });
    }

    formatMessage(message) {
        // Basic formatting for better readability
        return message
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
    }

    showTypingIndicator() {
        const chatMessages = document.getElementById('chatMessages');
        const typingElement = document.createElement('div');
        typingElement.className = 'message ai-message typing-message';
        typingElement.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;

        chatMessages.appendChild(typingElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    hideTypingIndicator() {
        const typingMessage = document.querySelector('.typing-message');
        if (typingMessage) {
            typingMessage.remove();
        }
    }

    saveMessages() {
        localStorage.setItem('chat_messages', JSON.stringify(this.chatMessages));
    }

    loadStoredMessages() {
        const stored = localStorage.getItem('chat_messages');
        if (stored) {
            this.chatMessages = JSON.parse(stored);
            this.renderStoredMessages();
        }
    }

    renderStoredMessages() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;

        // Clear existing messages except welcome message
        const welcomeMessage = chatMessages.querySelector('.ai-message');
        chatMessages.innerHTML = '';
        if (welcomeMessage) {
            chatMessages.appendChild(welcomeMessage);
        }

        // Render stored messages
        this.chatMessages.forEach(msg => {
            this.addMessageToChat(msg.message, msg.sender);
        });
    }

    updateStats(timeRange) {
        // Simulate stats update based on time range
        const stats = {
            7: {
                users: { value: '2,847', change: '+12.5%' },
                groups: { value: '1,256', change: '+8.2%' },
                savings: { value: 'Rp 45.2M',  change: '+15.3%' },
                targets: { value: '847', change: '+23.1%' }
            },
            30: {
                users: { value: '2,654', change: '+18.7%' },
                groups: { value: '1,189', change: '+12.4%' },
                savings: { value: 'Rp 41.8M', change: '+22.1%' },
                targets: { value: '756', change: '+28.9%' }
            },
            90: {
                users: { value: '2,341', change: '+25.3%' },
                groups: { value: '1,067', change: '+19.6%' },
                savings: { value: 'Rp 38.5M', change: '+31.2%' },
                targets: { value: '623', change: '+35.4%' }
            }
        };

        const selectedStats = stats[timeRange];
        if (selectedStats) {
            // Update the stat cards with new data
            const statCards = document.querySelectorAll('.stat-card');
            const statKeys = ['users', 'groups', 'savings', 'targets'];
            
            statCards.forEach((card, index) => {
                const statKey = statKeys[index];
                const stat = selectedStats[statKey];
                
                const numberElement = card.querySelector('.stat-number');
                const changeElement = card.querySelector('.stat-change');
                
                if (numberElement && changeElement && stat) {
                    numberElement.textContent = stat.value;
                    changeElement.textContent = stat.change;
                }
            });
        }
    }

    saveSettingsApiKey() {
        const apiKeyInput = document.getElementById('openaiApiKey');
        const apiKey = apiKeyInput.value.trim();

        if (!apiKey) {
            this.showNotification('Silakan masukkan API Key yang valid.', 'error');
            return;
        }

        if (!apiKey.startsWith('sk-')) {
            this.showNotification('Format API Key tidak valid. API Key harus dimulai dengan "sk-"', 'error');
            return;
        }

        this.apiKey = apiKey;
        localStorage.setItem('openai_api_key', apiKey);
        this.updateChatStatus('online');
        this.showNotification('API Key berhasil disimpan!', 'success');
        
        // Clear input
        apiKeyInput.value = '';
    }

    // Utility methods for future enhancements
    exportChatHistory() {
        const dataStr = JSON.stringify(this.chatMessages, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const exportFileDefaultName = `chat-history-${new Date().toISOString().split('T')[0]}.json`;
        
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        linkElement.click();
    }

    clearChatHistory() {
        if (confirm('Apakah Anda yakin ingin menghapus semua riwayat chat?')) {
            this.chatMessages = [];
            localStorage.removeItem('chat_messages');
            this.renderStoredMessages();
        }
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    const misAdmin = new MISAdmin();
    
    // Make it globally accessible for debugging
    window.misAdmin = misAdmin;
});

// Performance monitoring
window.addEventListener('load', () => {
    const loadTime = performance.now();
    console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
});

// Error handling
window.addEventListener('error', (e) => {
    console.error('Global error:', e.error);
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K for quick search (future feature)
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        // Implement quick search functionality
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal.active');
        if (modal) {
            modal.classList.remove('active');
        }
        
        const dropdown = document.querySelector('.notification-dropdown.active');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }
});