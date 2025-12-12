<?php
/**
 * CloudPe Admin Module
 * 
 * Manage CloudPe resources, create Configurable Options, and auto-update.
 * 
 * @author CloudPe
 * @version 3.27
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Current module version - UPDATE THIS WITH EACH RELEASE
define('CLOUDPE_MODULE_VERSION', '3.27');

// Update server URL - GitHub releases
define('CLOUDPE_UPDATE_URL', 'https://raw.githubusercontent.com/Leapswitch-Networks/cloudpe-whmcs/main/version.json');

function cloudpe_admin_config()
{
    return [
        'name' => 'CloudPe Manager',
        'description' => 'Manage CloudPe resources, create Configurable Options, and update modules.',
        'version' => CLOUDPE_MODULE_VERSION,
        'author' => 'CloudPe',
        'language' => 'english',
        'fields' => [
            'update_url' => [
                'FriendlyName' => 'Update Server URL',
                'Type' => 'text',
                'Size' => '60',
                'Default' => 'https://raw.githubusercontent.com/Leapswitch-Networks/cloudpe-whmcs/main/version.json',
                'Description' => 'URL to check for module updates (version.json)',
            ],
            'license_key' => [
                'FriendlyName' => 'License Key',
                'Type' => 'text',
                'Size' => '40',
                'Default' => '',
                'Description' => 'Optional license key for premium features',
            ],
        ],
    ];
}

function cloudpe_admin_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_cloudpe_settings')) {
            Capsule::schema()->create('mod_cloudpe_settings', function ($table) {
                $table->increments('id');
                $table->integer('server_id');
                $table->string('setting_key', 100);
                $table->text('setting_value')->nullable();
                $table->timestamps();
                $table->unique(['server_id', 'setting_key']);
            });
        }
    } catch (\Exception $e) {
        // Table exists
    }
    
    return ['status' => 'success', 'description' => 'CloudPe Manager v' . CLOUDPE_MODULE_VERSION . ' activated.'];
}

function cloudpe_admin_deactivate()
{
    return ['status' => 'success', 'description' => 'CloudPe Manager deactivated.'];
}

/**
 * Check for module updates
 */
function cloudpe_admin_check_update($updateUrl = null)
{
    if (empty($updateUrl)) {
        $updateUrl = CLOUDPE_UPDATE_URL;
    }
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $updateUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'CloudPe-WHMCS/' . CLOUDPE_MODULE_VERSION,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return ['success' => false, 'error' => $error ?: "HTTP {$httpCode}"];
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['version'])) {
            return ['success' => false, 'error' => 'Invalid response from update server'];
        }
        
        $currentVersion = CLOUDPE_MODULE_VERSION;
        $latestVersion = $data['version'];
        
        return [
            'success' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => version_compare($latestVersion, $currentVersion, '>'),
            'download_url' => $data['download_url'] ?? '',
            'changelog' => $data['changelog'] ?? [],
            'released' => $data['released'] ?? '',
            'min_whmcs_version' => $data['min_whmcs_version'] ?? '',
            'min_php_version' => $data['min_php_version'] ?? '',
        ];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Download and install update
 */
function cloudpe_admin_install_update($downloadUrl)
{
    try {
        // Create temp directory
        $tempDir = sys_get_temp_dir() . '/cloudpe_update_' . time();
        $zipFile = $tempDir . '/update.zip';
        
        if (!mkdir($tempDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create temp directory'];
        }
        
        // Download the update
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'CloudPe-WHMCS/' . CLOUDPE_MODULE_VERSION,
        ]);
        
        $zipContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            cloudpe_admin_cleanup_temp($tempDir);
            return ['success' => false, 'error' => 'Download failed: ' . ($error ?: "HTTP {$httpCode}")];
        }
        
        // Save zip file
        if (file_put_contents($zipFile, $zipContent) === false) {
            cloudpe_admin_cleanup_temp($tempDir);
            return ['success' => false, 'error' => 'Failed to save update file'];
        }
        
        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            cloudpe_admin_cleanup_temp($tempDir);
            return ['success' => false, 'error' => 'Failed to open update package'];
        }
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Find and copy modules
        $whmcsRoot = ROOTDIR;
        $extractedModules = $tempDir . '/modules';
        
        if (!is_dir($extractedModules)) {
            cloudpe_admin_cleanup_temp($tempDir);
            return ['success' => false, 'error' => 'Invalid update package structure'];
        }
        
        // Backup current modules
        $backupDir = $whmcsRoot . '/modules/cloudpe_backup_' . date('YmdHis');
        
        $serverModulePath = $whmcsRoot . '/modules/servers/cloudpe';
        $addonModulePath = $whmcsRoot . '/modules/addons/cloudpe_admin';
        
        if (is_dir($serverModulePath)) {
            cloudpe_admin_copy_directory($serverModulePath, $backupDir . '/servers/cloudpe');
        }
        if (is_dir($addonModulePath)) {
            cloudpe_admin_copy_directory($addonModulePath, $backupDir . '/addons/cloudpe_admin');
        }
        
        // Copy new files
        $errors = [];
        
        // Copy server module
        $srcServer = $extractedModules . '/servers/cloudpe';
        if (is_dir($srcServer)) {
            if (!cloudpe_admin_copy_directory($srcServer, $serverModulePath)) {
                $errors[] = 'Failed to update server module';
            }
        }
        
        // Copy addon module
        $srcAddon = $extractedModules . '/addons/cloudpe_admin';
        if (is_dir($srcAddon)) {
            if (!cloudpe_admin_copy_directory($srcAddon, $addonModulePath)) {
                $errors[] = 'Failed to update addon module';
            }
        }
        
        // Cleanup
        cloudpe_admin_cleanup_temp($tempDir);
        
        if (!empty($errors)) {
            return [
                'success' => false, 
                'error' => implode('; ', $errors),
                'backup_path' => $backupDir,
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Update installed successfully!',
            'backup_path' => $backupDir,
        ];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Helper: Copy directory recursively
 */
function cloudpe_admin_copy_directory($src, $dst)
{
    if (!is_dir($src)) {
        return false;
    }
    
    if (!is_dir($dst)) {
        if (!mkdir($dst, 0755, true)) {
            return false;
        }
    }
    
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        
        if (is_dir($srcPath)) {
            if (!cloudpe_admin_copy_directory($srcPath, $dstPath)) {
                closedir($dir);
                return false;
            }
        } else {
            if (!copy($srcPath, $dstPath)) {
                closedir($dir);
                return false;
            }
        }
    }
    closedir($dir);
    
    return true;
}

