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
        window.AppConfig = {
            isUserAuthenticated: {{ auth()->check() ? 'true' : 'false' }},
            reverb: {
                key: '{{ config('reverb.apps.apps.0.key') }}',
                wsHost: '{{ config('reverb.servers.reverb.hostname') ?? 'localhost' }}',
                wsPort: {{ config('reverb.servers.reverb.port') ?? 8080 }},
                forceTLS: false,
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
            }
        };
    </script>
@endpush
