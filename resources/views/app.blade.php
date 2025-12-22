@extends('layouts.app')

@section('title', 'Temp Mail - Disposable Email Service')

@push('styles')
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
@endpush

@section('content')
    <div class="container">
        <!-- Email Address Box -->
        <div class="email-box">
            <div class="email-display">
                <div class="email-address" id="emailAddress">Loading...</div>
                <button class="btn btn-primary" onclick="copyEmail()" id="copyBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copy
                </button>
                <button class="btn btn-secondary" onclick="refreshInbox()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Refresh
                </button>
                <button class="btn btn-success" onclick="createNewInbox()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New Address
                </button>
            </div>
            <div class="timer-section">
                <div class="timer">
                    <svg class="timer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Expires in: <strong id="timerDisplay">--:--</strong></span>
                    <span id="expiryLabel" class="expiry-label"></span>
                </div>
                <div class="realtime-indicator" id="realtimeStatus">
                    <span class="pulse"></span>
                    <span>Real-time updates active</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Email List -->
            <div class="email-list">
                <div class="email-list-header">
                    <div class="header-left">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(event)">
                        <h2>Inbox</h2>
                    </div>
                    <button id="bulkDeleteBtn" class="btn btn-danger btn-sm hidden" onclick="deleteSelectedEmails()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Delete
                    </button>
                </div>
                <div class="email-items" id="emailList">
                    <div class="empty-state" id="emptyState">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <h3>No emails yet</h3>
                        <p>Emails sent to your temp address will appear here</p>
                    </div>
                </div>
            </div>

            <!-- Email Viewer -->
            <div class="email-viewer" id="emailViewer">
                <div class="empty-state" id="viewerEmptyState">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <h3>Select an email</h3>
                    <p>Choose an email from the list to view its contents</p>
                </div>
                <div id="emailContent" class="hidden">
                    <div class="email-viewer-header">
                        <h3 class="email-viewer-subject" id="viewerSubject">Email Subject</h3>
                        <div class="email-viewer-from">
                            <div class="avatar" id="viewerAvatar">?</div>
                            <div class="email-viewer-from-info">
                                <h4 id="viewerFrom">Sender Name</h4>
                                <p id="viewerDate">Date</p>
                            </div>
                        </div>
                    </div>
                    <div class="email-viewer-body">
                        <div class="email-body-content" id="viewerBody"></div>
                    </div>
                    <div class="attachments-section hidden" id="attachmentsSection">
                        <h4>Attachments</h4>
                        <div class="attachment-list" id="attachmentList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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

        // Reverb WebSocket config
        const REVERB_CONFIG = {
            key: '{{ config('reverb.apps.apps.0.key') }}',
            wsHost: '{{ config('reverb.servers.reverb.hostname') ?? 'localhost' }}',
            wsPort: {{ config('reverb.servers.reverb.port') ?? 8080 }},
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        };

        // Check if current user is authenticated (from Blade)
        const isUserAuthenticated = {{ auth()->check() ? 'true' : 'false' }};

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeInbox();
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
                            if (isUserAuthenticated && !data.data.is_authenticated) {
                                console.log('Authenticated user with guest inbox, creating new inbox...');
                                await createNewInbox();
                                return;
                            }

                            // If user is NOT authenticated but inbox IS authenticated (logout case), create new guest inbox
                            if (!isUserAuthenticated && data.data.is_authenticated) {
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

            document.getElementById('emailAddress').textContent = inboxData.email;

            // Show expiry label
            const expiryLabel = document.getElementById('expiryLabel');
            if (inboxData.expiry_label) {
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

            if (diff <= 0) {
                document.getElementById('timerDisplay').textContent = 'Expired';
                clearInterval(timerInterval);
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            if (hours > 0) {
                document.getElementById('timerDisplay').textContent =
                    `${hours}h ${minutes}m ${seconds}s`;
            } else {
                document.getElementById('timerDisplay').textContent =
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
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
                document.getElementById('selectAll').checked = false;
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
            if (emails.length === 0) {
                selectAll.checked = false;
                return;
            }
            selectAll.checked = emails.every(email => selectedEmailIds.has(email.id));
        }

        function updateBulkDeleteVisibility() {
            const btn = document.getElementById('bulkDeleteBtn');
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
                    echoInstance = new Echo({
                        broadcaster: 'reverb',
                        key: REVERB_CONFIG.key,
                        wsHost: REVERB_CONFIG.wsHost,
                        wsPort: REVERB_CONFIG.wsPort,
                        forceTLS: REVERB_CONFIG.forceTLS,
                        enabledTransports: REVERB_CONFIG.enabledTransports,
                        disableStats: REVERB_CONFIG.disableStats,
                    });
                }

                currentChannel = `inbox.${inboxData.id}`;
                echoInstance.channel(currentChannel)
                    .listen('.email.received', (e) => {
                        console.log('New email received:', e);
                        loadEmails();
                        showToast('New email received!', 'success');
                    });

                document.getElementById('realtimeStatus').innerHTML = `
                        <span class="pulse"></span>
                        <span>Real-time updates active</span>
                    `;
            } catch (e) {
                console.error('WebSocket connection failed:', e);
                document.getElementById('realtimeStatus').innerHTML = `
                        <span style="color: var(--warning);">⚠️ Real-time updates unavailable</span>
                    `;
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
    </script>
@endpush
