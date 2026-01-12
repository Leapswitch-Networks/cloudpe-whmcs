<?php
/**
 * CloudPe Shared Console Page
 *
 * Standalone HTML page for public console access via share tokens.
 * Dark-themed UI with VM info and embedded noVNC console.
 *
 * @version 1.0
 */

// Get token from URL
$token = trim($_GET['token'] ?? '');

// Basic token format validation (will be fully validated by API)
$validToken = !empty($token) && strlen($token) === 64 && ctype_xdigit($token);

// Determine base URL for API calls
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$apiUrl = $protocol . '://' . $host . $basePath . '/console_share_api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Console - CloudPe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f0f1a;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #1a1a2e;
            border-bottom: 1px solid #2a2a4a;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo {
            font-size: 18px;
            font-weight: 600;
            color: #4a9fff;
        }

        .vm-name {
            font-size: 16px;
            font-weight: 500;
            color: #fff;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #166534;
            color: #86efac;
        }

        .status-stopped {
            background: #374151;
            color: #9ca3af;
        }

        .status-error {
            background: #7f1d1d;
            color: #fca5a5;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .expires {
            font-size: 13px;
            color: #9ca3af;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #374151;
            color: #e5e7eb;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .console-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #000;
            position: relative;
        }

        .console-frame {
            flex: 1;
            width: 100%;
            border: none;
            background: #000;
        }

        .loading-container, .error-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #374151;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 16px;
            color: #9ca3af;
        }

        .error-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #7f1d1d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 32px;
        }

        .error-title {
            font-size: 20px;
            font-weight: 600;
            color: #fca5a5;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: 14px;
            color: #9ca3af;
            max-width: 400px;
            line-height: 1.5;
        }

        .footer {
            background: #1a1a2e;
            border-top: 1px solid #2a2a4a;
            padding: 10px 20px;
            font-size: 12px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .footer-hint {
            display: flex;
            gap: 16px;
        }

        .kbd {
            display: inline-block;
            padding: 2px 6px;
            background: #374151;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="logo">CloudPe Console</div>
            <span id="vm-name" class="vm-name hidden"></span>
            <span id="status-badge" class="status-badge hidden"></span>
        </div>
        <div class="header-right">
            <span id="expires-text" class="expires hidden"></span>
            <button id="btn-popout" class="btn btn-secondary hidden" onclick="openInNewTab()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    <polyline points="15 3 21 3 21 9"/>
                    <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                Open in New Tab
            </button>
            <button id="btn-reload" class="btn btn-primary hidden" onclick="loadConsole()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Reconnect
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loading-container" class="loading-container">
        <div class="spinner"></div>
        <p class="loading-text">Connecting to console...</p>
    </div>

    <!-- Error State -->
    <div id="error-container" class="error-container hidden">
        <div class="error-icon">!</div>
        <h2 id="error-title" class="error-title">Error</h2>
        <p id="error-message" class="error-message"></p>
    </div>

    <!-- Console Container -->
    <div id="console-container" class="console-container hidden">
        <iframe id="console-frame" class="console-frame" sandbox="allow-scripts allow-same-origin allow-forms" allow="clipboard-read; clipboard-write"></iframe>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-hint">
            <span>Keyboard hints:</span>
            <span><kbd class="kbd">Ctrl+Alt+Del</kbd> Send to VM</span>
            <span><kbd class="kbd">Ctrl+Alt</kbd> Release mouse</span>
        </div>
        <div>Powered by CloudPe</div>
    </div>

    <script>
        const token = <?php echo json_encode($validToken ? $token : ''); ?>;
        const apiUrl = <?php echo json_encode($apiUrl); ?>;
        let consoleUrl = null;

        const errorTitles = {
            'TOKEN_NOT_FOUND': 'Invalid Link',
            'TOKEN_REVOKED': 'Link Revoked',
            'TOKEN_EXPIRED': 'Link Expired',
            'VM_NOT_FOUND': 'VM Not Found',
            'VM_NOT_ACTIVE': 'VM Not Running',
            'SERVICE_NOT_ACTIVE': 'Service Inactive',
            'CONSOLE_ERROR': 'Console Unavailable',
            'RATE_LIMITED': 'Too Many Requests',
            'API_ERROR': 'Connection Error',
            'SERVER_ERROR': 'Server Error'
        };

        const errorMessages = {
            'TOKEN_NOT_FOUND': 'This console share link does not exist or has been deleted.',
            'TOKEN_REVOKED': 'This console share link has been revoked by the owner.',
            'TOKEN_EXPIRED': 'This console share link has expired. Please request a new link from the VM owner.',
            'VM_NOT_FOUND': 'The virtual machine associated with this link no longer exists.',
            'VM_NOT_ACTIVE': 'The virtual machine is not running. Console access requires the VM to be active.',
            'SERVICE_NOT_ACTIVE': 'The service associated with this VM is not active.',
            'CONSOLE_ERROR': 'Unable to connect to the VM console. Please try again later.',
            'RATE_LIMITED': 'Too many requests. Please wait a moment before trying again.',
            'API_ERROR': 'Failed to connect to the cloud infrastructure.',
            'SERVER_ERROR': 'A server configuration error occurred.'
        };

        function showLoading() {
            document.getElementById('loading-container').classList.remove('hidden');
            document.getElementById('error-container').classList.add('hidden');
            document.getElementById('console-container').classList.add('hidden');
        }

        function showError(errorCode, customMessage) {
            document.getElementById('loading-container').classList.add('hidden');
            document.getElementById('error-container').classList.remove('hidden');
            document.getElementById('console-container').classList.add('hidden');

            document.getElementById('error-title').textContent = errorTitles[errorCode] || 'Error';
            document.getElementById('error-message').textContent = customMessage || errorMessages[errorCode] || 'An unknown error occurred.';

            // Hide header elements on error
            document.getElementById('vm-name').classList.add('hidden');
            document.getElementById('status-badge').classList.add('hidden');
            document.getElementById('expires-text').classList.add('hidden');
            document.getElementById('btn-popout').classList.add('hidden');
            document.getElementById('btn-reload').classList.add('hidden');
        }

        function showConsole(data) {
            document.getElementById('loading-container').classList.add('hidden');
            document.getElementById('error-container').classList.add('hidden');
            document.getElementById('console-container').classList.remove('hidden');

            // Update header
            document.getElementById('vm-name').textContent = data.vm_name;
            document.getElementById('vm-name').classList.remove('hidden');

            const badge = document.getElementById('status-badge');
            badge.textContent = 'Running';
            badge.className = 'status-badge status-active';
            badge.classList.remove('hidden');

            // Format expiry
            const expiresAt = new Date(data.expires_at);
            document.getElementById('expires-text').textContent = 'Expires: ' + expiresAt.toLocaleString();
            document.getElementById('expires-text').classList.remove('hidden');

            // Show buttons
            document.getElementById('btn-popout').classList.remove('hidden');
            document.getElementById('btn-reload').classList.remove('hidden');

            // Load console in iframe
            consoleUrl = data.console_url;
            document.getElementById('console-frame').src = consoleUrl;
        }

        function openInNewTab() {
            if (consoleUrl) {
                window.open(consoleUrl, '_blank', 'noopener,noreferrer');
            }
        }

        async function loadConsole() {
            if (!token) {
                showError('TOKEN_NOT_FOUND');
                return;
            }

            showLoading();

            try {
                // First check status (non-consuming)
                const statusRes = await fetch(apiUrl + '?action=status&token=' + encodeURIComponent(token));
                const statusData = await statusRes.json();

                if (!statusData.valid && !statusData.success) {
                    showError(statusData.error_code || 'TOKEN_NOT_FOUND', statusData.message);
                    return;
                }

                // Now get console URL (consuming)
                const consoleRes = await fetch(apiUrl + '?action=access&token=' + encodeURIComponent(token));
                const consoleData = await consoleRes.json();

                if (!consoleData.success) {
                    showError(consoleData.error_code || 'CONSOLE_ERROR', consoleData.message);
                    return;
                }

                showConsole(consoleData);

            } catch (error) {
                console.error('Console load error:', error);
                showError('API_ERROR', 'Failed to connect to the console service. Please check your network connection.');
            }
        }

        // Load on page ready
        document.addEventListener('DOMContentLoaded', loadConsole);
    </script>
</body>
</html>
