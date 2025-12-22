// State
let inboxData = null;
let emails = [];
let selectedEmailId = null;
let selectedEmailIds = new Set();
let timerInterval = null;
let echoInstance = null;
let currentChannel = null;

// API Base URL
const API_BASE = '/api';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('emailList')) {
        initializeInbox();
    }
});

async function initializeInbox() {
    const savedToken = localStorage.getItem('inboxToken');

    if (savedToken) {
        try {
            const response = await fetch(`${API_BASE}/inbox/${savedToken}`);
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // If user is authenticated but inbox is guest, create new auth inbox
                    if (window.AppConfig.isUserAuthenticated && !data.data.is_authenticated) {
                        console.log('Authenticated user with guest inbox, creating new inbox...');
                        await createNewInbox();
                        return;
                    }

                    // If user is NOT authenticated but inbox IS authenticated (logout case), create new guest inbox
                    if (!window.AppConfig.isUserAuthenticated && data.data.is_authenticated) {
                        console.log('Guest user with auth inbox (logout detected), creating new inbox...');
                        await createNewInbox();
                        return;
                    }
                    inboxData = {
                        ...data.data,
                        token: savedToken
                    };
                    updateUI();
                    await loadEmails();
                    subscribeToInbox();
                    return;
                }
            }
        } catch (e) {
            console.log('Saved inbox expired or invalid');
        }
    }

    await createNewInbox();
}

async function createNewInbox() {
    try {
        const response = await fetch(`${API_BASE}/inbox`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({})
        });

        const data = await response.json();

        if (data.success) {
            inboxData = data.data;
            localStorage.setItem('inboxToken', inboxData.token);
            emails = [];
            selectedEmailId = null;
            selectedEmailIds.clear();
            updateUI();
            renderEmails();
            showViewer(null);
            subscribeToInbox();
            showToast('New inbox created!', 'success');
        } else {
            showToast('Failed to create inbox', 'error');
        }
    } catch (e) {
        showToast('Error creating inbox: ' + e.message, 'error');
    }
}

function updateUI() {
    if (!inboxData) return;

    const emailAddressEl = document.getElementById('emailAddress');
    if (emailAddressEl) emailAddressEl.textContent = inboxData.email;

    // Show expiry label
    const expiryLabel = document.getElementById('expiryLabel');
    if (expiryLabel && inboxData.expiry_label) {
        expiryLabel.textContent = `(${inboxData.expiry_label})`;
        expiryLabel.style.display = 'inline';
    }

    // Start timer
    if (timerInterval) clearInterval(timerInterval);
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
}

function updateTimer() {
    if (!inboxData) return;

    const expiresAt = new Date(inboxData.expires_at);
    const now = new Date();
    const diff = expiresAt - now;

    const timerDisplay = document.getElementById('timerDisplay');
    if (!timerDisplay) return;

    if (diff <= 0) {
        timerDisplay.textContent = 'Expired';
        clearInterval(timerInterval);
        return;
    }

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    if (hours > 0) {
        timerDisplay.textContent = `${hours}h ${minutes}m ${seconds}s`;
    } else {
        timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
}

async function loadEmails() {
    if (!inboxData) return;

    try {
        const response = await fetch(`${API_BASE}/inbox/${inboxData.token}/emails`);
        const data = await response.json();

        if (data.success) {
            const oldCount = emails.length;
            emails = data.data;
            renderEmails(oldCount < emails.length);
        }
    } catch (e) {
        console.error('Error loading emails:', e);
    }
}

async function refreshInbox() {
    await loadEmails();
    showToast('Inbox refreshed', 'success');
}

function getAttachmentBadgeHtml(count) {
    if (count <= 0) return '';
    return `
        <span class="email-attachment-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
            </svg>
            ${count}
        </span>
    `;
}

function renderEmails(hasNew = false) {
    const container = document.getElementById('emailList');
    if (!container) return;

    if (emails.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <h3>No emails yet</h3>
                <p>Emails sent to your temp address will appear here</p>
            </div>
        `;
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
        updateBulkDeleteVisibility();
        return;
    }

    container.innerHTML = emails.map((email, index) => `
        <div class="email-item ${!email.is_read ? 'unread' : ''} ${selectedEmailId === email.id ? 'active' : ''} ${hasNew && index === 0 ? 'new' : ''}"
             onclick="handleEmailClick(event, ${email.id})">
            <div class="email-item-checkbox">
                <input type="checkbox" ${selectedEmailIds.has(email.id) ? 'checked' : ''} 
                       onclick="toggleEmailSelection(event, ${email.id})">
            </div>
            <div class="email-item-content">
                <div class="email-from">${escapeHtml(email.from_name || email.from_email)}</div>
                <div class="email-subject">${escapeHtml(email.subject || '(No Subject)')}</div>
                <div class="email-meta">
                    <span>${formatDate(email.received_at)}</span>
                    ${getAttachmentBadgeHtml(email.attachments_count)}
                </div>
            </div>
        </div>
    `).join('');

    updateBulkDeleteVisibility();
}

function handleEmailClick(event, emailId) {
    if (event.target.tagName === 'INPUT') return;
    viewEmail(emailId);
}

function toggleEmailSelection(event, emailId) {
    event.stopPropagation();
    if (event.target.checked) {
        selectedEmailIds.add(emailId);
    } else {
        selectedEmailIds.delete(emailId);
    }
    updateBulkDeleteVisibility();
    updateSelectAllState();
}

function toggleSelectAll(event) {
    const checked = event.target.checked;
    if (checked) {
        emails.forEach(email => selectedEmailIds.add(email.id));
    } else {
        selectedEmailIds.clear();
    }
    renderEmails();
}

function updateSelectAllState() {
    const selectAll = document.getElementById('selectAll');
    if (!selectAll) return;
    
    if (emails.length === 0) {
        selectAll.checked = false;
        return;
    }
    selectAll.checked = emails.every(email => selectedEmailIds.has(email.id));
}

function updateBulkDeleteVisibility() {
    const btn = document.getElementById('bulkDeleteBtn');
    if (!btn) return;

    if (selectedEmailIds.size > 0) {
        btn.classList.remove('hidden');
        btn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18"></path>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            Delete (${selectedEmailIds.size})
        `;
    } else {
        btn.classList.add('hidden');
    }
}

async function deleteSelectedEmails() {
    if (selectedEmailIds.size === 0) return;
    if (!confirm(`Are you sure you want to delete ${selectedEmailIds.size} emails?`)) return;

    try {
        const response = await fetch(`${API_BASE}/inbox/${inboxData.token}/emails`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                ids: Array.from(selectedEmailIds)
            })
        });

        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            if (selectedEmailIds.has(selectedEmailId)) {
                showViewer(null);
                selectedEmailId = null;
            }
            selectedEmailIds.clear();
            await loadEmails();
        } else {
            showToast('Failed to delete emails', 'error');
        }
    } catch (e) {
        showToast('Error deleting emails', 'error');
    }
}

