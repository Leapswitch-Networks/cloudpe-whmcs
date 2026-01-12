<?php
/**
 * CloudPe AJAX Endpoint
 *
 * Standalone endpoint for client area VM actions.
 * Bypasses WHMCS modop=custom routing which interferes with JSON responses.
 *
 * @version 3.44-beta.2
 */

// Prevent direct output before headers
ob_start();

// Calculate WHMCS root path (3 levels up from modules/servers/cloudpe/)
$whmcsRoot = dirname(dirname(dirname(__DIR__)));

// Load WHMCS
require_once $whmcsRoot . '/init.php';

// Now we have access to WHMCS Database Capsule
use WHMCS\Database\Capsule;

// Load CloudPe API
require_once __DIR__ . '/lib/CloudPeAPI.php';
require_once __DIR__ . '/lib/CloudPeHelper.php';

// Clear any output buffered during init
ob_end_clean();

// Set JSON response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/**
 * Send JSON response and exit
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data));
    exit;
}

/**
 * Log to WHMCS module log
 */
function logAction(string $action, $request, $response, string $status = ''): void
{
    logModuleCall('cloudpe', 'AJAX_' . $action, $request, $response, $status);
}

// Get request parameters
$action = $_REQUEST['action'] ?? '';
$serviceId = (int)($_REQUEST['service_id'] ?? 0);

// Validate action
$validActions = ['start', 'stop', 'restart', 'console', 'password', 'console_output', 'console_share_create', 'console_share_list', 'console_share_revoke'];
if (!in_array($action, $validActions)) {
    logAction('invalid', $_REQUEST, 'Invalid action');
    jsonResponse(false, 'Invalid action');
}

// Validate service ID
if ($serviceId <= 0) {
    logAction($action, $_REQUEST, 'Invalid service ID');
    jsonResponse(false, 'Invalid service ID');
}

// Get logged-in client ID from WHMCS session
$clientId = (int)($_SESSION['uid'] ?? 0);
if ($clientId <= 0) {
    logAction($action, $_REQUEST, 'Not authenticated');
    jsonResponse(false, 'Please log in to continue');
}

// Verify service belongs to this client and get service details
$service = Capsule::table('tblhosting')
    ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
    ->where('tblhosting.id', $serviceId)
    ->where('tblhosting.userid', $clientId)
    ->where('tblproducts.servertype', 'cloudpe')
    ->select(
        'tblhosting.id',
        'tblhosting.userid',
        'tblhosting.server',
        'tblhosting.packageid',
        'tblhosting.domainstatus'
    )
    ->first();

if (!$service) {
    logAction($action, ['service_id' => $serviceId, 'client_id' => $clientId], 'Service not found or access denied');
    jsonResponse(false, 'Service not found or access denied');
}

// Check service status
if ($service->domainstatus !== 'Active') {
    logAction($action, ['service_id' => $serviceId, 'status' => $service->domainstatus], 'Service not active');
    jsonResponse(false, 'Service is not active');
}

// Get server credentials
$server = Capsule::table('tblservers')->where('id', $service->server)->first();
if (!$server) {
    logAction($action, ['service_id' => $serviceId], 'Server not found');
    jsonResponse(false, 'Server configuration not found');
}

// Get VM ID from custom field
$vmId = getCustomFieldValue($serviceId, $service->packageid, 'VM ID');
if (empty($vmId)) {
    logAction($action, ['service_id' => $serviceId], 'VM ID not found');
    jsonResponse(false, 'VM not provisioned yet');
}

// Build API params
$params = [
    'serverhostname' => $server->hostname,
    'serverusername' => $server->username,
    'serverpassword' => decrypt($server->password),
    'serveraccesshash' => $server->accesshash,
    'serversecure' => $server->secure,
];

