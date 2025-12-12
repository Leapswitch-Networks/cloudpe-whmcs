# CLAUDE.md - Project Guide for Claude Code

## Project Overview

This is the CloudPe WHMCS Module for Virtuozzo Hybrid Infrastructure (VHI). It enables WHMCS resellers to provision and manage virtual machines through CloudPe's OpenStack-based infrastructure.

## Key Architecture

### Authentication
- Uses OpenStack Application Credentials (NOT username/password)
- Credentials are scoped to specific Project + Region combinations
- Auth URL pattern: `https://{hostname}/{project_path}/v3/auth/tokens`
- **IMPORTANT**: The Access Hash field contains the project path (e.g., `/openstack/14`)

### File Structure
```
modules/
├── addons/cloudpe_admin/     # Admin management module
│   └── cloudpe_admin.php     # Config options, updates, resource management
└── servers/cloudpe/          # Provisioning module
    ├── cloudpe.php           # WHMCS hooks (create, suspend, terminate, etc.)
    ├── hooks.php             # Client area hooks
    ├── lib/
    │   ├── CloudPeAPI.php    # OpenStack API client
    │   └── CloudPeHelper.php # Utility functions
    └── templates/            # Client area templates
```

### Critical Code Patterns

#### CloudPeAPI.php - URL Construction (DO NOT CHANGE)
```php
// In constructor:
$this->serverUrl = $protocol . rtrim($hostname, '/');
if (!empty($accessHash)) {
    $this->serverUrl .= '/' . ltrim($accessHash, '/');
}
if (strpos($this->serverUrl, '/v3') === false) {
    $this->serverUrl .= '/v3';
}

// Auth URL is then:
$this->serverUrl . '/auth/tokens'
// Result: https://hostname/openstack/14/v3/auth/tokens
```

#### Service Endpoints
After authentication, service endpoints come from the token catalog and are used directly:
- Compute: `{catalog_url}/servers`
- Network: `{catalog_url}/v2.0/networks`
- Image: `{catalog_url}/v2/images`
- Volume: `{catalog_url}/volumes`

## Common Tasks

### Adding a New API Method
1. Add method to `CloudPeAPI.php`
2. Use `$this->getEndpoint('service_type')` for the base URL
3. Use `$this->apiRequest($url, 'METHOD', $data)` for requests
4. Always wrap in try/catch
5. Return `['success' => bool, 'data' => ...]`

### Updating Version
1. Update `CLOUDPE_MODULE_VERSION` in `cloudpe_admin.php`
2. Update `@version` in `CloudPeAPI.php` header
3. Update `version.json` in repository root
4. Update `CHANGELOG.md`

### Creating a Release
1. Update all version numbers
2. Create release ZIP: `zip -r cloudpe-whmcs-module-vX.XX.zip modules/`
3. Create GitHub release with the ZIP
4. Update `version.json` download_url

## Testing

### Test Connection
1. WHMCS Admin → Setup → Servers → Test Connection
2. Should return "Connected successfully. Project ID: ..."

### Test Resource Loading
1. Addons → CloudPe Manager → Flavors/Images/Networks tabs
2. Click "Load from API" buttons
3. Resources should populate

### Test VM Creation
1. Create a test product with CloudPe module
2. Place a test order
3. Check provisioning logs

## Known Issues & Gotchas

1. **405 Error**: Usually means the auth URL is wrong. Check Access Hash includes project path.
2. **401 Error**: Invalid credentials or expired.
3. **Empty Resources**: Check server connection first, then API permissions.
4. **VNC Console**: Tries multiple methods (remote-consoles, os-getVNCConsole, etc.)

## Dependencies

- PHP 7.4+ (uses typed properties)
- WHMCS 8.0+
- cURL extension
- ZipArchive (for updates)

## API Reference

Key OpenStack APIs used:
- Identity v3: `/v3/auth/tokens`
- Nova (Compute): `/servers`, `/flavors`
- Neutron (Network): `/v2.0/networks`, `/v2.0/security-groups`
- Glance (Image): `/v2/images`
- Cinder (Volume): `/volumes`, `/types`
