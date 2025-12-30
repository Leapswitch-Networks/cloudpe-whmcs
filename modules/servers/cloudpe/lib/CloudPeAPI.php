<?php
/**
 * CloudPe API Client
 * 
 * Uses OpenStack Application Credentials authentication.
 * Partners generate Application Credentials from the Cloud Management Platform.
 * 
 * @author CloudPe
 * @version 3.40
 */

class CloudPeAPI
{
    private $serverUrl;
    private $credentialId;
    private $credentialSecret;
    private $token;
    private $tokenExpires;
    private $endpoints = [];
    private $projectId;
    private $timeout = 60;
    private $sslVerify = true;
    
    /**
     * Constructor - Initialize API client with WHMCS server params
     * 
     * Expected params from WHMCS Server configuration:
     * - serverhostname: Server hostname (e.g., cmp.dashboard.controlcloud.app)
     * - serverusername: Credential ID from CMP
     * - serverpassword: Credential Secret from CMP
     * - serversecure: SSL verification (on/off)
     * - serveraccesshash: Optional path (e.g., /openstack/14)
     * 
     * URL can be provided in two ways:
     * 1. Full URL in hostname: https://cmp.dashboard.app/openstack/14
     * 2. Split: hostname = cmp.dashboard.app, accesshash = /openstack/14
     */
    public function __construct(array $params)
    {
        $hostname = trim($params['serverhostname'] ?? '');
        $accessHash = trim($params['serveraccesshash'] ?? '');
        $secure = !isset($params['serversecure']) || $params['serversecure'] === 'on' || $params['serversecure'] === true;
        
        $this->credentialId = $params['serverusername'];
        $this->credentialSecret = $params['serverpassword'];
        $this->sslVerify = $secure;
        
        // Build the server URL
        // Check if hostname already includes protocol
        if (strpos($hostname, 'http://') === 0 || strpos($hostname, 'https://') === 0) {
            $this->serverUrl = rtrim($hostname, '/');
        } else {
            // Add protocol based on secure setting
            $protocol = $secure ? 'https://' : 'http://';
            $this->serverUrl = $protocol . rtrim($hostname, '/');
        }
        
        // Append access hash (path) if provided
        if (!empty($accessHash)) {
            $this->serverUrl .= '/' . ltrim($accessHash, '/');
        }
        
        $this->serverUrl = rtrim($this->serverUrl, '/');
        
        // Ensure we have /v3 for identity endpoint
        if (strpos($this->serverUrl, '/v3') === false) {
            $this->serverUrl .= '/v3';
        }
    }
    
    /**
     * Get the base URL (without /v3) for constructing service endpoints
     */
    private function getBaseUrl(): string
    {
        // Remove /v3 suffix to get base URL
        return preg_replace('#/v3$#', '', $this->serverUrl);
    }
    