/**
 * Helper: Clean up temp directory
 */
function cloudpe_admin_cleanup_temp($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            cloudpe_admin_cleanup_temp($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function cloudpe_admin_output($vars)
{
    $modulelink = $vars['modulelink'];
    $action = $_REQUEST['action'] ?? 'dashboard';
    $updateUrl = $vars['update_url'] ?? CLOUDPE_UPDATE_URL;
    
    // Handle AJAX requests
    if (isset($_REQUEST['ajax'])) {
        header('Content-Type: application/json');
        
        switch ($_REQUEST['ajax']) {
            case 'check_update':
                echo json_encode(cloudpe_admin_check_update($updateUrl));
                exit;
                
            case 'install_update':
                $downloadUrl = $_REQUEST['download_url'] ?? '';
                if (empty($downloadUrl)) {
                    echo json_encode(['success' => false, 'error' => 'No download URL provided']);
                } else {
                    echo json_encode(cloudpe_admin_install_update($downloadUrl));
                }
                exit;
                
            case 'load_images':
                echo json_encode(cloudpe_admin_load_images($_REQUEST['server_id'] ?? 0));
                exit;
                
            case 'load_flavors':
                echo json_encode(cloudpe_admin_load_flavors($_REQUEST['server_id'] ?? 0));
                exit;
                
            case 'save_images':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'selected_images', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_flavors':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'selected_flavors', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_image_names':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'image_names', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_flavor_names':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'flavor_names', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_image_prices':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'image_prices', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_flavor_prices':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'flavor_prices', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'save_disks':
                echo json_encode(cloudpe_admin_save_setting($_REQUEST['server_id'], 'disk_sizes', $_REQUEST['data'] ?? ''));
                exit;
                
            case 'create_config_group':
                echo json_encode(cloudpe_admin_create_config_group($_REQUEST));
                exit;
        }
        exit;
    }
    
    $servers = Capsule::table('tblservers')->where('type', 'cloudpe')->get();
    
    if ($servers->isEmpty() && $action !== 'updates') {
        echo '<div class="alert alert-warning">No CloudPe servers configured. <a href="configservers.php">Add a server first</a>.</div>';
        // Still show updates tab
        echo '<ul class="nav nav-tabs" style="margin-bottom: 20px;">';
        echo '<li class="active"><a href="' . $modulelink . '&action=updates">Updates</a></li>';
        echo '</ul>';
        cloudpe_admin_render_updates($modulelink, $updateUrl);
        return;
    }
    
    $selectedServer = $_REQUEST['server_id'] ?? ($servers->isNotEmpty() ? $servers->first()->id : 0);
    $currencies = Capsule::table('tblcurrencies')->orderBy('default', 'desc')->get();
    
    // Navigation
    echo '<ul class="nav nav-tabs" style="margin-bottom: 20px;">';
    echo '<li' . ($action == 'dashboard' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=dashboard&server_id=' . $selectedServer . '">Dashboard</a></li>';
    echo '<li' . ($action == 'images' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=images&server_id=' . $selectedServer . '">Images</a></li>';
    echo '<li' . ($action == 'flavors' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=flavors&server_id=' . $selectedServer . '">Flavors</a></li>';
    echo '<li' . ($action == 'disks' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=disks&server_id=' . $selectedServer . '">Disk Sizes</a></li>';
    echo '<li' . ($action == 'create_group' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=create_group&server_id=' . $selectedServer . '">Create Config Group</a></li>';
    echo '<li' . ($action == 'updates' ? ' class="active"' : '') . '><a href="' . $modulelink . '&action=updates&server_id=' . $selectedServer . '"><i class="fas fa-sync"></i> Updates</a></li>';
    echo '</ul>';
    
    // Server selector (except for updates tab)
    if ($action !== 'updates' && $servers->isNotEmpty()) {
        echo '<div class="row" style="margin-bottom: 20px;"><div class="col-md-4">';
        echo '<form method="get" class="form-inline">';
        echo '<input type="hidden" name="module" value="cloudpe_admin">';
        echo '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">';
        echo '<select name="server_id" class="form-control" onchange="this.form.submit()">';
        foreach ($servers as $server) {
            $sel = ($server->id == $selectedServer) ? ' selected' : '';
            echo '<option value="' . $server->id . '"' . $sel . '>' . htmlspecialchars($server->name) . ' (' . htmlspecialchars($server->hostname) . ')</option>';
        }
        echo '</select></form></div></div>';
    }
    
    // Render content based on action
    switch ($action) {
        case 'updates':
            cloudpe_admin_render_updates($modulelink, $updateUrl);
            break;
        case 'images':
            cloudpe_admin_render_images($modulelink, $selectedServer, $currencies);
            break;
        case 'flavors':
            cloudpe_admin_render_flavors($modulelink, $selectedServer, $currencies);
            break;
        case 'disks':
            cloudpe_admin_render_disks($modulelink, $selectedServer, $currencies);
            break;
        case 'create_group':
            cloudpe_admin_render_create_group($modulelink, $selectedServer, $currencies);
            break;
        default:
            cloudpe_admin_render_dashboard($modulelink, $selectedServer);
    }
}

/**
 * Render Updates Tab
 */
function cloudpe_admin_render_updates($modulelink, $updateUrl)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fas fa-cloud-download-alt"></i> Module Updates</h3></div>';
    echo '<div class="panel-body">';
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h4>Current Installation</h4>';
    echo '<table class="table table-bordered">';
    echo '<tr><td><strong>Module Version</strong></td><td><span class="label label-primary">' . CLOUDPE_MODULE_VERSION . '</span></td></tr>';
    echo '<tr><td><strong>PHP Version</strong></td><td>' . PHP_VERSION . '</td></tr>';
    echo '<tr><td><strong>WHMCS Version</strong></td><td>' . $GLOBALS['CONFIG']['Version'] . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<h4>Update Status</h4>';
    echo '<div id="update-status">';
    echo '<p><i class="fas fa-spinner fa-spin"></i> Checking for updates...</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<hr>';
    
    echo '<div id="update-actions" style="display:none;">';
    echo '<h4>Available Update</h4>';
    echo '<div id="update-info"></div>';
    echo '<button type="button" class="btn btn-success btn-lg" id="btn-install-update" style="display:none;">';
    echo '<i class="fas fa-download"></i> Download & Install Update</button>';
    echo '<div id="update-progress" style="display:none; margin-top: 15px;">';
    echo '<div class="progress"><div class="progress-bar progress-bar-striped active" style="width: 100%">Installing update...</div></div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div id="update-result" style="display:none; margin-top: 15px;"></div>';
    
    echo '</div></div>';
    
    // JavaScript for update functionality
    echo '<script>
    var updateUrl = ' . json_encode($updateUrl) . ';
    var moduleLink = ' . json_encode($modulelink) . ';
    var downloadUrl = "";
    
    $(document).ready(function() {
        checkForUpdates();
    });
    
    function checkForUpdates() {
        $.ajax({
            url: moduleLink + "&ajax=check_update",
            type: "GET",
            dataType: "json",
            success: function(data) {
                if (data.success) {
                    if (data.update_available) {
                        $("#update-status").html(
                            \'<div class="alert alert-warning">\' +
                            \'<i class="fas fa-exclamation-triangle"></i> \' +
                            \'<strong>Update Available!</strong> Version \' + data.latest_version + \' is available.\' +
                            \'</div>\'
                        );
                        
                        var info = \'<table class="table table-bordered">\';
                        info += \'<tr><td><strong>Latest Version</strong></td><td><span class="label label-success">\' + data.latest_version + \'</span></td></tr>\';
                        info += \'<tr><td><strong>Your Version</strong></td><td><span class="label label-default">\' + data.current_version + \'</span></td></tr>\';
                        info += \'<tr><td><strong>Released</strong></td><td>\' + data.released + \'</td></tr>\';
                        info += \'</table>\';
                        
                        if (data.changelog && data.changelog.length > 0) {
                            info += \'<h5>Changelog:</h5><ul>\';
                            data.changelog.forEach(function(item) {
                                info += \'<li>\' + item + \'</li>\';
                            });
                            info += \'</ul>\';
                        }
                        
                        $("#update-info").html(info);
                        $("#update-actions").show();
                        downloadUrl = data.download_url;
                        
                        if (downloadUrl) {
                            $("#btn-install-update").show();
                        }
                    } else {
                        $("#update-status").html(
                            \'<div class="alert alert-success">\' +
                            \'<i class="fas fa-check-circle"></i> \' +
                            \'<strong>Up to date!</strong> You are running the latest version (\' + data.current_version + \').\' +
                            \'</div>\'
                        );
                    }
                } else {
                    $("#update-status").html(
                        \'<div class="alert alert-danger">\' +
                        \'<i class="fas fa-times-circle"></i> \' +
                        \'Failed to check for updates: \' + data.error +
                        \'</div>\' +
                        \'<button class="btn btn-default" onclick="checkForUpdates()">Retry</button>\'
                    );
                }
            },
            error: function() {
                $("#update-status").html(
                    \'<div class="alert alert-danger">\' +
                    \'<i class="fas fa-times-circle"></i> \' +
                    \'Failed to connect to update server.\' +
                    \'</div>\' +
                    \'<button class="btn btn-default" onclick="checkForUpdates()">Retry</button>\'
                );
            }
        });
    }
    
    $("#btn-install-update").click(function() {
        if (!confirm("This will update the CloudPe modules. A backup will be created. Continue?")) {
            return;
        }
        
        $(this).hide();
        $("#update-progress").show();
        
        $.ajax({
            url: moduleLink + "&ajax=install_update&download_url=" + encodeURIComponent(downloadUrl),
            type: "GET",
            dataType: "json",
            success: function(data) {
                $("#update-progress").hide();
                
                if (data.success) {
                    $("#update-result").html(
                        \'<div class="alert alert-success">\' +
                        \'<i class="fas fa-check-circle"></i> \' +
                        \'<strong>Update Installed Successfully!</strong><br>\' +
                        \'Backup saved to: \' + data.backup_path + \'<br><br>\' +
                        \'<strong>Please refresh this page to use the new version.</strong>\' +
                        \'</div>\' +
                        \'<button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>\'
                    ).show();
                } else {
                    $("#update-result").html(
                        \'<div class="alert alert-danger">\' +
                        \'<i class="fas fa-times-circle"></i> \' +
                        \'<strong>Update Failed!</strong><br>\' +
                        data.error +
                        (data.backup_path ? \'<br>Backup saved to: \' + data.backup_path : \'\') +
                        \'</div>\'
                    ).show();
                    $("#btn-install-update").show();
                }
            },
            error: function() {
                $("#update-progress").hide();
                $("#update-result").html(
                    \'<div class="alert alert-danger">\' +
                    \'<i class="fas fa-times-circle"></i> \' +
                    \'Update request failed. Please try again.\' +
                    \'</div>\'
                ).show();
                $("#btn-install-update").show();
            }
        });
    });
    </script>';
}

/**
 * Render Dashboard
 */
function cloudpe_admin_render_dashboard($modulelink, $serverId)
{
    $server = Capsule::table('tblservers')->where('id', $serverId)->first();
    
    echo '<div class="row">';
    
    // Server Info
    echo '<div class="col-md-6">';
    echo '<div class="panel panel-primary">';
    echo '<div class="panel-heading"><h3 class="panel-title">Server Information</h3></div>';
    echo '<div class="panel-body">';
    if ($server) {
        echo '<table class="table">';
        echo '<tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($server->name) . '</td></tr>';
        echo '<tr><td><strong>Hostname:</strong></td><td>' . htmlspecialchars($server->hostname) . '</td></tr>';
        echo '<tr><td><strong>Secure:</strong></td><td>' . ($server->secure ? 'Yes (HTTPS)' : 'No (HTTP)') . '</td></tr>';
        echo '</table>';
    }
    echo '</div></div></div>';
    
    // Module Version
    echo '<div class="col-md-6">';
    echo '<div class="panel panel-info">';
    echo '<div class="panel-heading"><h3 class="panel-title">Module Information</h3></div>';
    echo '<div class="panel-body">';
    echo '<table class="table">';
    echo '<tr><td><strong>Version:</strong></td><td><span class="label label-primary">' . CLOUDPE_MODULE_VERSION . '</span></td></tr>';
    echo '<tr><td><strong>Updates:</strong></td><td><a href="' . $modulelink . '&action=updates">Check for Updates</a></td></tr>';
    echo '</table>';
    echo '</div></div></div>';
    
    echo '</div>';
    
    // Quick Links
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Quick Actions</h3></div>';
    echo '<div class="panel-body">';
    echo '<a href="' . $modulelink . '&action=images&server_id=' . $serverId . '" class="btn btn-default"><i class="fas fa-image"></i> Manage Images</a> ';
    echo '<a href="' . $modulelink . '&action=flavors&server_id=' . $serverId . '" class="btn btn-default"><i class="fas fa-server"></i> Manage Flavors</a> ';
    echo '<a href="' . $modulelink . '&action=disks&server_id=' . $serverId . '" class="btn btn-default"><i class="fas fa-hdd"></i> Manage Disk Sizes</a> ';
    echo '<a href="' . $modulelink . '&action=create_group&server_id=' . $serverId . '" class="btn btn-success"><i class="fas fa-plus"></i> Create Config Group</a>';
    echo '</div></div>';
    
    // Existing Config Groups
    $configGroups = Capsule::table('tblproductconfiggroups')
        ->where('name', 'like', 'CloudPe%')
        ->orWhere('name', 'like', '%VPS%')
        ->get();
    
    if ($configGroups->isNotEmpty()) {
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading"><h3 class="panel-title">Existing Configurable Option Groups</h3></div>';
        echo '<div class="panel-body">';
        echo '<table class="table table-striped">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Options</th><th>Linked Products</th></tr></thead><tbody>';
        
        foreach ($configGroups as $group) {
            $optionCount = Capsule::table('tblproductconfigoptions')->where('gid', $group->id)->count();
            $linkedProducts = Capsule::table('tblproductconfiglinks')->where('gid', $group->id)->count();
            echo '<tr>';
            echo '<td>' . $group->id . '</td>';
            echo '<td>' . htmlspecialchars($group->name) . '</td>';
            echo '<td>' . $optionCount . '</td>';
            echo '<td>' . $linkedProducts . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div></div>';
    }
}

// Include the rest of the functions (images, flavors, disks, create_group)
// These are helper functions that remain largely the same

function cloudpe_admin_load_images($serverId)
{
    $server = Capsule::table('tblservers')->where('id', $serverId)->first();
    if (!$server) {
        return ['success' => false, 'error' => 'Server not found'];
    }
    
    require_once ROOTDIR . '/modules/servers/cloudpe/lib/CloudPeAPI.php';
    
    $api = new CloudPeAPI([
        'serverhostname' => $server->hostname,
        'serverusername' => $server->username,
        'serverpassword' => decrypt($server->password),
        'serveraccesshash' => $server->accesshash,
        'serversecure' => $server->secure,
    ]);
    
    return $api->listImages();
}

function cloudpe_admin_load_flavors($serverId)
{
    $server = Capsule::table('tblservers')->where('id', $serverId)->first();
    if (!$server) {
        return ['success' => false, 'error' => 'Server not found'];
    }
    
    require_once ROOTDIR . '/modules/servers/cloudpe/lib/CloudPeAPI.php';
    
    $api = new CloudPeAPI([
        'serverhostname' => $server->hostname,
        'serverusername' => $server->username,
        'serverpassword' => decrypt($server->password),
        'serveraccesshash' => $server->accesshash,
        'serversecure' => $server->secure,
    ]);
    
    return $api->listFlavors();
}

function cloudpe_admin_save_setting($serverId, $key, $value)
{
    try {
        Capsule::table('mod_cloudpe_settings')->updateOrInsert(
            ['server_id' => $serverId, 'setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
        return ['success' => true];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function cloudpe_admin_get_setting($serverId, $key)
{
    $row = Capsule::table('mod_cloudpe_settings')
        ->where('server_id', $serverId)
        ->where('setting_key', $key)
        ->first();
    
    return $row ? $row->setting_value : null;
}

function cloudpe_admin_render_images($modulelink, $serverId, $currencies)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading">';
    echo '<h3 class="panel-title">Manage Images</h3>';
    echo '</div>';
    echo '<div class="panel-body">';
    echo '<button class="btn btn-primary" onclick="loadImages()"><i class="fas fa-sync"></i> Load Images from API</button>';
    echo '<button class="btn btn-default" onclick="cloudpeSelectAll(\'images\')">Select All</button>';
    echo '<button class="btn btn-default" onclick="cloudpeSelectNone(\'images\')">Select None</button>';
    echo '<button class="btn btn-success" onclick="saveImages()"><i class="fas fa-save"></i> Save</button>';
    echo '<hr><div id="images-container"><p class="text-muted">Click "Load Images from API" to fetch available images.</p></div>';
    echo '</div></div>';
    
    $savedImages = cloudpe_admin_get_setting($serverId, 'selected_images');
    $savedNames = cloudpe_admin_get_setting($serverId, 'image_names');
    $savedPrices = cloudpe_admin_get_setting($serverId, 'image_prices');
    
    echo '<script>
    var serverId = ' . $serverId . ';
    var moduleLink = ' . json_encode($modulelink) . ';
    var savedImages = ' . ($savedImages ?: '[]') . ';
    var savedNames = ' . ($savedNames ?: '{}') . ';
    var savedPrices = ' . ($savedPrices ?: '{}') . ';
    var currencies = ' . json_encode($currencies) . ';
    
    function loadImages() {
        $("#images-container").html("<p><i class=\"fas fa-spinner fa-spin\"></i> Loading...</p>");
        $.get(moduleLink + "&ajax=load_images&server_id=" + serverId, function(data) {
            if (data.success) {
                renderImages(data.images);
            } else {
                $("#images-container").html("<div class=\"alert alert-danger\">" + data.error + "</div>");
            }
        });
    }
    
    function renderImages(images) {
        var html = "<table class=\"table table-bordered table-striped\"><thead><tr>";
        html += "<th width=\"30\"><input type=\"checkbox\" id=\"select-all-images\"></th>";
        html += "<th>Image Name</th><th>Display Name</th>";
        currencies.forEach(function(c) { html += "<th>" + c.code + " /mo</th>"; });
        html += "</tr></thead><tbody>";
        
        images.forEach(function(img) {
            var checked = savedImages.includes(img.id) ? " checked" : "";
            var displayName = savedNames[img.id] || img.name;
            html += "<tr>";
            html += "<td><input type=\"checkbox\" class=\"img-check\" data-id=\"" + img.id + "\"" + checked + "></td>";
            html += "<td>" + img.name + "</td>";
            html += "<td><input type=\"text\" class=\"form-control input-sm img-name\" data-id=\"" + img.id + "\" value=\"" + displayName + "\"></td>";
            currencies.forEach(function(c) {
                var price = (savedPrices[img.id] && savedPrices[img.id][c.id]) || "0.00";
                html += "<td><input type=\"text\" class=\"form-control input-sm img-price\" data-id=\"" + img.id + "\" data-currency=\"" + c.id + "\" value=\"" + price + "\" style=\"width:80px\"></td>";
            });
            html += "</tr>";
        });
        
        html += "</tbody></table>";
        $("#images-container").html(html);
    }
    
    function cloudpeSelectAll(type) {
        $("." + (type === "images" ? "img" : "flv") + "-check").prop("checked", true);
    }
    
    function cloudpeSelectNone(type) {
        $("." + (type === "images" ? "img" : "flv") + "-check").prop("checked", false);
    }
    
    function saveImages() {
        var selected = [];
        var names = {};
        var prices = {};
        
        $(".img-check:checked").each(function() { selected.push($(this).data("id")); });
        $(".img-name").each(function() { names[$(this).data("id")] = $(this).val(); });
        $(".img-price").each(function() {
            var id = $(this).data("id");
            var curr = $(this).data("currency");
            if (!prices[id]) prices[id] = {};
            prices[id][curr] = $(this).val();
        });
        
        $.post(moduleLink + "&ajax=save_images&server_id=" + serverId, {data: JSON.stringify(selected)});
        $.post(moduleLink + "&ajax=save_image_names&server_id=" + serverId, {data: JSON.stringify(names)});
        $.post(moduleLink + "&ajax=save_image_prices&server_id=" + serverId, {data: JSON.stringify(prices)}, function() {
            alert("Images saved!");
        });
    }
    </script>';
}

function cloudpe_admin_render_flavors($modulelink, $serverId, $currencies)
{
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Manage Flavors</h3></div>';
    echo '<div class="panel-body">';
    echo '<button class="btn btn-primary" onclick="loadFlavors()"><i class="fas fa-sync"></i> Load Flavors from API</button>';
    echo '<button class="btn btn-default" onclick="cloudpeSelectAll(\'flavors\')">Select All</button>';
    echo '<button class="btn btn-default" onclick="cloudpeSelectNone(\'flavors\')">Select None</button>';
    echo '<button class="btn btn-success" onclick="saveFlavors()"><i class="fas fa-save"></i> Save</button>';
    echo '<hr><div id="flavors-container"><p class="text-muted">Click "Load Flavors from API" to fetch available flavors.</p></div>';
    echo '</div></div>';
    
    $savedFlavors = cloudpe_admin_get_setting($serverId, 'selected_flavors');
    $savedNames = cloudpe_admin_get_setting($serverId, 'flavor_names');
    $savedPrices = cloudpe_admin_get_setting($serverId, 'flavor_prices');
    
    echo '<script>
    var serverId = ' . $serverId . ';
    var moduleLink = ' . json_encode($modulelink) . ';
    var savedFlavors = ' . ($savedFlavors ?: '[]') . ';
    var savedFlavorNames = ' . ($savedNames ?: '{}') . ';
    var savedFlavorPrices = ' . ($savedPrices ?: '{}') . ';
    var currencies = ' . json_encode($currencies) . ';
    
    function loadFlavors() {
        $("#flavors-container").html("<p><i class=\"fas fa-spinner fa-spin\"></i> Loading...</p>");
        $.get(moduleLink + "&ajax=load_flavors&server_id=" + serverId, function(data) {
            if (data.success) {
                renderFlavors(data.flavors);
            } else {
                $("#flavors-container").html("<div class=\"alert alert-danger\">" + data.error + "</div>");
            }
        });
    }
    
    function renderFlavors(flavors) {
        var html = "<table class=\"table table-bordered table-striped\"><thead><tr>";
        html += "<th width=\"30\"><input type=\"checkbox\" id=\"select-all-flavors\"></th>";
        html += "<th>Flavor</th><th>vCPU</th><th>RAM</th><th>Display Name</th>";
        currencies.forEach(function(c) { html += "<th>" + c.code + " /mo</th>"; });
        html += "</tr></thead><tbody>";
        
        flavors.forEach(function(flv) {
            var checked = savedFlavors.includes(flv.id) ? " checked" : "";
            var ram = Math.round(flv.ram / 1024 * 10) / 10;
            var displayName = savedFlavorNames[flv.id] || (flv.vcpus + " vCPU, " + ram + " GB RAM");
            html += "<tr>";
            html += "<td><input type=\"checkbox\" class=\"flv-check\" data-id=\"" + flv.id + "\"" + checked + "></td>";
            html += "<td>" + flv.name + "</td>";
            html += "<td>" + flv.vcpus + "</td>";
            html += "<td>" + ram + " GB</td>";
            html += "<td><input type=\"text\" class=\"form-control input-sm flv-name\" data-id=\"" + flv.id + "\" value=\"" + displayName + "\"></td>";
            currencies.forEach(function(c) {
                var price = (savedFlavorPrices[flv.id] && savedFlavorPrices[flv.id][c.id]) || "0.00";
                html += "<td><input type=\"text\" class=\"form-control input-sm flv-price\" data-id=\"" + flv.id + "\" data-currency=\"" + c.id + "\" value=\"" + price + "\" style=\"width:80px\"></td>";
            });
            html += "</tr>";
        });
        
        html += "</tbody></table>";
        $("#flavors-container").html(html);
    }
    
    function saveFlavors() {
        var selected = [];
        var names = {};
        var prices = {};
        
        $(".flv-check:checked").each(function() { selected.push($(this).data("id")); });
        $(".flv-name").each(function() { names[$(this).data("id")] = $(this).val(); });
        $(".flv-price").each(function() {
            var id = $(this).data("id");
            var curr = $(this).data("currency");
            if (!prices[id]) prices[id] = {};
            prices[id][curr] = $(this).val();
        });
        
        $.post(moduleLink + "&ajax=save_flavors&server_id=" + serverId, {data: JSON.stringify(selected)});
        $.post(moduleLink + "&ajax=save_flavor_names&server_id=" + serverId, {data: JSON.stringify(names)});
        $.post(moduleLink + "&ajax=save_flavor_prices&server_id=" + serverId, {data: JSON.stringify(prices)}, function() {
            alert("Flavors saved!");
        });
    }
    </script>';
}

function cloudpe_admin_render_disks($modulelink, $serverId, $currencies)
{
    $savedDisks = cloudpe_admin_get_setting($serverId, 'disk_sizes');
    $disks = $savedDisks ? json_decode($savedDisks, true) : [];
    
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Manage Disk Sizes</h3></div>';
    echo '<div class="panel-body">';
    echo '<button class="btn btn-primary" onclick="addDisk()"><i class="fas fa-plus"></i> Add Disk Size</button>';
    echo '<button class="btn btn-success" onclick="saveDisks()"><i class="fas fa-save"></i> Save</button>';
    echo '<hr><table class="table table-bordered" id="disks-table"><thead><tr>';
    echo '<th>Size (GB)</th><th>Display Name</th>';
    foreach ($currencies as $c) echo '<th>' . $c->code . ' /mo</th>';
    echo '<th>Action</th></tr></thead><tbody>';
    
    foreach ($disks as $i => $disk) {
        echo '<tr>';
        echo '<td><input type="number" class="form-control input-sm disk-size" value="' . ($disk['size'] ?? '') . '"></td>';
        echo '<td><input type="text" class="form-control input-sm disk-name" value="' . htmlspecialchars($disk['name'] ?? '') . '"></td>';
        foreach ($currencies as $c) {
            $price = $disk['prices'][$c->id] ?? '0.00';
            echo '<td><input type="text" class="form-control input-sm disk-price" data-currency="' . $c->id . '" value="' . $price . '" style="width:80px"></td>';
        }
        echo '<td><button class="btn btn-danger btn-xs" onclick="$(this).closest(\'tr\').remove()">Del</button></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div></div>';
    
    echo '<script>
    var moduleLink = ' . json_encode($modulelink) . ';
    var serverId = ' . $serverId . ';
    var currencies = ' . json_encode($currencies) . ';
    
    function addDisk() {
        var html = "<tr><td><input type=\"number\" class=\"form-control input-sm disk-size\"></td>";
        html += "<td><input type=\"text\" class=\"form-control input-sm disk-name\"></td>";
        currencies.forEach(function(c) {
            html += "<td><input type=\"text\" class=\"form-control input-sm disk-price\" data-currency=\"" + c.id + "\" value=\"0.00\" style=\"width:80px\"></td>";
        });
        html += "<td><button class=\"btn btn-danger btn-xs\" onclick=\"$(this).closest(\'tr\').remove()\">Del</button></td></tr>";
        $("#disks-table tbody").append(html);
    }
    
    function saveDisks() {
        var disks = [];
        $("#disks-table tbody tr").each(function() {
            var size = $(this).find(".disk-size").val();
            var name = $(this).find(".disk-name").val();
            var prices = {};
            $(this).find(".disk-price").each(function() {
                prices[$(this).data("currency")] = $(this).val();
            });
            if (size) disks.push({size: size, name: name, prices: prices});
        });
        
        $.post(moduleLink + "&ajax=save_disks&server_id=" + serverId, {data: JSON.stringify(disks)}, function() {
            alert("Disk sizes saved!");
        });
    }
    </script>';
}

function cloudpe_admin_render_create_group($modulelink, $serverId, $currencies)
{
    $products = Capsule::table('tblproducts')
        ->where('servertype', 'cloudpe')
        ->get();
    
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Create Configurable Options Group</h3></div>';
    echo '<div class="panel-body">';
    
    echo '<form id="create-group-form">';
    echo '<div class="form-group">';
    echo '<label>Group Name</label>';
    echo '<input type="text" name="group_name" class="form-control" placeholder="e.g., Linux VPS Options" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Link to Products</label>';
    echo '<button type="button" class="btn btn-xs btn-default" onclick="$(\'input[name=products]\').prop(\'checked\',true)">Select All</button> ';
    echo '<button type="button" class="btn btn-xs btn-default" onclick="$(\'input[name=products]\').prop(\'checked\',false)">Select None</button>';
    echo '<div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:10px;margin-top:5px;">';
    foreach ($products as $p) {
        echo '<label style="display:block;font-weight:normal;"><input type="checkbox" name="products" value="' . $p->id . '"> ' . htmlspecialchars($p->name) . '</label>';
    }
    echo '</div></div>';
    
    echo '<div class="form-group">';
    echo '<label>Include Options</label><br>';
    echo '<label style="font-weight:normal;"><input type="checkbox" name="include_os" checked> Operating System (Images)</label><br>';
    echo '<label style="font-weight:normal;"><input type="checkbox" name="include_size" checked> Server Size (Flavors)</label><br>';
    echo '<label style="font-weight:normal;"><input type="checkbox" name="include_disk" checked> Disk Space</label>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Billing Cycle Multipliers (from monthly)</label>';
    echo '<div class="row">';
    echo '<div class="col-md-2"><label class="small">Quarterly</label><input type="number" step="0.1" name="mult_q" class="form-control input-sm" value="3"></div>';
    echo '<div class="col-md-2"><label class="small">Semi-Annual</label><input type="number" step="0.1" name="mult_s" class="form-control input-sm" value="6"></div>';
    echo '<div class="col-md-2"><label class="small">Annual</label><input type="number" step="0.1" name="mult_a" class="form-control input-sm" value="12"></div>';
    echo '<div class="col-md-2"><label class="small">Biennial</label><input type="number" step="0.1" name="mult_b" class="form-control input-sm" value="24"></div>';
    echo '<div class="col-md-2"><label class="small">Triennial</label><input type="number" step="0.1" name="mult_t" class="form-control input-sm" value="36"></div>';
    echo '</div></div>';
    
    echo '<button type="submit" class="btn btn-success btn-lg"><i class="fas fa-plus"></i> Create Configurable Options Group</button>';
    echo '</form>';
    
    echo '<div id="create-result" style="margin-top:15px;"></div>';
    echo '</div></div>';
    
    echo '<script>
    var moduleLink = ' . json_encode($modulelink) . ';
    var serverId = ' . $serverId . ';
    
    $("#create-group-form").submit(function(e) {
        e.preventDefault();
        
        var products = [];
        $("input[name=products]:checked").each(function() { products.push($(this).val()); });
        
        if (products.length === 0) {
            alert("Please select at least one product.");
            return;
        }
        
        var data = {
            server_id: serverId,
            group_name: $("input[name=group_name]").val(),
            products: products,
            include_os: $("input[name=include_os]").is(":checked") ? 1 : 0,
            include_size: $("input[name=include_size]").is(":checked") ? 1 : 0,
            include_disk: $("input[name=include_disk]").is(":checked") ? 1 : 0,
            mult_q: $("input[name=mult_q]").val(),
            mult_s: $("input[name=mult_s]").val(),
            mult_a: $("input[name=mult_a]").val(),
            mult_b: $("input[name=mult_b]").val(),
            mult_t: $("input[name=mult_t]").val()
        };
        
        $("#create-result").html("<p><i class=\"fas fa-spinner fa-spin\"></i> Creating...</p>");
        
        $.post(moduleLink + "&ajax=create_config_group", data, function(result) {
            if (result.success) {
                $("#create-result").html("<div class=\"alert alert-success\"><i class=\"fas fa-check\"></i> " + result.message + "</div>");
            } else {
                $("#create-result").html("<div class=\"alert alert-danger\">" + result.error + "</div>");
            }
        });
    });
    </script>';
}

function cloudpe_admin_create_config_group($data)
{
    try {
        $serverId = $data['server_id'];
        $groupName = $data['group_name'];
        $products = $data['products'] ?? [];
        $includeOs = !empty($data['include_os']);
        $includeSize = !empty($data['include_size']);
        $includeDisk = !empty($data['include_disk']);
        
        $multQ = floatval($data['mult_q'] ?? 3);
        $multS = floatval($data['mult_s'] ?? 6);
        $multA = floatval($data['mult_a'] ?? 12);
        $multB = floatval($data['mult_b'] ?? 24);
        $multT = floatval($data['mult_t'] ?? 36);
        
        $currencies = Capsule::table('tblcurrencies')->get();
        
        // Create group
        $groupId = Capsule::table('tblproductconfiggroups')->insertGetId([
            'name' => $groupName,
            'description' => 'Created by CloudPe Manager',
        ]);
        
        // Link products
        foreach ($products as $pid) {
            Capsule::table('tblproductconfiglinks')->insert(['gid' => $groupId, 'pid' => $pid]);
        }
        
        $order = 0;
        
        // Add OS options
        if ($includeOs) {
            $images = json_decode(cloudpe_admin_get_setting($serverId, 'selected_images') ?: '[]', true);
            $imageNames = json_decode(cloudpe_admin_get_setting($serverId, 'image_names') ?: '{}', true);
            $imagePrices = json_decode(cloudpe_admin_get_setting($serverId, 'image_prices') ?: '{}', true);
            
            if (!empty($images)) {
                $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid' => $groupId,
                    'optionname' => 'Operating System',
                    'optiontype' => 1,
                    'qtyminimum' => 0,
                    'qtymaximum' => 0,
                    'order' => $order++,
                    'hidden' => 0,
                ]);
                
                $subOrder = 0;
                foreach ($images as $imgId) {
                    $name = $imageNames[$imgId] ?? $imgId;
                    $subId = Capsule::table('tblproductconfigoptionssub')->insertGetId([
                        'configid' => $optionId,
                        'optionname' => $imgId . '|' . $name,
                        'sortorder' => $subOrder++,
                        'hidden' => 0,
                    ]);
                    
                    foreach ($currencies as $curr) {
                        $monthly = floatval($imagePrices[$imgId][$curr->id] ?? 0);
                        Capsule::table('tblpricing')->insert([
                            'type' => 'configoptions',
                            'currency' => $curr->id,
                            'relid' => $subId,
                            'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                            'monthly' => $monthly,
                            'quarterly' => $monthly * $multQ,
                            'semiannually' => $monthly * $multS,
                            'annually' => $monthly * $multA,
                            'biennially' => $monthly * $multB,
                            'triennially' => $monthly * $multT,
                        ]);
                    }
                }
            }
        }
        
        // Add Size options
        if ($includeSize) {
            $flavors = json_decode(cloudpe_admin_get_setting($serverId, 'selected_flavors') ?: '[]', true);
            $flavorNames = json_decode(cloudpe_admin_get_setting($serverId, 'flavor_names') ?: '{}', true);
            $flavorPrices = json_decode(cloudpe_admin_get_setting($serverId, 'flavor_prices') ?: '{}', true);
            
            if (!empty($flavors)) {
                $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid' => $groupId,
                    'optionname' => 'Server Size',
                    'optiontype' => 1,
                    'qtyminimum' => 0,
                    'qtymaximum' => 0,
                    'order' => $order++,
                    'hidden' => 0,
                ]);
                
                $subOrder = 0;
                foreach ($flavors as $flvId) {
                    $name = $flavorNames[$flvId] ?? $flvId;
                    $subId = Capsule::table('tblproductconfigoptionssub')->insertGetId([
                        'configid' => $optionId,
                        'optionname' => $flvId . '|' . $name,
                        'sortorder' => $subOrder++,
                        'hidden' => 0,
                    ]);
                    
                    foreach ($currencies as $curr) {
                        $monthly = floatval($flavorPrices[$flvId][$curr->id] ?? 0);
                        Capsule::table('tblpricing')->insert([
                            'type' => 'configoptions',
                            'currency' => $curr->id,
                            'relid' => $subId,
                            'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                            'monthly' => $monthly,
                            'quarterly' => $monthly * $multQ,
                            'semiannually' => $monthly * $multS,
                            'annually' => $monthly * $multA,
                            'biennially' => $monthly * $multB,
                            'triennially' => $monthly * $multT,
                        ]);
                    }
                }
            }
        }
        
        // Add Disk options
        if ($includeDisk) {
            $disks = json_decode(cloudpe_admin_get_setting($serverId, 'disk_sizes') ?: '[]', true);
            
            if (!empty($disks)) {
                $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid' => $groupId,
                    'optionname' => 'Disk Space',
                    'optiontype' => 1,
                    'qtyminimum' => 0,
                    'qtymaximum' => 0,
                    'order' => $order++,
                    'hidden' => 0,
                ]);
                
                $subOrder = 0;
                foreach ($disks as $disk) {
                    $size = $disk['size'];
                    $name = $disk['name'] ?: $size . ' GB';
                    $subId = Capsule::table('tblproductconfigoptionssub')->insertGetId([
                        'configid' => $optionId,
                        'optionname' => $size . '|' . $name,
                        'sortorder' => $subOrder++,
                        'hidden' => 0,
                    ]);
                    
                    foreach ($currencies as $curr) {
                        $monthly = floatval($disk['prices'][$curr->id] ?? 0);
                        Capsule::table('tblpricing')->insert([
                            'type' => 'configoptions',
                            'currency' => $curr->id,
                            'relid' => $subId,
                            'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                            'monthly' => $monthly,
                            'quarterly' => $monthly * $multQ,
                            'semiannually' => $monthly * $multS,
                            'annually' => $monthly * $multA,
                            'biennially' => $monthly * $multB,
                            'triennially' => $monthly * $multT,
                        ]);
                    }
                }
            }
        }
        
        return ['success' => true, 'message' => "Config group '{$groupName}' created with ID {$groupId}"];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
