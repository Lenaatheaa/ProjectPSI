// Main JavaScript functionality for JalanYuk app

// Global variables
let currentGroupId = null;
let groups = [
    {
        id: 1,
        name: 'Trip to Bali 2024',
        description: 'Liburan keluarga ke Bali untuk merayakan tahun baru',
        targetAmount: 15000000,
        currentAmount: 8500000,
        startDate: '2024-01-15',
        endDate: '2024-12-31',
        members: [
            {
                id: 1,
                name: 'Yogas Wilbowo',
                email: 'yogas@jalanyuk.com',
                avatar: 'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
                joinedAt: '2024-01-15',
                contributionAmount: 3000000,
                paymentCount: 2,
                isLeader: true
            },
            {
                id: 2,
                name: 'Sarah Johnson',
                email: 'sarah@example.com',
                avatar: 'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
                joinedAt: '2024-01-16',
                contributionAmount: 2500000,
                paymentCount: 2,
                isLeader: false
            },
            {
                id: 3,
                name: 'Michael Chen',
                email: 'michael@example.com',
                avatar: 'https://images.pexels.com/photos/1516680/pexels-photo-1516680.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
                joinedAt: '2024-01-18',
                contributionAmount: 3000000,
                paymentCount: 2,
                isLeader: false
            }
        ]
    }
];

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
});

// Initialize application
function initializeApp() {
    setupSearchAndFilter();
    setTimeout(animateProgressBars, 500);
}

// Setup event listeners
function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filter functionality
    const filterSelect = document.getElementById('filterSelect');
    if (filterSelect) {
        filterSelect.addEventListener('change', handleFilter);
    }
    
    // Modal close on outside click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

// Search functionality
function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const groupCards = document.querySelectorAll('.group-card');
    
    groupCards.forEach(card => {
        const title = card.querySelector('.group-title').textContent.toLowerCase();
        const description = card.querySelector('.group-description').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Filter functionality
function handleFilter(e) {
    const filterValue = e.target.value;
    const groupCards = document.querySelectorAll('.group-card');
    
    groupCards.forEach(card => {
        switch (filterValue) {
            case 'all':
                card.style.display = 'block';
                break;
            case 'active':
                card.style.display = 'block';
                break;
            case 'inactive':
                card.style.display = 'none';
                break;
        }
    });
}

// Navigation functions
function openGroupDetail(groupId) {
    window.location.href = `group-detail.php?id=${groupId}`;
}

function goBack() {
    window.location.href = 'index.html';
}

// Create Group Modal
function openCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = modal.querySelector('input[name="name"]');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

function closeCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
}

// Create group form submission
function createGroup(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        name: formData.get('name'),
        description: formData.get('description'),
        target_amount: parseInt(formData.get('target_amount')),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date')
    };
    
    // Validate dates
    if (new Date(data.start_date) >= new Date(data.end_date)) {
        showNotification('Tanggal selesai harus setelah tanggal mulai', 'error');
        return;
    }
    
    // Validate amount
    if (data.target_amount <= 0) {
        showNotification('Target dana harus lebih dari 0', 'error');
        return;
    }
    
    // Simulate group creation
    const newGroup = {
        id: groups.length + 1,
        name: data.name,
        description: data.description,
        targetAmount: data.target_amount,
        currentAmount: 0,
        startDate: data.start_date,
        endDate: data.end_date,
        members: []
    };
    
    groups.push(newGroup);
    
    showNotification('Grup berhasil dibuat!', 'success');
    closeCreateGroupModal();
    
    // Reload page to show new group
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Invite Members Modal
function openInviteMembersModal() {
    const modal = document.getElementById('inviteMembersModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Generate invite link
        generateInviteLink();
    }
}

function closeInviteMembersModal() {
    const modal = document.getElementById('inviteMembersModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Generate invite link
function generateInviteLink() {
    const inviteLinkInput = document.getElementById('inviteLink');
    if (inviteLinkInput) {
        const baseUrl = window.location.origin;
        const groupId = getCurrentGroupId();
        const inviteCode = generateInviteCode();
        const inviteLink = `${baseUrl}/join?group=${groupId}&code=${inviteCode}`;
        
        inviteLinkInput.value = inviteLink;
    }
}

// Copy invite link
function copyInviteLink() {
    const inviteLinkInput = document.getElementById('inviteLink');
    if (inviteLinkInput) {
        copyToClipboard(inviteLinkInput.value);
    }
}

// Generate invite code
function generateInviteCode() {
    return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

// Get current group ID
function getCurrentGroupId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id') || '1';
}

// Leave Group Modal
function openLeaveGroupModal() {
    const modal = document.getElementById('leaveGroupModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeLeaveGroupModal() {
    const modal = document.getElementById('leaveGroupModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Confirm leave group
function confirmLeaveGroup() {
    showNotification('Anda telah keluar dari grup', 'success');
    closeLeaveGroupModal();
    
    // Redirect to main page
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}

// Remove member from group
function removeMember(memberId) {
    if (!confirm('Apakah Anda yakin ingin mengeluarkan anggota ini dari grup?')) {
        return;
    }
    
    showNotification('Anggota berhasil dikeluarkan', 'success');
    
    // Remove member from UI
    const memberItem = document.querySelector(`[data-member-id="${memberId}"]`);
    if (memberItem) {
        memberItem.remove();
    }
    
    // Update member count
    updateMemberCount();
}

// Update member count in UI
function updateMemberCount() {
    const memberItems = document.querySelectorAll('.member-item');
    const memberCountSpan = document.querySelector('.members-count span');
    
    if (memberCountSpan) {
        memberCountSpan.textContent = `${memberItems.length} aktif`;
    }
}

// Utility functions
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

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount).replace('IDR', 'Rp');
}

function formatDate(dateString) {
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    const date = new Date(dateString);
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    
    return `${day} ${month} ${year}`;
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add notification styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        default: return 'info-circle';
    }
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#22c55e';
        case 'error': return '#ef4444';
        case 'warning': return '#f59e0b';
        default: return '#3b82f6';
    }
}

// Progress bar animation
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
}

// Copy to clipboard functionality
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Link berhasil disalin!', 'success');
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Link berhasil disalin!', 'success');
    }
}

// Setup search and filter functionality
function setupSearchAndFilter() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterGroups();
        });
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            filterGroups();
        });
    }
}

function filterGroups() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const filterValue = document.getElementById('filterSelect')?.value || 'all';
    const groupCards = document.querySelectorAll('.group-card');
    
    groupCards.forEach(card => {
        const title = card.querySelector('.group-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.group-description')?.textContent.toLowerCase() || '';
        
        const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
        const matchesFilter = filterValue === 'all' || card.dataset.status === filterValue;
        
        if (matchesSearch && matchesFilter) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Close all modals
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = '';
}

// Handle modal backdrop clicks
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        const modalId = e.target.id;
        switch (modalId) {
            case 'createGroupModal':
                closeCreateGroupModal();
                break;
            case 'inviteMembersModal':
                closeInviteMembersModal();
                break;
            case 'leaveGroupModal':
                closeLeaveGroupModal();
                break;
        }
    }
});

// Handle ESC key for modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateGroupModal();
        closeInviteMembersModal();
        closeLeaveGroupModal();
    }
});

// Add CSS animation for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
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
        gap: 8px;
        flex: 1;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        opacity: 0.8;
        transition: opacity 0.2s ease;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
`;
document.head.appendChild(style);