    /**
     * Authenticate using Application Credentials
     */
    private function authenticate(): bool
    {
        if ($this->token && $this->tokenExpires && strtotime($this->tokenExpires) > time() + 60) {
            return true;
        }
        
        $authData = [
            'auth' => [
                'identity' => [
                    'methods' => ['application_credential'],
                    'application_credential' => [
                        'id' => $this->credentialId,
                        'secret' => $this->credentialSecret,
                    ],
                ],
            ],
        ];
        
        $response = $this->curlRequest(
            $this->serverUrl . '/auth/tokens',
            'POST',
            $authData,
            [],
            true
        );
        
        if (!$response['success']) {
            $error = $response['error'] ?? 'Unknown error';
            // Add more context for debugging
            if ($response['httpCode'] === 0) {
                $error .= ' (Could not connect to server)';
            } elseif ($response['httpCode'] === 401) {
                $error = 'Invalid credentials - check Credential ID and Secret';
            } elseif ($response['httpCode'] === 404) {
                $error = 'Auth endpoint not found - check Server URL format';
            }
            throw new Exception('Authentication failed: ' . $error);
        }
        
        $this->token = $response['headers']['x-subject-token'] ?? null;
        
        if (!$this->token) {
            throw new Exception('No token received from authentication');
        }
        
        $tokenData = json_decode($response['body'], true);
        
        if (!$tokenData || !isset($tokenData['token'])) {
            throw new Exception('Invalid token response from server');
        }
        
        $this->tokenExpires = $tokenData['token']['expires_at'] ?? null;
        $this->projectId = $tokenData['token']['project']['id'] ?? null;
        
        // Extract service endpoints from catalog
        // CMP proxy returns URLs like: https://cmp.dashboard.controlcloud.app/openstack/14/nova/v2.1/...
        if (isset($tokenData['token']['catalog'])) {
            foreach ($tokenData['token']['catalog'] as $service) {
                $serviceName = $service['type'];
                foreach ($service['endpoints'] as $endpoint) {
                    if ($endpoint['interface'] === 'public') {
                        $this->endpoints[$serviceName] = rtrim($endpoint['url'], '/');
                        break;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get the authenticated project ID
     */
    public function getProjectId(): ?string
    {
        $this->authenticate();
        return $this->projectId;
    }
    
    /**
     * Get endpoint URL for a service
     */
    private function getEndpoint(string $service): string
    {
        $this->authenticate();
        
        // Try exact match first
        if (isset($this->endpoints[$service])) {
            return $this->endpoints[$service];
        }
        
        // Try common aliases
        $aliases = [
            'volume' => ['volumev3', 'volumev2', 'block-storage'],
            'volumev3' => ['volume', 'block-storage'],
        ];
        
        if (isset($aliases[$service])) {
            foreach ($aliases[$service] as $alias) {
                if (isset($this->endpoints[$alias])) {
                    return $this->endpoints[$alias];
                }
            }
        }
        
        throw new Exception("Service endpoint not found: $service. Available: " . implode(', ', array_keys($this->endpoints)));
    }
    
    /**
     * Test connection to CloudPe API
     */
    public function testConnection(): array
    {
        try {
            $this->authenticate();
            
            // Try to list flavors as a connection test
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/flavors', 'GET');
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'project_id' => $this->projectId,
                    'message' => 'Connected successfully. Project ID: ' . $this->projectId,
                ];
            }
            
            return ['success' => false, 'error' => $response['error'] ?? 'Failed to list flavors'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List available flavors
     */
    public function listFlavors(): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/flavors/detail', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'flavors' => $data['flavors'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List available images
     */
    public function listImages(): array
    {
        try {
            $imageUrl = $this->getEndpoint('image');
            // Glance v2 API - endpoint may or may not include /v2
            $url = $imageUrl;
            if (strpos($imageUrl, '/v2') === false) {
                $url .= '/v2';
            }
            $response = $this->apiRequest($url . '/images?status=active&limit=100', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'images' => $data['images'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List available networks
     */
    public function listNetworks(): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            // Neutron API - endpoint may or may not include /v2.0
            $url = $networkUrl;
            if (strpos($networkUrl, '/v2.0') === false) {
                $url .= '/v2.0';
            }
            $response = $this->apiRequest($url . '/networks', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'networks' => $data['networks'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List security groups
     */
    public function listSecurityGroups(): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            $url = $networkUrl;
            if (strpos($networkUrl, '/v2.0') === false) {
                $url .= '/v2.0';
            }
            $response = $this->apiRequest($url . '/security-groups', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'security_groups' => $data['security_groups'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create a new server/VM
     */
    public function createServer(array $params): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            
            // Build networks array - accept either 'networks' array or single 'network_id'
            $networks = [];
            if (!empty($params['networks'])) {
                $networks = $params['networks'];
            } elseif (!empty($params['network_id'])) {
                $networks = [['uuid' => $params['network_id']]];
            }
            
            $serverData = [
                'server' => [
                    'name' => $params['name'],
                    'flavorRef' => $params['flavorRef'] ?? $params['flavor_id'] ?? null,
                    'networks' => $networks,
                ],
            ];

            // Only include metadata if provided (OpenStack requires object {}, not array [])
            if (!empty($params['metadata'])) {
                $serverData['server']['metadata'] = (object)$params['metadata'];
            }
            
            if (!isset($params['block_device_mapping_v2'])) {
                $serverData['server']['imageRef'] = $params['image_id'];
            } else {
                $serverData['server']['block_device_mapping_v2'] = $params['block_device_mapping_v2'];
            }
            
            if (!empty($params['security_groups'])) {
                $serverData['server']['security_groups'] = array_map(function($sg) {
                    return ['name' => trim($sg)];
                }, (array)$params['security_groups']);
            }

            if (!empty($params['adminPass'])) {
                $serverData['server']['adminPass'] = $params['adminPass'];
            }

            if (!empty($params['key_name'])) {
                $serverData['server']['key_name'] = $params['key_name'];
            }
            
            if (!empty($params['user_data'])) {
                $serverData['server']['user_data'] = base64_encode($params['user_data']);
            }
            
            if (!empty($params['availability_zone'])) {
                $serverData['server']['availability_zone'] = $params['availability_zone'];
            }
            
            // Use microversion 2.67 to support volume_type in block_device_mapping_v2
            $extraHeaders = ['X-OpenStack-Nova-API-Version: 2.67'];
            
            $response = $this->apiRequest($computeUrl . '/servers', 'POST', $serverData, $extraHeaders);
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'server' => $data['server']];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get server details
     */
    public function getServer(string $serverId): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/servers/' . $serverId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'server' => $data['server']];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete a server
     */
    public function deleteServer(string $serverId): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/servers/' . $serverId, 'DELETE');
            
            if (in_array($response['httpCode'], [204, 202, 200])) {
                return ['success' => true];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Start a server
     */
    public function startServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['os-start' => null]);
    }
    
    /**
     * Stop a server
     */
    public function stopServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['os-stop' => null]);
    }
    
    /**
     * Reboot a server
     */
    public function rebootServer(string $serverId, string $type = 'SOFT'): array
    {
        return $this->serverAction($serverId, ['reboot' => ['type' => $type]]);
    }
    
    /**
     * Change administrative password
     * Password must be at least 12 characters with uppercase, lowercase, number, and special characters
     */
    public function changePassword(string $serverId, string $newPassword): array
    {
        return $this->serverAction($serverId, ['changePassword' => ['adminPass' => $newPassword]]);
    }
    
    /**
     * Shelve a server (suspend with state preservation)
     */
    public function shelveServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['shelve' => null]);
    }
    
    /**
     * Unshelve a server
     */
    public function unshelveServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['unshelve' => null]);
    }
    
    /**
     * Rebuild a server with new image
     */
    public function rebuildServer(string $serverId, string $imageId, string $password = null): array
    {
        $rebuildData = ['rebuild' => ['imageRef' => $imageId]];
        
        if ($password) {
            $rebuildData['rebuild']['adminPass'] = $password;
        }
        
        return $this->serverAction($serverId, $rebuildData);
    }
    
    /**
     * Resize a server (change flavor)
     */
    public function resizeServer(string $serverId, string $flavorId): array
    {
        return $this->serverAction($serverId, ['resize' => ['flavorRef' => $flavorId]]);
    }
    
    /**
     * Confirm resize
     */
    public function confirmResize(string $serverId): array
    {
        return $this->serverAction($serverId, ['confirmResize' => null]);
    }
    
    /**
     * Revert resize
     */
    public function revertResize(string $serverId): array
    {
        return $this->serverAction($serverId, ['revertResize' => null]);
    }
    
    /**
     * Generic server action
     */
    private function serverAction(string $serverId, array $action): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest(
                $computeUrl . '/servers/' . $serverId . '/action',
                'POST',
                $action
            );
            
            if (in_array($response['httpCode'], [200, 202, 204])) {
                return ['success' => true];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Wait for server to reach a specific status
     */
    public function waitForServerStatus(string $serverId, string $targetStatus, int $timeout = 300): array
    {
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $result = $this->getServer($serverId);
            
            if (!$result['success']) {
                if ($targetStatus === 'DELETED' && $result['httpCode'] === 404) {
                    return ['success' => true, 'status' => 'DELETED'];
                }
                return $result;
            }
            
            $status = $result['server']['status'];
            
            if ($status === $targetStatus) {
                return ['success' => true, 'server' => $result['server']];
            }
            
            if ($status === 'ERROR') {
                $fault = $result['server']['fault']['message'] ?? 'Unknown error';
                return ['success' => false, 'error' => "Server entered ERROR state: $fault"];
            }
            
            sleep(5);
        }
        
        return ['success' => false, 'error' => "Timeout waiting for status: $targetStatus"];
    }
    
    /**
     * Get VNC console URL
     */
    public function getConsoleUrl(string $serverId, string $type = 'novnc'): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            
            // Try newer API first
            $response = $this->apiRequest(
                $computeUrl . '/servers/' . $serverId . '/remote-consoles',
                'POST',
                ['remote_console' => ['protocol' => 'vnc', 'type' => $type]]
            );
            
            if ($response['success'] || $response['httpCode'] === 200) {
                $data = json_decode($response['body'], true);
                if ($url = $data['remote_console']['url'] ?? null) {
                    return ['success' => true, 'url' => $url];
                }
            }
            
            // Fallback to legacy API
            $response = $this->apiRequest(
                $computeUrl . '/servers/' . $serverId . '/action',
                'POST',
                ['os-getVNCConsole' => ['type' => $type]]
            );
            
            $data = json_decode($response['body'], true);
            if ($url = $data['console']['url'] ?? null) {
                return ['success' => true, 'url' => $url];
            }
            
            return ['success' => false, 'error' => 'No console URL returned'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Assign floating IP to server
     */
    public function assignFloatingIP(string $serverId, string $floatingNetworkId): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            
            // Get server port
            $response = $this->apiRequest($networkUrl . '/ports?device_id=' . $serverId, 'GET');
            if (!$response['success']) {
                return ['success' => false, 'error' => 'Failed to get server ports'];
            }
            
            $data = json_decode($response['body'], true);
            if (empty($data['ports'])) {
                return ['success' => false, 'error' => 'No ports found for server'];
            }
            
            $portId = $data['ports'][0]['id'];
            
            // Create and assign floating IP
            $response = $this->apiRequest($networkUrl . '/floatingips', 'POST', [
                'floatingip' => [
                    'floating_network_id' => $floatingNetworkId,
                    'port_id' => $portId,
                ],
            ]);
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return [
                'success' => true,
                'floating_ip' => $data['floatingip']['floating_ip_address'],
                'floating_ip_id' => $data['floatingip']['id'],
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Release floating IP
     */
    public function releaseFloatingIP(string $floatingIpIdOrAddress): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            
            // If it looks like an IP address, find the ID first
            if (filter_var($floatingIpIdOrAddress, FILTER_VALIDATE_IP)) {
                $response = $this->apiRequest(
                    $networkUrl . '/floatingips?floating_ip_address=' . $floatingIpIdOrAddress,
                    'GET'
                );
                
                if ($response['success']) {
                    $data = json_decode($response['body'], true);
                    if (!empty($data['floatingips'])) {
                        $floatingIpIdOrAddress = $data['floatingips'][0]['id'];
                    } else {
                        return ['success' => true]; // Already gone
                    }
                }
            }
            
            $response = $this->apiRequest($networkUrl . '/floatingips/' . $floatingIpIdOrAddress, 'DELETE');
            
            return in_array($response['httpCode'], [204, 200, 404]) 
                ? ['success' => true] 
                : $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get external networks (for floating IP pools)
     */
    public function listExternalNetworks(): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            $response = $this->apiRequest($networkUrl . '/networks?router:external=true', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'networks' => $data['networks'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List ports for a server
     */
    public function listServerPorts(string $serverId): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            
            $response = $this->apiRequest($networkUrl . '/ports?device_id=' . $serverId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'ports' => $data['ports'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete a port
     */
    public function deletePort(string $portId): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            
            $response = $this->apiRequest($networkUrl . '/ports/' . $portId, 'DELETE');
            
            return in_array($response['httpCode'], [200, 204, 404])
                ? ['success' => true]
                : $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * List available volume types
     */
    public function listVolumeTypes(): array
    {
        try {
            $volumeUrl = $this->getEndpoint('volumev3');
            if (empty($volumeUrl)) {
                $volumeUrl = $this->getEndpoint('volume');
            }
            if (empty($volumeUrl)) {
                return ['success' => true, 'volume_types' => []];
            }
            
            $response = $this->apiRequest($volumeUrl . '/types', 'GET');
            
            if (!$response['success']) {
                // Volume service might not be available - return empty list
                if ($response['httpCode'] == 404) {
                    return ['success' => true, 'volume_types' => []];
                }
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'volume_types' => $data['volume_types'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get a specific image by ID
     */
    public function getImage(string $imageId): array
    {
        try {
            $imageUrl = $this->getEndpoint('image');
            $url = $imageUrl;
            if (strpos($imageUrl, '/v2') === false) {
                $url .= '/v2';
            }
            $response = $this->apiRequest($url . '/images/' . $imageId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'image' => $data ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get a specific flavor by ID
     */
    public function getFlavor(string $flavorId): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/flavors/' . $flavorId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'flavor' => $data['flavor'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Suspend a server
     */
    public function suspendServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['suspend' => null]);
    }
    
    /**
     * Resume a suspended server
     */
    public function resumeServer(string $serverId): array
    {
        return $this->serverAction($serverId, ['resume' => null]);
    }
    
    /**
     * Get volumes attached to a server
     */
    public function getServerVolumes(string $serverId): array
    {
        try {
            $computeUrl = $this->getEndpoint('compute');
            $response = $this->apiRequest($computeUrl . '/servers/' . $serverId . '/os-volume_attachments', 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'volumes' => $data['volumeAttachments'] ?? []];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Extend a volume size
     */
    public function extendVolume(string $volumeId, int $newSizeGB): array
    {
        try {
            $volumeUrl = $this->getEndpoint('volumev3');
            $response = $this->apiRequest(
                $volumeUrl . '/volumes/' . $volumeId . '/action',
                'POST',
                ['os-extend' => ['new_size' => $newSizeGB]]
            );
            
            if (in_array($response['httpCode'], [200, 202])) {
                return ['success' => true];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get a specific security group by ID
     */
    public function getSecurityGroup(string $securityGroupId): array
    {
        try {
            $networkUrl = $this->getEndpoint('network');
            if (strpos($networkUrl, '/v2.0') === false) {
                $networkUrl .= '/v2.0';
            }
            $response = $this->apiRequest($networkUrl . '/security-groups/' . $securityGroupId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'security_group' => $data['security_group'] ?? null];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Make an authenticated API request
     */
    private function apiRequest(string $url, string $method, array $data = null, array $extraHeaders = []): array
    {
        $this->authenticate();
        $headers = array_merge(['X-Auth-Token: ' . $this->token], $extraHeaders);
        return $this->curlRequest($url, $method, $data, $headers);
    }
    
    /**
     * Make a cURL request
     */
    private function curlRequest(string $url, string $method, array $data = null, array $headers = [], bool $returnHeaders = false): array
    {
        $ch = curl_init();
        
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $responseHeaders = [];
        if ($returnHeaders) {
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) >= 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            });
        }
        
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        
        if ($error) {
            $errorMsg = "cURL error ($errno): $error";
            if ($errno === 60 || $errno === 77) {
                $errorMsg .= ' (SSL certificate problem - try disabling Secure Connection)';
            }
            return ['success' => false, 'error' => $errorMsg, 'httpCode' => $httpCode];
        }
        
        $result = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'httpCode' => $httpCode,
            'body' => $body,
        ];
        
        if ($returnHeaders) {
            $result['headers'] = $responseHeaders;
        }
        
        if (!$result['success']) {
            $errorData = json_decode($body, true);
            $result['error'] = $errorData['error']['message'] 
                ?? $errorData['badRequest']['message']
                ?? $errorData['message']
                ?? $errorData['error']
                ?? "HTTP Error: $httpCode";
        }
        
        return $result;
    }
    
    /**
     * Create a bootable volume from an image
     */
    public function createBootVolume(string $name, string $imageId, int $size, string $volumeType = ''): array
    {
        try {
            $volumeUrl = $this->getEndpoint('volumev3');
            
            $volumeData = [
                'volume' => [
                    'name' => $name,
                    'size' => $size,
                    'imageRef' => $imageId,
                    'bootable' => true,
                ],
            ];
            
            if (!empty($volumeType)) {
                $volumeData['volume']['volume_type'] = $volumeType;
            }
            
            $response = $this->apiRequest($volumeUrl . '/volumes', 'POST', $volumeData);
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'volume' => $data['volume']];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get volume details
     */
    public function getVolume(string $volumeId): array
    {
        try {
            $volumeUrl = $this->getEndpoint('volumev3');
            $response = $this->apiRequest($volumeUrl . '/volumes/' . $volumeId, 'GET');
            
            if (!$response['success']) {
                return $response;
            }
            
            $data = json_decode($response['body'], true);
            return ['success' => true, 'volume' => $data['volume']];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete a volume
     */
    public function deleteVolume(string $volumeId): array
    {
        try {
            $volumeUrl = $this->getEndpoint('volumev3');
            $response = $this->apiRequest($volumeUrl . '/volumes/' . $volumeId, 'DELETE');
            
            if (in_array($response['httpCode'], [204, 202, 200])) {
                return ['success' => true];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Wait for volume to reach a specific status
     */
    public function waitForVolumeStatus(string $volumeId, string $targetStatus, int $timeout = 300): array
    {
        $start = time();
        
        while ((time() - $start) < $timeout) {
            $result = $this->getVolume($volumeId);
            
            if (!$result['success']) {
                return $result;
            }
            
            $status = $result['volume']['status'];
            
            if ($status === $targetStatus) {
                return ['success' => true, 'volume' => $result['volume']];
            }
            
            if ($status === 'error') {
                return ['success' => false, 'error' => 'Volume entered error state'];
            }
            
            sleep(3);
        }
        
        return ['success' => false, 'error' => "Timeout waiting for volume status: $targetStatus"];
    }
}
