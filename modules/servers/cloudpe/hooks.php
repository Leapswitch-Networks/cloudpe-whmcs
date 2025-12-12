<?php
/**
 * CloudPe WHMCS Module Hooks
 * 
 * Handles validation and UI enhancements for CloudPe services
 * 
 * @version 3.17
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Hook: Validate configurable options on upgrade
 * Prevents selecting smaller disk size than current
 */
add_hook('ShoppingCartValidateCheckout', 1, function($vars) {
    // Only check for upgrades
    if (empty($_SESSION['upgradealiases'])) {
        return;
    }
    
    foreach ($_SESSION['upgradealiases'] as $upgradeKey => $upgradeData) {
        // Get the service being upgraded
        $serviceId = $upgradeData['serviceid'] ?? 0;
        if (empty($serviceId)) {
            continue;
        }
        
        // Check if this is a CloudPe service
        $service = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $serviceId)
            ->where('tblproducts.servertype', 'cloudpe')
            ->select('tblhosting.*', 'tblproducts.id as product_id')
            ->first();
        
        if (!$service) {
            continue;
        }
        
        // Get current disk size from the VM
        $currentDiskSize = cloudpe_get_current_disk_size($serviceId, $service->product_id);
        
        if ($currentDiskSize <= 0) {
            continue; // Can't determine current size, skip validation
        }
        
        // Check if there's a disk size in the new configurable options
        $newDiskSize = 0;
        
        if (!empty($vars['configoptions'])) {
            foreach ($vars['configoptions'] as $optionId => $value) {
                $option = Capsule::table('tblproductconfigoptions')
                    ->where('id', $optionId)
                    ->first();
                
                if ($option && in_array($option->optionname, ['Disk Space', 'Volume Size'])) {
                    // Value format is either the size directly or "size|label"
                    $parts = explode('|', $value);
                    $newDiskSize = (int)$parts[0];
                    break;
                }
            }
        }
        
        // Also check POST data for configoptions format
        if ($newDiskSize == 0 && !empty($_POST['configoption'])) {
            foreach ($_POST['configoption'] as $optionId => $value) {
                $option = Capsule::table('tblproductconfigoptions')
                    ->where('id', $optionId)
                    ->first();
                
                if ($option && in_array($option->optionname, ['Disk Space', 'Volume Size'])) {
                    // Get the sub-option value
                    $subOption = Capsule::table('tblproductconfigoptionssub')
                        ->where('id', $value)
                        ->first();
                    
                    if ($subOption) {
                        $parts = explode('|', $subOption->optionname);
                        $newDiskSize = (int)$parts[0];
                    }
                    break;
                }
            }
        }
        
        if ($newDiskSize > 0 && $newDiskSize < $currentDiskSize) {
            return [
                'error' => "Disk size cannot be reduced. Your current disk is {$currentDiskSize}GB. Please select {$currentDiskSize}GB or larger.",
            ];
        }
    }
});

/**
 * Hook: Add warning message in client area for disk upgrades
 */
add_hook('ClientAreaPageUpgrade', 1, function($vars) {
    $serviceId = $_GET['id'] ?? 0;
    
    if (empty($serviceId)) {
        return;
    }
    
    // Check if CloudPe service
    $service = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblhosting.id', $serviceId)
        ->where('tblproducts.servertype', 'cloudpe')
        ->select('tblhosting.*', 'tblproducts.id as product_id')
        ->first();
    
    if (!$service) {
        return;
    }
    
    $currentDiskSize = cloudpe_get_current_disk_size($serviceId, $service->product_id);
    
    if ($currentDiskSize > 0) {
        return [
            'cloudpe_current_disk' => $currentDiskSize,
            'cloudpe_disk_warning' => "Note: Disk size can only be increased. Your current disk is {$currentDiskSize}GB.",
        ];
    }
});

/**
 * Helper: Get current disk size for a CloudPe service
 */
function cloudpe_get_current_disk_size($serviceId, $productId)
{
    // First try to get from API
    try {
        $serverId = '';
        
        // Get VM ID from custom field
        $field = Capsule::table('tblcustomfields')
            ->where('relid', $productId)
            ->where('type', 'product')
            ->where('fieldname', 'VM ID')
            ->first();
        
        if ($field) {
            $value = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $field->id)
                ->where('relid', $serviceId)
                ->first();
            
            $serverId = $value->value ?? '';
        }
        
        if (empty($serverId)) {
            return 0;
        }
        
        // Get server credentials
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        
        if (!$server) {
            return 0;
        }
        
        // Load API
        $apiPath = ROOTDIR . '/modules/servers/cloudpe/lib/CloudPeAPI.php';
        if (!file_exists($apiPath)) {
            return 0;
        }
        
        require_once $apiPath;
        
        $api = new CloudPeAPI([
            'serverhostname' => $server->hostname,
            'serverusername' => $server->username,
            'serverpassword' => decrypt($server->password),
            'serveraccesshash' => $server->accesshash,
            'serversecure' => $server->secure,
        ]);
        
        // Get volumes
        $volumesResult = $api->getServerVolumes($serverId);
        
        if ($volumesResult['success'] && !empty($volumesResult['volumes'])) {
            $volumeId = $volumesResult['volumes'][0]['volumeId'] ?? '';
            
            if (!empty($volumeId)) {
                $volumeResult = $api->getVolume($volumeId);
                
                if ($volumeResult['success']) {
                    return (int)($volumeResult['volume']['size'] ?? 0);
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
    
    return 0;
}
