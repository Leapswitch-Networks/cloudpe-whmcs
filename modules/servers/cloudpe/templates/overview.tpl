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
                        <button type="button" class="btn btn-warning btn-block" id="btn-stop" onclick="cloudpeAction('ClientStop', 'Stopping VM...')">
                            <i class="fas fa-stop"></i> Stop VM
                        </button>
                        <button type="button" class="btn btn-info btn-block" id="btn-restart" onclick="cloudpeAction('ClientRestart', 'Restarting VM...')">
                            <i class="fas fa-sync"></i> Restart VM
                        </button>
                        <button type="button" class="btn btn-primary btn-block" id="btn-console" onclick="cloudpeConsole()">
                            <i class="fas fa-terminal"></i> VNC Console
                        </button>
                        <button type="button" class="btn btn-danger btn-block" id="btn-password" onclick="cloudpeAction('ClientChangePassword', 'Resetting password...')">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    {elseif $status == 'SHUTOFF' || $status == 'SHELVED' || $status == 'STOPPED' || $status == 'SHELVED_OFFLOADED'}
                        <button type="button" class="btn btn-success btn-block" id="btn-start" onclick="cloudpeAction('ClientStart', 'Starting VM...')">
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
var serviceId = {$serviceid};
var currentStatus = '{$status}';

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
    var html = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
               '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
               '<span aria-hidden="true">&times;</span></button>' +
               '<i class="fas fa-' + (type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')) + '"></i> ' +
               message + '</div>';

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
function cloudpeAction(action, loadingMessage) {
    cloudpeLog('info', 'Action initiated: ' + action);

    // Confirm destructive actions
    if (action === 'ClientStop' && !confirm('Are you sure you want to stop the VM?')) {
        cloudpeLog('info', 'Action cancelled by user');
        return;
    }
    if (action === 'ClientRestart' && !confirm('Are you sure you want to restart the VM?')) {
        cloudpeLog('info', 'Action cancelled by user');
        return;
    }
    if (action === 'ClientChangePassword' && !confirm('Are you sure you want to reset the root password?')) {
        cloudpeLog('info', 'Action cancelled by user');
        return;
    }

    disableButtons();
    showAlert('info', '<i class="fas fa-spinner fa-spin"></i> ' + loadingMessage);

    var url = 'clientarea.php?action=productdetails&id=' + serviceId + '&modop=custom&a=' + action;

    cloudpeLog('info', 'Sending AJAX request', { url: url, action: action });

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        timeout: 60000, // 60 second timeout for long operations
        success: function(response) {
            cloudpeLog('info', 'Response received', response);

            if (response.success) {
                cloudpeLog('success', action + ' completed successfully');
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
            } else {
                errorMsg += error || 'Unknown error';
            }

            showAlert('error', errorMsg);
            enableButtons();
        }
    });
}

// Console action handler (opens in new window)
function cloudpeConsole() {
    cloudpeLog('info', 'Console action initiated');

    disableButtons();
    showAlert('info', '<i class="fas fa-spinner fa-spin"></i> Opening VNC console...');

    var url = 'clientarea.php?action=productdetails&id=' + serviceId + '&modop=custom&a=ClientConsole';

    cloudpeLog('info', 'Sending console AJAX request', { url: url });

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        timeout: 30000,
        success: function(response) {
            cloudpeLog('info', 'Console response received', response);

            if (response.success && response.url) {
                cloudpeLog('success', 'Console URL received', response.url);
                showAlert('success', 'Console opened in new window');
                window.open(response.url, '_blank');
            } else {
                cloudpeLog('error', 'Console failed', response.message);
                showAlert('error', response.message || 'Failed to get console URL');
            }
            enableButtons();
        },
        error: function(xhr, status, error) {
            cloudpeLog('error', 'Console AJAX request failed', { status: status, error: error });
            showAlert('error', 'Failed to open console: ' + (error || 'Unknown error'));
            enableButtons();
        }
    });
}

// Log page load
$(document).ready(function() {
    cloudpeLog('info', 'CloudPe VM Control loaded', { serviceId: serviceId, status: currentStatus });
});
</script>
