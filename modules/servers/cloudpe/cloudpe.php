<?php
/**
 * CloudPe WHMCS Provisioning Module
 * 
 * Provisions virtual machines on CloudPe/OpenStack infrastructure
 * using Application Credentials authentication.
 * 
 * @version 3.41
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/CloudPeAPI.php';
require_once __DIR__ . '/lib/CloudPeHelper.php';

use WHMCS\Database\Capsule;

function cloudpe_MetaData(): array
{
    return [
        'DisplayName' => 'CloudPe',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    ];
}

function cloudpe_ConfigOptions(): array
{
    return [
        'flavor' => [
            'FriendlyName' => 'Flavor',
            'Type' => 'text',
            'Size' => 64,
            'Loader' => 'cloudpe_FlavorLoader',
            'SimpleMode' => true,
            'Description' => 'VM Size/Flavor',
        ],
        'image' => [
            'FriendlyName' => 'Default Image',
            'Type' => 'text',
            'Size' => 64,
            'Loader' => 'cloudpe_ImageLoader',
            'SimpleMode' => true,
            'Description' => 'Default OS Image (can be overridden by Configurable Options)',
        ],
        'network' => [
            'FriendlyName' => 'Network',
            'Type' => 'text',
            'Size' => 64,
            'Loader' => 'cloudpe_NetworkLoader',
            'SimpleMode' => true,
            'Description' => 'Network for VM',
        ],
        'ip_version' => [
            'FriendlyName' => 'IP Assignment',
            'Type' => 'dropdown',
            'Options' => 'ipv4|IPv4 Only,ipv6|IPv6 Only,both|Both IPv4 and IPv6',
            'Default' => 'ipv4',
            'SimpleMode' => true,
            'Description' => 'IP version(s) to assign',
        ],
        'security_group' => [
            'FriendlyName' => 'Security Group',
            'Type' => 'text',
            'Size' => 64,
            'Loader' => 'cloudpe_SecurityGroupLoader',
            'SimpleMode' => true,
            'Description' => 'Security group for VM',
        ],
        'min_volume_size' => [
            'FriendlyName' => 'Minimum Volume Size',
            'Type' => 'text',
            'Size' => 10,
            'Default' => '30',
            'SimpleMode' => true,
            'Description' => 'Minimum disk size in GB',
        ],
        'volume_type' => [
            'FriendlyName' => 'Storage Policy',
            'Type' => 'text',
            'Size' => 64,
            'Loader' => 'cloudpe_VolumeTypeLoader',
            'SimpleMode' => true,
            'Description' => 'Volume type (e.g., General Purpose)',
        ],
    ];
}

// Loader Functions
function cloudpe_FlavorLoader(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->listFlavors();
        if ($result['success']) {
            $options = [];
            foreach ($result['flavors'] as $flavor) {
                $ram = round(($flavor['ram'] ?? 0) / 1024, 1);
                $label = $flavor['name'] . ' (' . ($flavor['vcpus'] ?? '?') . ' vCPU, ' . $ram . ' GB RAM)';
                $options[$flavor['id']] = $label;
            }
            return $options;
        }
    } catch (Exception $e) {}
    return ['error' => 'Failed to load flavors'];
}

function cloudpe_ImageLoader(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->listImages();
        if ($result['success']) {
            $options = [];
            foreach ($result['images'] as $image) {
                $options[$image['id']] = $image['name'];
            }
            return $options;
        }
    } catch (Exception $e) {}
    return ['error' => 'Failed to load images'];
}

function cloudpe_NetworkLoader(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->listNetworks();
        if ($result['success']) {
            $options = [];
            foreach ($result['networks'] as $network) {
                $label = $network['name'];
                if (!empty($network['subnets'])) {
                    $label .= ' (' . count($network['subnets']) . ' subnet(s))';
                }
                $options[$network['id']] = $label;
            }
            return $options;
        }
    } catch (Exception $e) {}
    return ['error' => 'Failed to load networks'];
}

function cloudpe_SecurityGroupLoader(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->listSecurityGroups();
        if ($result['success']) {
            $options = [];
            foreach ($result['security_groups'] as $sg) {
                $options[$sg['id']] = $sg['name'];
            }
            return $options;
        }
    } catch (Exception $e) {}
    return ['error' => 'Failed to load security groups'];
}

function cloudpe_VolumeTypeLoader(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->listVolumeTypes();
        if ($result['success']) {
            $options = [];
            foreach ($result['volume_types'] as $vt) {
                $options[$vt['name']] = $vt['name'];
            }
            return $options;
        }
    } catch (Exception $e) {}
    return ['error' => 'Failed to load volume types'];
}

function cloudpe_TestConnection(array $params): array
{
    try {
        $api = new CloudPeAPI($params);
        $result = $api->testConnection();
        
        if ($result['success']) {
            return ['success' => true, 'error' => ''];
        }
        return ['success' => false, 'error' => $result['error'] ?? 'Connection failed'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function cloudpe_CreateAccount(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        
        logModuleCall('cloudpe', 'CreateAccount', $params, '', 'Starting VM creation');
        
        // Get config options
        $defaultFlavorId = trim($params['configoption1'] ?? '');
        $defaultImageId = trim($params['configoption2'] ?? '');
        $networkId = trim($params['configoption3'] ?? '');
        $ipVersion = $params['configoption4'] ?: 'ipv4';
        $securityGroupId = trim($params['configoption5'] ?? '');
        $minVolumeSize = (int)($params['configoption6'] ?? 30);
        $volumeType = trim($params['configoption7'] ?? '');
        
        // Get flavor from Configurable Options or default
        $flavorId = trim(
            $params['configoptions']['Server Size'] ?? 
            $params['configoptions']['Flavor'] ?? 
            $params['configoptions']['Plan'] ?? 
            $defaultFlavorId
        );
        
        // Get image from Configurable Options or default
        $imageId = trim(
            $params['configoptions']['Operating System'] ?? 
            $params['configoptions']['Image'] ?? 
            $params['configoptions']['OS'] ?? 
            $defaultImageId
        );
        
        // Get volume size from Configurable Options or default
        $volumeSize = (int)($params['configoptions']['Disk Space'] ?? $params['configoptions']['Volume Size'] ?? $minVolumeSize);
        if ($volumeSize < $minVolumeSize) $volumeSize = $minVolumeSize;
        if ($volumeSize < 30) $volumeSize = 30;
        
        // Validate
        if (empty($flavorId)) {
            return 'Configuration Error: No flavor/server size specified.';
        }
        if (empty($imageId)) {
            return 'Configuration Error: No OS image specified.';
        }
        if (empty($networkId)) {
            return 'Configuration Error: No network specified.';
        }
        
        $hostname = $helper->generateHostname($params);
        $password = $helper->generatePassword();
        
        // Network config
        $networks = [['uuid' => $networkId]];
        
        // Block device mapping
        $blockDevice = [
            'boot_index' => 0,
            'uuid' => $imageId,
            'source_type' => 'image',
            'destination_type' => 'volume',
            'volume_size' => $volumeSize,
            'delete_on_termination' => true,
        ];
        
        if (!empty($volumeType)) {
            $blockDevice['volume_type'] = $volumeType;
        }
        
        $serverData = [
            'name' => $hostname,
            'flavorRef' => $flavorId,
            'networks' => $networks,
            'block_device_mapping_v2' => [$blockDevice],
            'adminPass' => $password,
        ];
        
        if (!empty($securityGroupId)) {
            $serverData['security_groups'] = [$securityGroupId];
        }
        
        logModuleCall('cloudpe', 'CreateAccount', $serverData, '', 'Sending create request');
        
        $result = $api->createServer($serverData);
        
        if (!$result['success']) {
            logModuleCall('cloudpe', 'CreateAccount', $serverData, $result, 'Create failed');
            return 'Failed to create VM: ' . ($result['error'] ?? 'Unknown error');
        }
        
        $serverId = $result['server']['id'] ?? '';
        
        if (empty($serverId)) {
            return 'Failed to create VM: No server ID returned';
        }
        
        logModuleCall('cloudpe', 'CreateAccount', $serverData, $result, 'VM created: ' . $serverId);
        
        // Wait for VM to become active
        $maxWait = 120;
        $waited = 0;
        $vmData = null;
        
        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;
            
            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'ACTIVE') {
                    $vmData = $statusResult['server'];
                    break;
                }
                if ($status === 'ERROR') {
                    return 'VM creation failed with ERROR status';
                }
            }
        }
        
        // Extract IPs
        $ips = ['ipv4' => '', 'ipv6' => ''];
        if ($vmData && !empty($vmData['addresses'])) {
            $ips = $helper->extractIPs($vmData['addresses']);
        }
        
        // Update custom fields
        updateServiceCustomField($params['serviceid'], $params['pid'], 'VM ID', $serverId);
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);
        
        // Update service
        $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'dedicatedip' => $dedicatedIp,
            'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
            'password' => encrypt($password),
        ]);
        
        logModuleCall('cloudpe', 'CreateAccount', $serverData, ['server_id' => $serverId, 'ips' => $ips], 'Complete');
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('cloudpe', 'CreateAccount', $params, $e->getMessage(), 'Exception');
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_SuspendAccount(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) {
            return 'No VM ID found';
        }
        
        logModuleCall('cloudpe', 'SuspendAccount', ['server_id' => $serverId], '', 'Suspending');
        
        $result = $api->suspendServer($serverId);
        
        logModuleCall('cloudpe', 'SuspendAccount', ['server_id' => $serverId], $result, $result['success'] ? 'Success' : 'Failed');
        
        return $result['success'] ? 'success' : ('Failed: ' . ($result['error'] ?? 'Unknown error'));
        
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_UnsuspendAccount(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) {
            return 'No VM ID found';
        }
        
        logModuleCall('cloudpe', 'UnsuspendAccount', ['server_id' => $serverId], '', 'Resuming');
        
        $result = $api->resumeServer($serverId);
        
        logModuleCall('cloudpe', 'UnsuspendAccount', ['server_id' => $serverId], $result, $result['success'] ? 'Success' : 'Failed');
        
        return $result['success'] ? 'success' : ('Failed: ' . ($result['error'] ?? 'Unknown error'));
        
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_TerminateAccount(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) {
            return 'success'; // Already deleted
        }
        
        logModuleCall('cloudpe', 'TerminateAccount', ['server_id' => $serverId], '', 'Starting termination');
        
        // Get ports before deletion
        $portsResult = $api->listServerPorts($serverId);
        $ports = $portsResult['success'] ? ($portsResult['ports'] ?? []) : [];
        
        // Delete server
        $result = $api->deleteServer($serverId);
        
        if (!$result['success'] && ($result['http_code'] ?? 0) != 404) {
            logModuleCall('cloudpe', 'TerminateAccount', ['server_id' => $serverId], $result, 'Delete failed');
            return 'Failed: ' . ($result['error'] ?? 'Unknown error');
        }
        
        // Clean up ports
        foreach ($ports as $port) {
            if (!empty($port['id'])) {
                $api->deletePort($port['id']);
            }
        }
        
        // Clear custom fields
        updateServiceCustomField($params['serviceid'], $params['pid'], 'VM ID', '');
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', '');
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', '');
        
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'dedicatedip' => '',
            'assignedips' => '',
        ]);
        
        logModuleCall('cloudpe', 'TerminateAccount', ['server_id' => $serverId], $result, 'Complete');
        
        return 'success';
        
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Upgrade/Downgrade - Called when configurable options change
 */
