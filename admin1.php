<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelSave - Admin Dashboard</title>
    <link rel="stylesheet" href="css/styles1.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-plane"></i>
                    <div>
                        <h1>TravelSave</h1>
                        <p>Admin Dashboard</p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item" data-section="users">
                    <i class="fas fa-users"></i>
                    <span>Manajemen User</span>
                </a>
                <a href="#" class="nav-item" data-section="groups">
                    <i class="fas fa-wallet"></i>
                    <span>Grup Tabungan</span>
                </a>
                <a href="#" class="nav-item" data-section="transactions">
                    <i class="fas fa-credit-card"></i>
                    <span>Transaksi</span>
                </a>
                <a href="#" class="nav-item" data-section="goals">
                    <i class="fas fa-target"></i>
                    <span>Target Travel</span>
                </a>
                <a href="#" class="nav-item" data-section="analytics">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <a href="#" class="nav-item" data-section="reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Laporan</span>
                </a>
                <a href="#" class="nav-item" data-section="chatbot">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
                </a>
                <a href="#" class="nav-item" data-section="settings">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h2 id="page-title">Dashboard Overview</h2>
                    <p id="page-subtitle">Selamat datang kembali, Admin!</p>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    <div class="user-profile">
                        <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="Admin">
                        <span>Admin</span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="content-area">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section active">
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card blue">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Total Users</h3>
                                <div class="stat-number">2,847</div>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+12.5%</span>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card green">
                            <div class="stat-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Grup Aktif</h3>
                                <div class="stat-number">342</div>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+8.2%</span>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card purple">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Total Tabungan</h3>
                                <div class="stat-number">Rp 1.2B</div>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+15.3%</span>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card orange">
                            <div class="stat-icon">
                                <i class="fas fa-target"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Target Tercapai</h3>
                                <div class="stat-number">156</div>
                                <div class="stat-change positive">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>+22.1%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Activities -->
                    <div class="dashboard-grid">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3>Statistik Tabungan</h3>
                                <div class="card-actions">
                                    <select class="time-filter">
                                        <option>7 Hari Terakhir</option>
                                        <option>30 Hari Terakhir</option>
                                        <option>3 Bulan Terakhir</option>
                                    </select>
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="savingsChart" width="400" height="200"></canvas>
                            </div>
                        </div>

                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3>Aktivitas Terbaru</h3>
                            </div>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon green">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong>Sarah Johnson</strong> membuat grup "Bali Adventure 2024"</p>
                                        <span class="activity-time">2 jam yang lalu</span>
                                    </div>
                                    <div class="activity-amount">Rp 2.500.000</div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon blue">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong>Michael Chen</strong> menyelesaikan kontribusi bulanan</p>
                                        <span class="activity-time">4 jam yang lalu</span>
                                    </div>
                                    <div class="activity-amount">Rp 500.000</div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon purple">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong>Emily Davis</strong> mencapai target "Japan Trip"</p>
                                        <span class="activity-time">6 jam yang lalu</span>
                                    </div>
                                    <div class="activity-amount">Rp 15.000.000</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Destinations -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Destinasi Populer</h3>
                        </div>
                        <div class="destinations-grid">
                            <div class="destination-item">
                                <img src="https://images.pexels.com/photos/2474690/pexels-photo-2474690.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&fit=crop" alt="Bali">
                                <div class="destination-info">
                                    <h4>Bali, Indonesia</h4>
                                    <p>45 grup • Rp 125M</p>
                                </div>
                            </div>
                            <div class="destination-item">
                                <img src="https://images.pexels.com/photos/2506923/pexels-photo-2506923.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&fit=crop" alt="Tokyo">
                                <div class="destination-info">
                                    <h4>Tokyo, Japan</h4>
                                    <p>32 grup • Rp 89M</p>
                                </div>
                            </div>
                            <div class="destination-item">
                                <img src="https://images.pexels.com/photos/338515/pexels-photo-338515.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&fit=crop" alt="Paris">
                                <div class="destination-info">
                                    <h4>Paris, France</h4>
                                    <p>28 grup • Rp 76M</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div id="users-section" class="content-section">
                    <div class="section-header">
                        <h3>Manajemen Pengguna</h3>
                        <button class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Tambah User
                        </button>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="table-controls">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Cari pengguna...">
                            </div>
                            <div class="filter-controls">
                                <select>
                                    <option>Semua Status</option>
                                    <option>Aktif</option>
                                    <option>Tidak Aktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Email</th>
                                        <th>Bergabung</th>
                                        <th>Status</th>
                                        <th>Total Tabungan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <img src="https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="User">
                                                <span>Sarah Johnson</span>
                                            </div>
                                        </td>
                                        <td>sarah.j@email.com</td>
                                        <td>15 Jan 2024</td>
                                        <td><span class="status-badge active">Aktif</span></td>
                                        <td>Rp 2.500.000</td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <img src="https://images.pexels.com/photos/91227/pexels-photo-91227.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="User">
                                                <span>Michael Chen</span>
                                            </div>
                                        </td>
                                        <td>michael.c@email.com</td>
                                        <td>12 Jan 2024</td>
                                        <td><span class="status-badge active">Aktif</span></td>
                                        <td>Rp 1.800.000</td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Chatbot Section -->
                <div id="chatbot-section" class="content-section">
                    <div class="section-header">
                        <h3>AI Assistant - Customer Support</h3>
                        <div class="chatbot-status">
                            <div class="status-indicator active"></div>
                            <span>AI Online</span>
                        </div>
                    </div>
                    
                    <div class="chatbot-container">
                        <div class="chatbot-sidebar">
                            <div class="chat-header">
                                <h4>Conversations</h4>
                                <button class="btn-secondary">New Chat</button>
                            </div>
                            <div class="chat-list">
                                <div class="chat-item active">
                                    <div class="chat-avatar">
                                        <img src="https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="User">
                                    </div>
                                    <div class="chat-info">
                                        <h5>Sarah Johnson</h5>
                                        <p>Bagaimana cara withdraw tabungan?</p>
                                        <span class="chat-time">10:30</span>
                                    </div>
                                </div>
                                <div class="chat-item">
                                    <div class="chat-avatar">
                                        <img src="https://images.pexels.com/photos/91227/pexels-photo-91227.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="User">
                                    </div>
                                    <div class="chat-info">
                                        <h5>Michael Chen</h5>
                                        <p>Saya tidak bisa akses grup tabungan</p>
                                        <span class="chat-time">09:45</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chatbot-main">
                            <div class="chat-header">
                                <div class="chat-user-info">
                                    <img src="https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=40&h=40&fit=crop" alt="User">
                                    <div>
                                        <h4>Sarah Johnson</h4>
                                        <span class="user-status">Online</span>
                                    </div>
                                </div>
                                <div class="chat-actions">
                                    <button class="btn-icon"><i class="fas fa-phone"></i></button>
                                    <button class="btn-icon"><i class="fas fa-video"></i></button>
                                    <button class="btn-icon"><i class="fas fa-ellipsis-v"></i></button>
                                </div>
                            </div>
                            
                            <div class="chat-messages">
                                <div class="message user-message">
                                    <div class="message-content">
                                        <p>Halo, saya ingin tahu bagaimana cara withdraw tabungan untuk trip ke Bali?</p>
                                        <span class="message-time">10:25</span>
                                    </div>
                                </div>
                                
                                <div class="message ai-message">
                                    <div class="message-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div class="message-content">
                                        <p>Halo Sarah! Untuk melakukan withdraw tabungan, Anda perlu:</p>
                                        <ol>
                                            <li>Masuk ke grup tabungan "Bali Adventure 2024"</li>
                                            <li>Pastikan target tabungan sudah tercapai (saat ini 85%)</li>
                                            <li>Klik "Request Withdrawal" dan isi form yang disediakan</li>
                                            <li>Menunggu persetujuan dari admin grup (biasanya 1-2 hari kerja)</li>
                                        </ol>
                                        <p>Apakah ada yang ingin ditanyakan lebih lanjut?</p>
                                        <span class="message-time">10:26</span>
                                    </div>
                                </div>
                                
                                <div class="message user-message">
                                    <div class="message-content">
                                        <p>Berapa lama proses pencairan dana setelah disetujui?</p>
                                        <span class="message-time">10:30</span>
                                    </div>
                                </div>
                                
                                <div class="typing-indicator">
                                    <div class="typing-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div class="typing-animation">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="chat-input">
                                <div class="input-container">
                                    <button class="btn-icon"><i class="fas fa-paperclip"></i></button>
                                    <input type="text" placeholder="Ketik balasan untuk Sarah..." id="chatInput">
                                    <button class="btn-icon"><i class="fas fa-smile"></i></button>
                                    <button class="btn-primary" id="sendMessage">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other sections would go here... -->
                <div id="groups-section" class="content-section">
                    <div class="section-header">
                        <h3>Manajemen Grup Tabungan</h3>
                        <button class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Buat Grup Baru
                        </button>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten manajemen grup tabungan akan ditampilkan di sini...</p>
                    </div>
                </div>

                <div id="transactions-section" class="content-section">
                    <div class="section-header">
                        <h3>Monitoring Transaksi</h3>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten monitoring transaksi akan ditampilkan di sini...</p>
                    </div>
                </div>

                <div id="goals-section" class="content-section">
                    <div class="section-header">
                        <h3>Target Travel</h3>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten target travel akan ditampilkan di sini...</p>
                    </div>
                </div>

                <div id="analytics-section" class="content-section">
                    <div class="section-header">
                        <h3>Analytics & Insights</h3>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten analytics akan ditampilkan di sini...</p>
                    </div>
                </div>

                <div id="reports-section" class="content-section">
                    <div class="section-header">
                        <h3>Laporan</h3>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten laporan akan ditampilkan di sini...</p>
                    </div>
                </div>

                <div id="settings-section" class="content-section">
                    <div class="section-header">
                        <h3>Pengaturan Sistem</h3>
                    </div>
                    <div class="dashboard-card">
                        <p>Konten pengaturan akan ditampilkan di sini...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js"></script>
    <script src="js/main.js"></script>
    <script src="js/config.js"></script>
</body>
</html>