<?php
/**
 * CloudPe Helper Functions
 * 
 * @version 3.16
 */

class CloudPeHelper
{
    public function generateHostname(array $params): string
    {
        $domain = $params['domain'] ?? '';
        if (!empty($domain) && $domain !== 'cloudpe.local') {
            return preg_replace('/[^a-zA-Z0-9\-]/', '', $domain);
        }
        
        $clientId = $params['clientsdetails']['userid'] ?? $params['userid'] ?? rand(1000, 9999);
        $serviceId = $params['serviceid'] ?? rand(1000, 9999);
        
        return 'vm-' . $clientId . '-' . $serviceId;
    }

    public function generatePassword(int $length = 16): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';
        
        $password = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        $allChars = $upper . $lower . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }

    public function extractIPs(array $addresses, string $networkName = ''): array
    {
        $ipv4 = '';
        $ipv6 = '';
        
        foreach ($addresses as $netName => $ips) {
            foreach ($ips as $ip) {
                $addr = $ip['addr'] ?? '';
                $version = $ip['version'] ?? 4;
                
                if ($version == 4 && empty($ipv4)) {
                    $ipv4 = $addr;
                } elseif ($version == 6 && empty($ipv6)) {
                    $ipv6 = $addr;
                }
            }
        }
        
        return ['ipv4' => $ipv4, 'ipv6' => $ipv6];
    }

    public function getStatusLabel(string $status): string
    {
        $labels = [
            'ACTIVE' => '<span class="label label-success">Active</span>',
            'BUILD' => '<span class="label label-info">Building</span>',
            'BUILDING' => '<span class="label label-info">Building</span>',
            'SHUTOFF' => '<span class="label label-warning">Stopped</span>',
            'STOPPED' => '<span class="label label-warning">Stopped</span>',
            'SUSPENDED' => '<span class="label label-danger">Suspended</span>',
            'PAUSED' => '<span class="label label-warning">Paused</span>',
            'ERROR' => '<span class="label label-danger">Error</span>',
            'DELETED' => '<span class="label label-danger">Deleted</span>',
            'SOFT_DELETED' => '<span class="label label-danger">Deleted</span>',
            'SHELVED' => '<span class="label label-warning">Shelved</span>',
            'SHELVED_OFFLOADED' => '<span class="label label-warning">Shelved</span>',
            'RESCUED' => '<span class="label label-info">Rescue Mode</span>',
            'RESIZED' => '<span class="label label-info">Resized</span>',
            'REBOOT' => '<span class="label label-info">Rebooting</span>',
            'HARD_REBOOT' => '<span class="label label-info">Rebooting</span>',
            'MIGRATING' => '<span class="label label-info">Migrating</span>',
        ];
        
        return $labels[strtoupper($status)] ?? '<span class="label label-default">' . htmlspecialchars($status) . '</span>';
    }
}