function cloudpe_ChangePackage(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) {
            return 'No VM ID found';
        }
        
        logModuleCall('cloudpe', 'ChangePackage', $params, '', 'Starting upgrade/downgrade');
        
        // Get current server info
        $serverResult = $api->getServer($serverId);
        if (!$serverResult['success']) {
            return 'Failed to get current VM info: ' . ($serverResult['error'] ?? 'Unknown error');
        }
        
        $currentServer = $serverResult['server'];
        $currentFlavorId = $currentServer['flavor']['id'] ?? '';
        
        // Get new values from configurable options or product settings
        $defaultFlavorId = trim($params['configoption1'] ?? '');
        $newFlavorId = trim(
            $params['configoptions']['Server Size'] ?? 
            $params['configoptions']['Flavor'] ?? 
            $params['configoptions']['Plan'] ?? 
            $defaultFlavorId
        );
        
        $minVolumeSize = (int)($params['configoption6'] ?? 30);
        $newVolumeSize = (int)($params['configoptions']['Disk Space'] ?? $params['configoptions']['Volume Size'] ?? 0);
        
        $results = [];
        $errors = [];
        
        // Handle flavor change (resize)
        if (!empty($newFlavorId) && $newFlavorId !== $currentFlavorId) {
            logModuleCall('cloudpe', 'ChangePackage', [
                'server_id' => $serverId,
                'old_flavor' => $currentFlavorId,
                'new_flavor' => $newFlavorId
            ], '', 'Resizing VM');
            
            $resizeResult = $api->resizeServer($serverId, $newFlavorId);
            
            if ($resizeResult['success']) {
                // Wait for resize to complete and confirm
                $maxWait = 120;
                $waited = 0;
                $resizeConfirmed = false;
                
                while ($waited < $maxWait) {
                    sleep(5);
                    $waited += 5;
                    
                    $statusResult = $api->getServer($serverId);
                    if ($statusResult['success']) {
                        $status = $statusResult['server']['status'] ?? '';
                        
                        if ($status === 'VERIFY_RESIZE') {
                            // Confirm the resize
                            $confirmResult = $api->confirmResize($serverId);
                            if ($confirmResult['success']) {
                                $resizeConfirmed = true;
                                $results[] = 'VM resized successfully';
                                logModuleCall('cloudpe', 'ChangePackage', ['server_id' => $serverId], $confirmResult, 'Resize confirmed');
                            } else {
                                $errors[] = 'Failed to confirm resize: ' . ($confirmResult['error'] ?? 'Unknown');
                            }
                            break;
                        } elseif ($status === 'ACTIVE') {
                            // Already done
                            $resizeConfirmed = true;
                            $results[] = 'VM resize completed';
                            break;
                        } elseif ($status === 'ERROR') {
                            $errors[] = 'VM entered ERROR state during resize';
                            break;
                        }
                    }
                }
                
                if (!$resizeConfirmed && $waited >= $maxWait) {
                    $errors[] = 'Resize timed out - check VM status manually';
                }
            } else {
                $errors[] = 'Failed to start resize: ' . ($resizeResult['error'] ?? 'Unknown error');
            }
        }
        
        // Handle volume resize (only increase supported)
        if ($newVolumeSize > 0 && $newVolumeSize >= $minVolumeSize) {
            // Get current volume
            $volumesResult = $api->getServerVolumes($serverId);
            
            if ($volumesResult['success'] && !empty($volumesResult['volumes'])) {
                $volumeId = $volumesResult['volumes'][0]['volumeId'] ?? '';
                
                if (!empty($volumeId)) {
                    $volumeResult = $api->getVolume($volumeId);
                    
                    if ($volumeResult['success']) {
                        $currentSize = $volumeResult['volume']['size'] ?? 0;
                        
                        if ($newVolumeSize > $currentSize) {
                            logModuleCall('cloudpe', 'ChangePackage', [
                                'server_id' => $serverId,
                                'volume_id' => $volumeId,
                                'old_size' => $currentSize,
                                'new_size' => $newVolumeSize
                            ], '', 'Extending volume');
                            
                            $extendResult = $api->extendVolume($volumeId, $newVolumeSize);
                            
                            if ($extendResult['success']) {
                                $results[] = "Disk extended from {$currentSize}GB to {$newVolumeSize}GB";
                                logModuleCall('cloudpe', 'ChangePackage', ['volume_id' => $volumeId], $extendResult, 'Volume extended');
                            } else {
                                $errors[] = 'Failed to extend disk: ' . ($extendResult['error'] ?? 'Unknown error');
                            }
                        } elseif ($newVolumeSize < $currentSize) {
                            // Disk shrinking not supported - log warning but don't fail
                            // The customer keeps their current larger disk
                            $results[] = "Disk size unchanged (shrinking not supported). Current: {$currentSize}GB";
                            logModuleCall('cloudpe', 'ChangePackage', [
                                'volume_id' => $volumeId,
                                'current_size' => $currentSize,
                                'requested_size' => $newVolumeSize
                            ], '', 'Disk shrink skipped - not supported');
                        }
                        // If equal, no action needed
                    }
                }
            }
        }
        
        // Return results
        if (!empty($errors)) {
            $message = implode('; ', $errors);
            if (!empty($results)) {
                $message = implode('; ', $results) . ' | Errors: ' . $message;
            }
            logModuleCall('cloudpe', 'ChangePackage', $params, $errors, 'Completed with errors');
            return $message;
        }
        
        if (!empty($results)) {
            logModuleCall('cloudpe', 'ChangePackage', $params, $results, 'Success');
            return 'success';
        }
        
        return 'success'; // No changes needed
        
    } catch (Exception $e) {
        logModuleCall('cloudpe', 'ChangePackage', $params, $e->getMessage(), 'Exception');
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Admin Services Tab Fields - Shows VM configuration in admin area
 */
function cloudpe_AdminServicesTabFields(array $params): array
{
    try {
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) {
            return [
                'VM Status' => '<span class="label label-warning">Not Provisioned</span>',
            ];
        }
        
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        
        // Get server details
        $result = $api->getServer($serverId);
        
        if (!$result['success']) {
            return [
                'VM Status' => '<span class="label label-danger">Error: ' . htmlspecialchars($result['error'] ?? 'Unknown') . '</span>',
                'VM ID' => $serverId,
            ];
        }
        
        $server = $result['server'];
        $status = $server['status'] ?? 'Unknown';
        $ips = $helper->extractIPs($server['addresses'] ?? []);
        
        // Get flavor details
        $flavorInfo = 'Unknown';
        $flavorId = $server['flavor']['id'] ?? '';
        
        if (!empty($flavorId)) {
            $flavorResult = $api->getFlavor($flavorId);
            if ($flavorResult['success'] && !empty($flavorResult['flavor'])) {
                $flavor = $flavorResult['flavor'];
                $ram = round(($flavor['ram'] ?? 0) / 1024, 1);
                $flavorInfo = ($flavor['vcpus'] ?? '?') . ' vCPU, ' . $ram . ' GB RAM';
                $flavorInfo .= ' <small class="text-muted">(' . ($flavor['name'] ?? $flavorId) . ')</small>';
            }
        }
        
        // Get image/OS details
        $imageName = 'Unknown';
        $imageId = $server['image']['id'] ?? '';
        
        if (!empty($imageId)) {
            $imageResult = $api->getImage($imageId);
            if ($imageResult['success'] && !empty($imageResult['image'])) {
                $imageName = $imageResult['image']['name'] ?? 'Unknown';
            }
        }
        
        // Get disk size
        $diskInfo = 'Unknown';
        $volumesResult = $api->getServerVolumes($serverId);
        
        if ($volumesResult['success'] && !empty($volumesResult['volumes'])) {
            $diskParts = [];
            foreach ($volumesResult['volumes'] as $vol) {
                $volId = $vol['volumeId'] ?? '';
                if (!empty($volId)) {
                    $volResult = $api->getVolume($volId);
                    if ($volResult['success']) {
                        $size = (int)($volResult['volume']['size'] ?? 0);
                        $diskParts[] = $size . ' GB';
                    }
                }
            }
            if (!empty($diskParts)) {
                $diskInfo = implode(' + ', $diskParts);
            }
        }
        
        return [
            'VM Status' => $helper->getStatusLabel($status),
            'VM ID' => '<code>' . htmlspecialchars($serverId) . '</code>',
            'Hostname' => htmlspecialchars($server['name'] ?? ''),
            'Operating System' => htmlspecialchars($imageName),
            'CPU & RAM' => $flavorInfo,
            'Disk' => $diskInfo,
            'IPv4' => $ips['ipv4'] ?: '<span class="text-muted">Not assigned</span>',
            'IPv6' => $ips['ipv6'] ?: '<span class="text-muted">Not assigned</span>',
            'Created' => $server['created'] ?? '',
        ];
        
    } catch (Exception $e) {
        return [
            'Error' => '<span class="label label-danger">' . htmlspecialchars($e->getMessage()) . '</span>',
        ];
    }
}