async function viewEmail(emailId) {
    if (!inboxData) return;

    selectedEmailId = emailId;
    renderEmails();

    try {
        const response = await fetch(`${API_BASE}/inbox/${inboxData.token}/emails/${emailId}`);
        const data = await response.json();

        if (data.success) {
            showViewer(data.data);
        }
    } catch (e) {
        showToast('Error loading email', 'error');
    }
}

function showViewer(email) {
    const emptyState = document.getElementById('viewerEmptyState');
    const content = document.getElementById('emailContent');
    if (!emptyState || !content) return;

    if (!email) {
        emptyState.classList.remove('hidden');
        content.classList.add('hidden');
        return;
    }

    emptyState.classList.add('hidden');
    content.classList.remove('hidden');

    document.getElementById('viewerSubject').textContent = email.subject || '(No Subject)';
    document.getElementById('viewerFrom').textContent = email.from_name || email.from_email;
    document.getElementById('viewerDate').textContent = formatDate(email.received_at);
    document.getElementById('viewerAvatar').textContent =
        (email.from_name || email.from_email).charAt(0).toUpperCase();

    // Display email body
    const bodyContainer = document.getElementById('viewerBody');
    if (email.body_html) {
        bodyContainer.innerHTML = `<iframe id="emailIframe" sandbox="allow-same-origin"></iframe>`;
        const iframe = document.getElementById('emailIframe');
        iframe.onload = () => {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(email.body_html);
            doc.close();
        };
        iframe.src = 'about:blank';
    } else {
        bodyContainer.innerHTML = `<pre style="white-space: pre-wrap; font-family: inherit;">${escapeHtml(email.body_text || 'No content')}</pre>`;
    }

    // Attachments
    const attachSection = document.getElementById('attachmentsSection');
    const attachList = document.getElementById('attachmentList');

    if (email.attachments && email.attachments.length > 0) {
        attachSection.classList.remove('hidden');
        attachList.innerHTML = email.attachments.map(att => `
            <a href="/api/inbox/${inboxData.token}/emails/${email.id}/attachments/${att.id}" 
               class="attachment-item" target="_blank">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                </svg>
                <span>${escapeHtml(att.filename)}</span>
                <span style="color: var(--text-muted); font-size: 0.8rem;">(${formatSize(att.size)})</span>
            </a>
        `).join('');
    } else {
        attachSection.classList.add('hidden');
    }
}

function subscribeToInbox() {
    if (!inboxData) return;

    // Disconnect previous channel
    if (currentChannel && echoInstance) {
        echoInstance.leave(currentChannel);
    }

    try {
        if (!echoInstance) {
            const config = window.AppConfig.reverb;
            echoInstance = new Echo({
                broadcaster: 'reverb',
                key: config.key,
                wsHost: config.wsHost,
                wsPort: config.wsPort,
                forceTLS: config.forceTLS,
                enabledTransports: config.enabledTransports,
                disableStats: config.disableStats,
            });
        }

        currentChannel = `inbox.${inboxData.id}`;
        echoInstance.channel(currentChannel)
            .listen('.email.received', (e) => {
                console.log('New email received:', e);
                loadEmails();
                showToast('New email received!', 'success');
            });

        const statusEl = document.getElementById('realtimeStatus');
        if (statusEl) {
            statusEl.innerHTML = `
                <span class="pulse"></span>
                <span>Real-time updates active</span>
            `;
        }
    } catch (e) {
        console.error('WebSocket connection failed:', e);
        const statusEl = document.getElementById('realtimeStatus');
        if (statusEl) {
            statusEl.innerHTML = `
                <span style="color: var(--warning);">⚠️ Real-time updates unavailable</span>
            `;
        }
    }
}

function copyEmail() {
    if (!inboxData) return;

    navigator.clipboard.writeText(inboxData.email).then(() => {
        showToast('Email copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy email', 'error');
    });
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : '✕'}</span>
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Expose functions to window for HTML event handlers
window.copyEmail = copyEmail;
window.refreshInbox = refreshInbox;
window.createNewInbox = createNewInbox;
window.toggleSelectAll = toggleSelectAll;
window.deleteSelectedEmails = deleteSelectedEmails;
window.handleEmailClick = handleEmailClick;
window.toggleEmailSelection = toggleEmailSelection;
