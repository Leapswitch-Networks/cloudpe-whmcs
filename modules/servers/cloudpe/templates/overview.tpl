<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Virtual Machine Overview</h3>
    </div>
    <div class="panel-body">
        <!-- Alert container for messages -->
        <div id="cloudpe-alert-container"></div>

        <div class="row">
            <div class="col-md-8">
                <!-- VM Status and Info -->
                <div class="row">
                    <div class="col-sm-6">
                        <h4><i class="fas fa-server"></i> Server Details</h4>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>Hostname</strong></td>
                                <td>{$hostname}</td>
                            </tr>
                            <tr>
                                <td><strong>Status</strong></td>
                                <td id="vm-status-cell">{$status_label}</td>
                            </tr>
                            <tr>
                                <td><strong>IPv4 Address</strong></td>
                                <td>{if $ipv4}{$ipv4}{else}<span class="text-muted">Not assigned</span>{/if}</td>
                            </tr>
                            <tr>
                                <td><strong>IPv6 Address</strong></td>
                                <td>{if $ipv6}{$ipv6}{else}<span class="text-muted">Not assigned</span>{/if}</td>
                            </tr>
                            <tr>
                                <td><strong>Created</strong></td>
                                <td>{$created|date_format:"%Y-%m-%d %H:%M"}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-sm-6">
                        <h4><i class="fas fa-microchip"></i> Configuration</h4>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>Operating System</strong></td>
                                <td>{$os|default:'Unknown'}</td>
                            </tr>
                            <tr>
                                <td><strong>CPU</strong></td>
                                <td>{$vcpus|default:'-'} vCPU{if $vcpus > 1}s{/if}</td>
                            </tr>
                            <tr>
                                <td><strong>Memory</strong></td>
                                <td>{$ram|default:'-'} GB</td>
                            </tr>
                            <tr>
                                <td><strong>Disk</strong></td>
                                <td>{$disk|default:'-'} GB</td>
                            </tr>
                            <tr>
                                <td><strong>Plan</strong></td>
                                <td><small>{$flavor_name|default:'Unknown'}</small></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <h4><i class="fas fa-cogs"></i> Actions</h4>
                <div id="vm-actions-container">
                    {if $status == 'ACTIVE'}
                        <button type="button" class="btn btn-warning btn-block" data-action="stop">
                            <i class="fas fa-stop"></i> Stop VM
                        </button>
                        <button type="button" class="btn btn-info btn-block" data-action="restart">
                            <i class="fas fa-sync"></i> Restart VM
                        </button>
                        <!-- Console Actions Dropdown -->
                        <div class="btn-group btn-block" style="margin-bottom: 5px;">
                            <button type="button" class="btn btn-primary btn-block dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-terminal"></i> Console <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" style="width: 100%;">
                                <li><a href="#" data-action="console"><i class="fas fa-desktop"></i> Open VNC Console</a></li>
                                <li><a href="#" onclick="showBootLogModal(); return false;"><i class="fas fa-file-alt"></i> View Boot Log</a></li>
                                <li class="divider"></li>
                                <li><a href="#" onclick="showShareModal(); return false;"><i class="fas fa-share-alt"></i> Share Console Access</a></li>
                                <li><a href="#" onclick="showShareListModal(); return false;"><i class="fas fa-list"></i> Manage Shares</a></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-danger btn-block" data-action="password">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    {elseif $status == 'SHUTOFF' || $status == 'SHELVED' || $status == 'STOPPED' || $status == 'SHELVED_OFFLOADED'}
                        <button type="button" class="btn btn-success btn-block" data-action="start">
                            <i class="fas fa-play"></i> Start VM
                        </button>
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            <small><i class="fas fa-info-circle"></i> Console and other actions are available when VM is running.</small>
                        </div>
                    {else}
                        <div class="alert alert-info">
                            <i class="fas fa-spinner fa-spin"></i> VM is currently <strong>{$status}</strong>. Please wait...
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Boot Log Modal -->
<div class="modal fade" id="bootLogModal" tabindex="-1" role="dialog" aria-labelledby="bootLogModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="bootLogModalLabel"><i class="fas fa-file-alt"></i> VM Boot Log</h4>
            </div>
            <div class="modal-body">
                <div class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="bootLogLength">Lines to display:</label>
                        <select id="bootLogLength" class="form-control" style="width: 120px; margin-left: 10px;">
                            <option value="50">50 lines</option>
                            <option value="100" selected>100 lines</option>
                            <option value="500">500 lines</option>
                            <option value="1000">1000 lines</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-default" onclick="loadBootLog()" style="margin-left: 10px;">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                <pre id="bootLogContent" style="max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 15px; font-size: 12px; border-radius: 4px;">Loading...</pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Share Console Modal -->