function cloudpe_AdminCustomButtonArray(): array
{
    return [
        'Start VM' => 'AdminStart',
        'Stop VM' => 'AdminStop',
        'Restart VM' => 'AdminRestart',
        'VNC Console' => 'AdminConsole',
        'Change Password' => 'AdminChangePassword',
        'Apply Upgrade' => 'AdminUpgrade',
        'Sync Status' => 'AdminSync',
    ];
}

function cloudpe_AdminStart(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) return 'No VM ID found';

        logModuleCall('cloudpe', 'AdminStart', ['server_id' => $serverId], '', 'Starting');
        $result = $api->startServer($serverId);

        if (!$result['success']) {
            logModuleCall('cloudpe', 'AdminStart', ['server_id' => $serverId], $result, 'Failed');
            return 'Failed: ' . ($result['error'] ?? 'Unknown error');
        }

        // Wait for VM to become ACTIVE (max 30 seconds)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'ACTIVE') {
                    // Sync IPs
                    $ips = $helper->extractIPs($statusResult['server']['addresses'] ?? []);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);

                    $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
                    Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                        'dedicatedip' => $dedicatedIp,
                        'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
                    ]);

                    logModuleCall('cloudpe', 'AdminStart', ['server_id' => $serverId], 'VM is now ACTIVE', 'Success');
                    break;
                }
            }
        }

        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_AdminStop(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) return 'No VM ID found';

        logModuleCall('cloudpe', 'AdminStop', ['server_id' => $serverId], '', 'Stopping');
        $result = $api->stopServer($serverId);

        if (!$result['success']) {
            logModuleCall('cloudpe', 'AdminStop', ['server_id' => $serverId], $result, 'Failed');
            return 'Failed: ' . ($result['error'] ?? 'Unknown error');
        }

        // Wait for VM to become SHUTOFF (max 30 seconds)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'SHUTOFF') {
                    logModuleCall('cloudpe', 'AdminStop', ['server_id' => $serverId], 'VM is now SHUTOFF', 'Success');
                    break;
                }
            }
        }

        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_AdminRestart(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) return 'No VM ID found';

        logModuleCall('cloudpe', 'AdminRestart', ['server_id' => $serverId], '', 'Restarting');
        $result = $api->rebootServer($serverId);

        if (!$result['success']) {
            logModuleCall('cloudpe', 'AdminRestart', ['server_id' => $serverId], $result, 'Failed');
            return 'Failed: ' . ($result['error'] ?? 'Unknown error');
        }

        // Wait for VM to become ACTIVE after reboot (max 30 seconds)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'ACTIVE') {
                    // Sync IPs
                    $ips = $helper->extractIPs($statusResult['server']['addresses'] ?? []);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);

                    $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
                    Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                        'dedicatedip' => $dedicatedIp,
                        'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
                    ]);

                    logModuleCall('cloudpe', 'AdminRestart', ['server_id' => $serverId], 'VM is now ACTIVE', 'Success');
                    break;
                }
            }
        }

        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_AdminConsole(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) return 'No VM ID found';
        
        logModuleCall('cloudpe', 'AdminConsole', ['server_id' => $serverId], '', 'Requesting console');
        
        $result = $api->getConsoleUrl($serverId);
        
        logModuleCall('cloudpe', 'AdminConsole', ['server_id' => $serverId], $result, $result['success'] ? 'Success' : 'Failed');
        
        if ($result['success'] && !empty($result['url'])) {
            header('Location: ' . $result['url']);
            exit;
        }
        
        return 'Console URL not received: ' . ($result['error'] ?? 'No URL in response');
    } catch (Exception $e) {
        logModuleCall('cloudpe', 'AdminConsole', $params, $e->getMessage(), 'Exception');
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_AdminChangePassword(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) return 'No VM ID found';
        
        $newPassword = $helper->generatePassword();
        
        logModuleCall('cloudpe', 'AdminChangePassword', ['server_id' => $serverId], '', 'Changing password');
        
        $result = $api->changePassword($serverId, $newPassword);
        
        if ($result['success']) {
            Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                'password' => encrypt($newPassword),
            ]);
            logModuleCall('cloudpe', 'AdminChangePassword', ['server_id' => $serverId], 'Password changed', 'Success');
            return 'success';
        }
        
        logModuleCall('cloudpe', 'AdminChangePassword', ['server_id' => $serverId], $result, 'Failed');
        return 'Failed: ' . ($result['error'] ?? 'Unknown error');
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_AdminUpgrade(array $params): string
{
    // AdminUpgrade is a manual trigger for the same logic as ChangePackage
    return cloudpe_ChangePackage($params);
}

