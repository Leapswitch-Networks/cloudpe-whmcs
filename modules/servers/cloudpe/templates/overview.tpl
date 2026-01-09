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
                        <button type="button" class="btn btn-primary btn-block" data-action="console">
                            <i class="fas fa-terminal"></i> VNC Console
                        </button>
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
    });
})();
</script>