try {
    $api = new CloudPeAPI($params);
    $helper = new CloudPeHelper();

    logAction($action, ['service_id' => $serviceId, 'vm_id' => $vmId, 'client_id' => $clientId], 'Starting action');

    switch ($action) {
        case 'start':
            $result = $api->startServer($vmId);
            if (!$result['success']) {
                jsonResponse(false, 'Failed to start VM: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Wait for VM to become ACTIVE (max 30 seconds)
            $newStatus = waitForStatus($api, $vmId, 'ACTIVE', 30);

            // Sync IPs if successful
            if ($newStatus === 'ACTIVE') {
                syncServiceIPs($api, $vmId, $serviceId, $service->packageid, $helper);
            }

            logAction($action, ['vm_id' => $vmId], ['status' => $newStatus], 'Success');
            jsonResponse(true, 'VM started successfully', ['status' => $newStatus]);
            break;

        case 'stop':
            $result = $api->stopServer($vmId);
            if (!$result['success']) {
                jsonResponse(false, 'Failed to stop VM: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Wait for VM to become SHUTOFF (max 30 seconds)
            $newStatus = waitForStatus($api, $vmId, 'SHUTOFF', 30);

            logAction($action, ['vm_id' => $vmId], ['status' => $newStatus], 'Success');
            jsonResponse(true, 'VM stopped successfully', ['status' => $newStatus]);
            break;

        case 'restart':
            $result = $api->rebootServer($vmId);
            if (!$result['success']) {
                jsonResponse(false, 'Failed to restart VM: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Wait for VM to become ACTIVE (max 30 seconds)
            $newStatus = waitForStatus($api, $vmId, 'ACTIVE', 30);

            // Sync IPs if successful
            if ($newStatus === 'ACTIVE') {
                syncServiceIPs($api, $vmId, $serviceId, $service->packageid, $helper);
            }

            logAction($action, ['vm_id' => $vmId], ['status' => $newStatus], 'Success');
            jsonResponse(true, 'VM restarted successfully', ['status' => $newStatus]);
            break;

        case 'console':
            $result = $api->getConsoleUrl($vmId);
            if (!$result['success'] || empty($result['url'])) {
                jsonResponse(false, 'Failed to get console URL: ' . ($result['error'] ?? 'No URL returned'));
            }

            logAction($action, ['vm_id' => $vmId], 'Console URL retrieved', 'Success');
            jsonResponse(true, 'Console ready', ['url' => $result['url']]);
            break;

        case 'password':
            $newPassword = $helper->generatePassword();
            $result = $api->changePassword($vmId, $newPassword);

            if (!$result['success']) {
                jsonResponse(false, 'Failed to reset password: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Update password in WHMCS
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'password' => encrypt($newPassword),
            ]);

            logAction($action, ['vm_id' => $vmId], 'Password reset', 'Success');
            jsonResponse(true, 'Password reset successfully. Reload page to view new password.');
            break;

        case 'console_output':
            $length = (int)($_REQUEST['length'] ?? 100);
            $result = $api->getConsoleOutput($vmId, $length);

            if (!$result['success']) {
                jsonResponse(false, 'Failed to get console output: ' . ($result['error'] ?? 'Unknown error'));
            }

            logAction($action, ['vm_id' => $vmId, 'length' => $length], 'Console output retrieved', 'Success');
            jsonResponse(true, 'Console output retrieved', [
                'output' => $result['output'],
                'length' => $result['length']
            ]);
            break;

        case 'console_share_create':
            // Ensure table exists
            CloudPeHelper::ensureConsoleSharesTable();

            $name = trim($_REQUEST['name'] ?? '');
            $expiry = $_REQUEST['expiry'] ?? '24h';
            $consoleType = $_REQUEST['console_type'] ?? 'novnc';

            // Validate expiry
            $expiryDurations = CloudPeHelper::getExpiryDurations();
            if (!isset($expiryDurations[$expiry])) {
                jsonResponse(false, 'Invalid expiry duration');
            }

            // Check VM is ACTIVE
            $vmResult = $api->getServer($vmId);
            if (!$vmResult['success'] || strtoupper($vmResult['server']['status'] ?? '') !== 'ACTIVE') {
                jsonResponse(false, 'VM must be running (ACTIVE) to create a console share');
            }

            // Generate token
            $tokenData = CloudPeHelper::generateShareToken();
            $expiresAt = date('Y-m-d H:i:s', time() + $expiryDurations[$expiry]);

            // Insert into database
            $shareId = Capsule::table('mod_cloudpe_console_shares')->insertGetId([
                'token_hash' => $tokenData['hash'],
                'service_id' => $serviceId,
                'vm_id' => $vmId,
                'created_by_user_id' => $clientId,
                'name' => $name ?: null,
                'expires_at' => $expiresAt,
                'console_type' => $consoleType,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Build share URL
            $systemUrl = rtrim($GLOBALS['CONFIG']['SystemURL'] ?? '', '/');
            $shareUrl = $systemUrl . '/modules/servers/cloudpe/console_share.php?token=' . $tokenData['token'];

            logAction($action, ['vm_id' => $vmId, 'share_id' => $shareId], 'Console share created', 'Success');
            jsonResponse(true, 'Console share created', [
                'id' => $shareId,
                'name' => $name,
                'token' => $tokenData['token'], // Only returned once!
                'share_url' => $shareUrl,
                'expires_at' => $expiresAt,
                'console_type' => $consoleType,
            ]);
            break;

        case 'console_share_list':
            CloudPeHelper::ensureConsoleSharesTable();

            $includeRevoked = filter_var($_REQUEST['include_revoked'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $query = Capsule::table('mod_cloudpe_console_shares')
                ->where('service_id', $serviceId)
                ->where('vm_id', $vmId);

            if (!$includeRevoked) {
                $query->where('revoked', false);
            }

            $shares = $query->orderBy('created_at', 'desc')->get();

            $shareList = [];
            $now = time();
            foreach ($shares as $share) {
                $expiresAt = strtotime($share->expires_at);
                $shareList[] = [
                    'id' => $share->id,
                    'name' => $share->name,
                    'expires_at' => $share->expires_at,
                    'console_type' => $share->console_type,
                    'use_count' => $share->use_count,
                    'last_used_at' => $share->last_used_at,
                    'created_at' => $share->created_at,
                    'revoked' => (bool)$share->revoked,
                    'is_expired' => $expiresAt < $now,
                ];
            }

            jsonResponse(true, 'Console shares retrieved', ['shares' => $shareList]);
            break;

        case 'console_share_revoke':
            CloudPeHelper::ensureConsoleSharesTable();

            $shareId = (int)($_REQUEST['share_id'] ?? 0);
            $reason = trim($_REQUEST['reason'] ?? 'Revoked by user');

            if ($shareId <= 0) {
                jsonResponse(false, 'Invalid share ID');
            }

            // Verify share belongs to this service
            $share = Capsule::table('mod_cloudpe_console_shares')
                ->where('id', $shareId)
                ->where('service_id', $serviceId)
                ->first();

            if (!$share) {
                jsonResponse(false, 'Share not found');
            }

            if ($share->revoked) {
                jsonResponse(false, 'Share already revoked');
            }

            Capsule::table('mod_cloudpe_console_shares')
                ->where('id', $shareId)
                ->update([
                    'revoked' => true,
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'revoked_reason' => substr($reason, 0, 255),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            logAction($action, ['vm_id' => $vmId, 'share_id' => $shareId], 'Console share revoked', 'Success');
            jsonResponse(true, 'Console share revoked');
            break;
    }
} catch (Exception $e) {
    logAction($action, ['vm_id' => $vmId], $e->getMessage(), 'Exception');
    jsonResponse(false, 'Error: ' . $e->getMessage());
}

/**
 * Get custom field value for a service
 */
function getCustomFieldValue(int $serviceId, int $productId, string $fieldName): string
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

/**
 * Update custom field value for a service
 */
function updateCustomFieldValue(int $serviceId, int $productId, string $fieldName, string $value): void
{
    $field = Capsule::table('tblcustomfields')
        ->where('relid', $productId)
        ->where('type', 'product')
        ->where('fieldname', $fieldName)
        ->first();

    if (!$field) return;

    Capsule::table('tblcustomfieldsvalues')
        ->updateOrInsert(
            ['fieldid' => $field->id, 'relid' => $serviceId],
            ['value' => $value]
        );
}

/**
 * Wait for VM to reach target status
 */
function waitForStatus(CloudPeAPI $api, string $vmId, string $targetStatus, int $maxWait): string
{
    $waited = 0;
    $status = 'UNKNOWN';

    while ($waited < $maxWait) {
        sleep(3);
        $waited += 3;

        $result = $api->getServer($vmId);
        if ($result['success']) {
            $status = $result['server']['status'] ?? 'UNKNOWN';
            if ($status === $targetStatus) {
                break;
            }
        }
    }

    return $status;
}

/**
 * Sync IP addresses from VM to WHMCS service
 */
function syncServiceIPs(CloudPeAPI $api, string $vmId, int $serviceId, int $productId, CloudPeHelper $helper): void
{
    $result = $api->getServer($vmId);
    if (!$result['success']) return;

    $ips = $helper->extractIPs($result['server']['addresses'] ?? []);

    updateCustomFieldValue($serviceId, $productId, 'Public IPv4', $ips['ipv4']);
    updateCustomFieldValue($serviceId, $productId, 'Public IPv6', $ips['ipv6']);

    $dedicatedIp = $ips['ipv4'] ?: $ips['ipv6'];
    Capsule::table('tblhosting')->where('id', $serviceId)->update([
        'dedicatedip' => $dedicatedIp,
        'assignedips' => trim($ips['ipv4'] . "\n" . $ips['ipv6']),
    ]);
}