<div class="modal fade" id="shareConsoleModal" tabindex="-1" role="dialog" aria-labelledby="shareConsoleModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="shareConsoleModalLabel"><i class="fas fa-share-alt"></i> Share Console Access</h4>
            </div>
            <div class="modal-body">
                <div id="shareFormContainer">
                    <div class="form-group">
                        <label for="shareName">Name (optional)</label>
                        <input type="text" id="shareName" class="form-control" placeholder="e.g., Support Access">
                        <p class="help-block">A friendly name to identify this share link.</p>
                    </div>
                    <div class="form-group">
                        <label for="shareExpiry">Expires In</label>
                        <select id="shareExpiry" class="form-control">
                            <option value="1h">1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h" selected>24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> The share link will only be shown once. Copy it immediately after creation.
                    </div>
                </div>
                <div id="shareResultContainer" style="display: none;">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Share link created successfully!
                    </div>
                    <div class="form-group">
                        <label>Share URL</label>
                        <div class="input-group">
                            <input type="text" id="shareUrl" class="form-control" readonly>
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="copyShareUrl()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </span>
                        </div>
                    </div>
                    <p class="text-muted"><i class="fas fa-clock"></i> Expires: <span id="shareExpiresAt"></span></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> This URL contains a secret token. Anyone with this link can access the VM console.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnCreateShare" onclick="createShare()">
                    <i class="fas fa-plus"></i> Create Share
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Shares Modal -->
<div class="modal fade" id="manageSharesModal" tabindex="-1" role="dialog" aria-labelledby="manageSharesModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="manageSharesModalLabel"><i class="fas fa-list"></i> Console Share Links</h4>
            </div>
            <div class="modal-body">
                <div id="sharesListContainer">
                    <p><i class="fas fa-spinner fa-spin"></i> Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <label style="float: left; font-weight: normal; margin-top: 7px;">
                    <input type="checkbox" id="showRevokedShares" onchange="loadSharesList()"> Show revoked shares
                </label>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="showShareModal()">
                    <i class="fas fa-plus"></i> Create New Share
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Configuration
    var serviceId = {$serviceid};
    var currentStatus = '{$status}';
    var ajaxUrl = '{$WEB_ROOT}/modules/servers/cloudpe/ajax.php';

    // Action labels for loading messages
    var actionLabels = {
        'start': 'Starting VM...',
        'stop': 'Stopping VM...',
        'restart': 'Restarting VM...',
        'console': 'Opening VNC console...',
        'password': 'Resetting password...'
    };

    // Confirmation messages for destructive actions
    var confirmMessages = {
        'stop': 'Are you sure you want to stop the VM?',
        'restart': 'Are you sure you want to restart the VM?',
        'password': 'Are you sure you want to reset the root password?'
    };

    // Console logging helper
    function cloudpeLog(type, message, data) {
        var timestamp = new Date().toISOString();
        var prefix = '[CloudPe ' + timestamp + '] ';

        switch(type) {
            case 'info':
                console.log('%c' + prefix + message, 'color: #17a2b8', data || '');
                break;
            case 'success':
                console.log('%c' + prefix + message, 'color: #28a745', data || '');
                break;
            case 'error':
                console.error(prefix + message, data || '');
                break;
            case 'warn':
                console.warn(prefix + message, data || '');
                break;
            default:
                console.log(prefix + message, data || '');
        }
    }

    // Show alert message
    function showAlert(type, message) {
        var alertClass = 'alert-' + (type === 'error' ? 'danger' : type);
        var iconClass = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle');
        var html = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
                   '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                   '<span aria-hidden="true">&times;</span></button>' +
                   '<i class="fas fa-' + iconClass + '"></i> ' + message + '</div>';

        $('#cloudpe-alert-container').html(html);

        // Auto-dismiss success after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('#cloudpe-alert-container .alert').fadeOut();
            }, 5000);
        }
    }

    // Disable all action buttons
    function disableButtons() {
        $('#vm-actions-container button').prop('disabled', true);
    }

    // Enable all action buttons
    function enableButtons() {
        $('#vm-actions-container button').prop('disabled', false);
    }

    // Update status label
    function updateStatusLabel(status) {
        var label = '';
        switch(status) {
            case 'ACTIVE':
                label = '<span class="label label-success">Running</span>';
                break;
            case 'SHUTOFF':
            case 'STOPPED':
                label = '<span class="label label-default">Stopped</span>';
                break;
            case 'BUILD':
            case 'REBUILD':
                label = '<span class="label label-info">Building</span>';
                break;
            case 'REBOOT':
            case 'HARD_REBOOT':
                label = '<span class="label label-warning">Rebooting</span>';
                break;
            case 'ERROR':
                label = '<span class="label label-danger">Error</span>';
                break;
            default:
                label = '<span class="label label-warning">' + status + '</span>';
        }
        $('#vm-status-cell').html(label);
        currentStatus = status;
    }

    // Main action handler
    function executeAction(action) {
        cloudpeLog('info', 'Action initiated: ' + action);

        // Confirm destructive actions
        if (confirmMessages[action] && !confirm(confirmMessages[action])) {
            cloudpeLog('info', 'Action cancelled by user');
            return;
        }

        disableButtons();
        showAlert('info', '<i class="fas fa-spinner fa-spin"></i> ' + (actionLabels[action] || 'Processing...'));

        var requestUrl = ajaxUrl + '?action=' + action + '&service_id=' + serviceId;
        cloudpeLog('info', 'Sending AJAX request', { url: requestUrl, action: action });

        $.ajax({
            url: requestUrl,
            type: 'GET',
            dataType: 'json',
            timeout: 60000, // 60 second timeout for long operations
            success: function(response) {
                cloudpeLog('info', 'Response received', response);

                if (response.success) {
                    cloudpeLog('success', action + ' completed successfully');

                    // Handle console action - open URL in new window
                    if (action === 'console' && response.url) {
                        showAlert('success', 'Console opened in new window');
                        window.open(response.url, '_blank', 'width=1024,height=768,menubar=no,toolbar=no,location=no,status=no');
                        enableButtons();
                        return;
                    }

                    showAlert('success', response.message);

                    // Update status if returned
                    if (response.status) {
                        updateStatusLabel(response.status);
                    }

                    // Reload page after 2 seconds to show updated state
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    cloudpeLog('error', action + ' failed', response.message);
                    showAlert('error', response.message || 'Action failed');
                    enableButtons();
                }
            },
            error: function(xhr, status, error) {
                cloudpeLog('error', 'AJAX request failed', { status: status, error: error, response: xhr.responseText });

                var errorMsg = 'Request failed: ';
                if (status === 'timeout') {
                    errorMsg += 'Operation timed out. Please check VM status and try again.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Network error. Please check your connection.';
                } else if (xhr.status === 403) {
                    errorMsg += 'Access denied. Please log in again.';
                } else {
                    errorMsg += error || 'Unknown error';
                }

                showAlert('error', errorMsg);
                enableButtons();
            }
        });
    }

    // Bind click handlers to action buttons
    $(document).ready(function() {
        cloudpeLog('info', 'CloudPe VM Control loaded', { serviceId: serviceId, status: currentStatus, ajaxUrl: ajaxUrl });

        // Bind click events using data-action attribute
        $('#vm-actions-container').on('click', 'button[data-action]', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            if (action) {
                executeAction(action);
            }
        });

        // Bind click events for dropdown menu items with data-action
        $('#vm-actions-container').on('click', '.dropdown-menu a[data-action]', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            if (action) {
                executeAction(action);
            }
        });
    });

    // ========================================
    // Console Features - Global Functions
    // ========================================

    // Boot Log Modal
    window.showBootLogModal = function() {
        $('#bootLogModal').modal('show');
        loadBootLog();
    };

    window.loadBootLog = function() {
        var length = $('#bootLogLength').val();
        $('#bootLogContent').text('Loading...');

        $.ajax({
            url: ajaxUrl + '?action=console_output&service_id=' + serviceId + '&length=' + length,
            type: 'GET',
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    $('#bootLogContent').text(response.output || '(No output available)');
                } else {
                    $('#bootLogContent').text('Error: ' + (response.message || 'Failed to load boot log'));
                }
            },
            error: function(xhr, status, error) {
                $('#bootLogContent').text('Failed to load boot log: ' + (error || 'Network error'));
            }
        });
    };

    // Share Console Modal
    window.showShareModal = function() {
        $('#shareFormContainer').show();
        $('#shareResultContainer').hide();
        $('#btnCreateShare').show();
        $('#shareName').val('');
        $('#shareExpiry').val('24h');
        $('#manageSharesModal').modal('hide');
        $('#shareConsoleModal').modal('show');
    };

    window.createShare = function() {
        var btn = $('#btnCreateShare');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');

        $.ajax({
            url: ajaxUrl + '?action=console_share_create&service_id=' + serviceId,
            type: 'POST',
            data: {
                name: $('#shareName').val(),
                expiry: $('#shareExpiry').val(),
                console_type: 'novnc'
            },
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    $('#shareUrl').val(response.share_url);
                    $('#shareExpiresAt').text(response.expires_at);
                    $('#shareFormContainer').hide();
                    $('#shareResultContainer').show();
                    $('#btnCreateShare').hide();
                    cloudpeLog('success', 'Console share created', response);
                } else {
                    showAlert('error', response.message || 'Failed to create share');
                    btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Create Share');
                }
            },
            error: function(xhr, status, error) {
                showAlert('error', 'Failed to create share: ' + (error || 'Network error'));
                btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Create Share');
            }
        });
    };

    window.copyShareUrl = function() {
        var input = document.getElementById('shareUrl');
        input.select();
        input.setSelectionRange(0, 99999); // For mobile
        try {
            document.execCommand('copy');
            showAlert('success', 'Share URL copied to clipboard!');
        } catch (err) {
            showAlert('error', 'Failed to copy. Please copy manually.');
        }
    };

    // Manage Shares Modal
    window.showShareListModal = function() {
        $('#manageSharesModal').modal('show');
        loadSharesList();
    };

    window.loadSharesList = function() {
        var includeRevoked = $('#showRevokedShares').is(':checked') ? 1 : 0;
        $('#sharesListContainer').html('<p><i class="fas fa-spinner fa-spin"></i> Loading...</p>');

        $.ajax({
            url: ajaxUrl + '?action=console_share_list&service_id=' + serviceId + '&include_revoked=' + includeRevoked,
            type: 'GET',
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    renderSharesList(response.shares);
                } else {
                    $('#sharesListContainer').html('<div class="alert alert-danger">' + (response.message || 'Failed to load shares') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#sharesListContainer').html('<div class="alert alert-danger">Failed to load shares: ' + (error || 'Network error') + '</div>');
            }
        });
    };

    function renderSharesList(shares) {
        if (!shares || shares.length === 0) {
            $('#sharesListContainer').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> No console shares created yet. Click "Create New Share" to generate a shareable console link.</div>');
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-striped table-hover">';
        html += '<thead><tr><th>Name</th><th>Created</th><th>Expires</th><th>Uses</th><th>Status</th><th>Action</th></tr></thead>';
        html += '<tbody>';

        for (var i = 0; i < shares.length; i++) {
            var share = shares[i];
            var statusLabel = '';

            if (share.revoked) {
                statusLabel = '<span class="label label-danger">Revoked</span>';
            } else if (share.is_expired) {
                statusLabel = '<span class="label label-warning">Expired</span>';
            } else {
                statusLabel = '<span class="label label-success">Active</span>';
            }

            var name = share.name || '<em class="text-muted">Unnamed</em>';
            var lastUsed = share.last_used_at ? share.last_used_at : '<span class="text-muted">Never</span>';

            html += '<tr>';
            html += '<td>' + name + '</td>';
            html += '<td><small>' + share.created_at + '</small></td>';
            html += '<td><small>' + share.expires_at + '</small></td>';
            html += '<td>' + share.use_count + '</td>';
            html += '<td>' + statusLabel + '</td>';
            html += '<td>';
            if (!share.revoked && !share.is_expired) {
                html += '<button class="btn btn-danger btn-xs" onclick="revokeShare(' + share.id + ')"><i class="fas fa-ban"></i> Revoke</button>';
            } else {
                html += '<span class="text-muted">-</span>';
            }
            html += '</td>';
            html += '</tr>';
        }

        html += '</tbody></table></div>';
        $('#sharesListContainer').html(html);
    }

    window.revokeShare = function(shareId) {
        if (!confirm('Are you sure you want to revoke this share link? Anyone with this link will no longer be able to access the console.')) {
            return;
        }

        $.ajax({
            url: ajaxUrl + '?action=console_share_revoke&service_id=' + serviceId + '&share_id=' + shareId,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Share link revoked successfully');
                    loadSharesList();
                } else {
                    showAlert('error', response.message || 'Failed to revoke share');
                }
            },
            error: function(xhr, status, error) {
                showAlert('error', 'Failed to revoke share: ' + (error || 'Network error'));
            }
        });
    };
})();
</script>