function cloudpe_AdminSync(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');
        
        if (empty($serverId)) return 'No VM ID found';
        
        $result = $api->getServer($serverId);
        
        if (!$result['success']) {
            return 'Failed: ' . ($result['error'] ?? 'Unknown error');
        }
        
        $server = $result['server'];
        $ips = $helper->extractIPs($server['addresses'] ?? []);
        
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
        updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);
        
        $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'dedicatedip' => $dedicatedIp,
            'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
        ]);
        
        return 'success';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientAreaAllowedFunctions(): array
{
    return [
        'ClientStart' => 'Start',
        'ClientStop' => 'Stop',
        'ClientRestart' => 'Restart',
        'ClientConsole' => 'Console',
        'ClientChangePassword' => 'Reset Password',
    ];
}

function cloudpe_ClientStart(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            $_SESSION['cloudpe_message'] = 'No VM ID found';
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'No VM ID found';
        }

        logModuleCall('cloudpe', 'ClientStart', ['server_id' => $serverId, 'client_id' => $params['userid']], '', 'Starting');
        $result = $api->startServer($serverId);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $_SESSION['cloudpe_message'] = 'Failed to start VM: ' . $error;
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'Failed: ' . $error;
        }

        // Wait for VM to become ACTIVE (max 30 seconds for client actions)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'ACTIVE') {
                    // Sync IPs and status
                    $ips = $helper->extractIPs($statusResult['server']['addresses'] ?? []);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);

                    $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
                    Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                        'dedicatedip' => $dedicatedIp,
                        'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
                    ]);

                    logModuleCall('cloudpe', 'ClientStart', ['server_id' => $serverId], 'VM is now ACTIVE', 'Success');
                    break;
                }
            }
        }

        $_SESSION['cloudpe_message'] = 'VM started successfully';
        $_SESSION['cloudpe_message_type'] = 'success';
        return 'success';
    } catch (Exception $e) {
        $_SESSION['cloudpe_message'] = 'Error starting VM: ' . $e->getMessage();
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientStop(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            $_SESSION['cloudpe_message'] = 'No VM ID found';
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'No VM ID found';
        }

        logModuleCall('cloudpe', 'ClientStop', ['server_id' => $serverId, 'client_id' => $params['userid']], '', 'Stopping');
        $result = $api->stopServer($serverId);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $_SESSION['cloudpe_message'] = 'Failed to stop VM: ' . $error;
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'Failed: ' . $error;
        }

        // Wait for VM to become SHUTOFF (max 30 seconds for client actions)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'SHUTOFF') {
                    logModuleCall('cloudpe', 'ClientStop', ['server_id' => $serverId], 'VM is now SHUTOFF', 'Success');
                    break;
                }
            }
        }

        $_SESSION['cloudpe_message'] = 'VM stopped successfully';
        $_SESSION['cloudpe_message_type'] = 'success';
        return 'success';
    } catch (Exception $e) {
        $_SESSION['cloudpe_message'] = 'Error stopping VM: ' . $e->getMessage();
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientRestart(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            $_SESSION['cloudpe_message'] = 'No VM ID found';
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'No VM ID found';
        }

        logModuleCall('cloudpe', 'ClientRestart', ['server_id' => $serverId, 'client_id' => $params['userid']], '', 'Restarting');
        $result = $api->rebootServer($serverId);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            $_SESSION['cloudpe_message'] = 'Failed to restart VM: ' . $error;
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'Failed: ' . $error;
        }

        // Wait for VM to become ACTIVE after reboot (max 30 seconds for client actions)
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(3);
            $waited += 3;

            $statusResult = $api->getServer($serverId);
            if ($statusResult['success']) {
                $status = $statusResult['server']['status'] ?? '';
                if ($status === 'ACTIVE') {
                    // Sync IPs
                    $ips = $helper->extractIPs($statusResult['server']['addresses'] ?? []);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv4', $ips['ipv4']);
                    updateServiceCustomField($params['serviceid'], $params['pid'], 'Public IPv6', $ips['ipv6']);

                    $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
                    Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                        'dedicatedip' => $dedicatedIp,
                        'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
                    ]);

                    logModuleCall('cloudpe', 'ClientRestart', ['server_id' => $serverId], 'VM is now ACTIVE', 'Success');
                    break;
                }
            }
        }

        $_SESSION['cloudpe_message'] = 'VM restarted successfully';
        $_SESSION['cloudpe_message_type'] = 'success';
        return 'success';
    } catch (Exception $e) {
        $_SESSION['cloudpe_message'] = 'Error restarting VM: ' . $e->getMessage();
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientConsole(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            $_SESSION['cloudpe_message'] = 'No VM ID found';
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'No VM ID found';
        }

        logModuleCall('cloudpe', 'ClientConsole', ['server_id' => $serverId, 'client_id' => $params['userid']], '', 'Requesting');

        $result = $api->getConsoleUrl($serverId);

        logModuleCall('cloudpe', 'ClientConsole', ['server_id' => $serverId], $result, $result['success'] ? 'Success' : 'Failed');

        if ($result['success'] && !empty($result['url'])) {
            header('Location: ' . $result['url']);
            exit;
        }

        $error = $result['error'] ?? 'No URL in response';
        $_SESSION['cloudpe_message'] = 'Console URL not received: ' . $error;
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Console URL not received: ' . $error;
    } catch (Exception $e) {
        logModuleCall('cloudpe', 'ClientConsole', $params, $e->getMessage(), 'Exception');
        $_SESSION['cloudpe_message'] = 'Error opening console: ' . $e->getMessage();
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientChangePassword(array $params): string
{
    try {
        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            $_SESSION['cloudpe_message'] = 'No VM ID found';
            $_SESSION['cloudpe_message_type'] = 'danger';
            return 'No VM ID found';
        }

        $newPassword = $helper->generatePassword();

        logModuleCall('cloudpe', 'ClientChangePassword', ['server_id' => $serverId, 'client_id' => $params['userid']], '', 'Changing');

        $result = $api->changePassword($serverId, $newPassword);

        if ($result['success']) {
            Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
                'password' => encrypt($newPassword),
            ]);
            $_SESSION['cloudpe_message'] = 'Password reset successfully. View your new password in the service details.';
            $_SESSION['cloudpe_message_type'] = 'success';
            return 'success';
        }

        $error = $result['error'] ?? 'Unknown error';
        $_SESSION['cloudpe_message'] = 'Failed to reset password: ' . $error;
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Failed: ' . $error;
    } catch (Exception $e) {
        $_SESSION['cloudpe_message'] = 'Error resetting password: ' . $e->getMessage();
        $_SESSION['cloudpe_message_type'] = 'danger';
        return 'Error: ' . $e->getMessage();
    }
}

