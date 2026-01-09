<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Virtual Machine Overview</h3>
    </div>
    <div class="panel-body">
        {if $cloudpe_message}
            <div class="alert alert-{$cloudpe_message_type|default:'info'} alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                {$cloudpe_message}
            </div>
        {/if}
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
                                <td>{$status_label}</td>
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
                {if $status == 'ACTIVE'}
                    <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=ClientStop" 
                       class="btn btn-warning btn-block" 
                       onclick="return confirm('Are you sure you want to stop the VM?')">
                        <i class="fas fa-stop"></i> Stop VM
                    </a>
                    <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=ClientRestart" 
                       class="btn btn-info btn-block"
                       onclick="return confirm('Are you sure you want to restart the VM?')">
                        <i class="fas fa-sync"></i> Restart VM
                    </a>
                    <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=ClientConsole" 
                       class="btn btn-primary btn-block" target="_blank">
                        <i class="fas fa-terminal"></i> VNC Console
                    </a>
                    <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=ClientChangePassword" 
                       class="btn btn-danger btn-block"
                       onclick="return confirm('Are you sure you want to reset the root password? The new password will be shown in your service details.')">
                        <i class="fas fa-key"></i> Reset Password
                    </a>
                {elseif $status == 'SHUTOFF' || $status == 'SHELVED' || $status == 'STOPPED' || $status == 'SHELVED_OFFLOADED'}
                    <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=ClientStart" 
                       class="btn btn-success btn-block">
                        <i class="fas fa-play"></i> Start VM
                    </a>
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