function cloudpe_ClientArea(array $params): array
{
    // Get and clear session messages
    $cloudpeMessage = $_SESSION['cloudpe_message'] ?? null;
    $cloudpeMessageType = $_SESSION['cloudpe_message_type'] ?? 'info';
    unset($_SESSION['cloudpe_message'], $_SESSION['cloudpe_message_type']);

    try {
        $serverId = getServiceCustomField($params['serviceid'], $params['pid'], 'VM ID');

        if (empty($serverId)) {
            return [
                'templatefile' => 'templates/no_vm',
                'vars' => [
                    'message' => 'VM not yet provisioned',
                    'cloudpe_message' => $cloudpeMessage,
                    'cloudpe_message_type' => $cloudpeMessageType,
                ],
            ];
        }

        $api = new CloudPeAPI($params);
        $helper = new CloudPeHelper();
        
        // Get server details
        $result = $api->getServer($serverId);
        
        if (!$result['success']) {
            return [
                'templatefile' => 'templates/error',
                'vars' => [
                    'error' => $result['error'] ?? 'Failed to get VM status',
                    'cloudpe_message' => $cloudpeMessage,
                    'cloudpe_message_type' => $cloudpeMessageType,
                ],
            ];
        }
        
        $server = $result['server'];
        $ips = $helper->extractIPs($server['addresses'] ?? []);
        
        // Get flavor details
        $flavorName = 'Unknown';
        $flavorVcpus = '-';
        $flavorRam = '-';
        $flavorId = $server['flavor']['id'] ?? '';
        
        if (!empty($flavorId)) {
            $flavorResult = $api->getFlavor($flavorId);
            if ($flavorResult['success'] && !empty($flavorResult['flavor'])) {
                $flavor = $flavorResult['flavor'];
                $flavorName = $flavor['name'] ?? 'Unknown';
                $flavorVcpus = $flavor['vcpus'] ?? '-';
                $flavorRam = round(($flavor['ram'] ?? 0) / 1024, 1);
            }
        }
        
        // Get image/OS details
        $imageName = 'Unknown';
        $imageId = $server['image']['id'] ?? '';
        
        if (!empty($imageId)) {
            $imageResult = $api->getImage($imageId);
            if ($imageResult['success'] && !empty($imageResult['image'])) {
                $imageName = $imageResult['image']['name'] ?? 'Unknown';
            }
        }
        
        // Get disk size from attached volumes
        $diskSize = '-';
        $volumesResult = $api->getServerVolumes($serverId);
        
        if ($volumesResult['success'] && !empty($volumesResult['volumes'])) {
            $totalDisk = 0;
            foreach ($volumesResult['volumes'] as $vol) {
                $volId = $vol['volumeId'] ?? '';
                if (!empty($volId)) {
                    $volResult = $api->getVolume($volId);
                    if ($volResult['success']) {
                        $totalDisk += (int)($volResult['volume']['size'] ?? 0);
                    }
                }
            }
            if ($totalDisk > 0) {
                $diskSize = $totalDisk;
            }
        }
        
        return [
            'templatefile' => 'templates/overview',
            'vars' => [
                'serviceid' => $params['serviceid'],
                'server_id' => $serverId,
                'status' => $server['status'] ?? 'Unknown',
                'status_label' => $helper->getStatusLabel($server['status'] ?? ''),
                'hostname' => $server['name'] ?? '',
                'ipv4' => $ips['ipv4'],
                'ipv6' => $ips['ipv6'],
                'created' => $server['created'] ?? '',
                // VM Configuration
                'vcpus' => $flavorVcpus,
                'ram' => $flavorRam,
                'disk' => $diskSize,
                'os' => $imageName,
                'flavor_name' => $flavorName,
                // Session messages
                'cloudpe_message' => $cloudpeMessage,
                'cloudpe_message_type' => $cloudpeMessageType,
            ],
        ];
        
    } catch (Exception $e) {
        return [
            'templatefile' => 'templates/error',
            'vars' => [
                'error' => $e->getMessage(),
                'cloudpe_message' => $cloudpeMessage,
                'cloudpe_message_type' => $cloudpeMessageType,
            ],
        ];
    }
}

// Helper functions
function getServiceCustomField(int $serviceId, int $productId, string $fieldName): string
{
    $field = Capsule::table('tblcustomfields')
        ->where('relid', $productId)
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->first();
    
    if (!$field) return '';
    
    $value = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $field->id)
        ->where('relid', $serviceId)
        ->first();
    
    return $value->value ?? '';
}

function updateServiceCustomField(int $serviceId, int $productId, string $fieldName, string $value): bool
{
    $field = Capsule::table('tblcustomfields')
        ->where('relid', $productId)
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->first();
    
    if (!$field) return false;
    
    Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
        ['fieldid' => $field->id, 'relid' => $serviceId],
        ['value' => $value]
    );
    
    return true;
}
