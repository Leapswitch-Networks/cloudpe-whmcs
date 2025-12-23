# CloudPe WHMCS Module - Comprehensive Documentation & Review

## Executive Summary

The CloudPe WHMCS Module is a comprehensive solution for WHMCS resellers to provision and manage virtual machines through CloudPe's OpenStack-based infrastructure. The codebase consists of:

1. **Admin Addon Module** (`cloudpe_admin.php`) - 1,648 lines
2. **Server Provisioning Module** (`cloudpe.php`) - 1,289 lines
3. **OpenStack API Client** (`CloudPeAPI.php`) - 1,127 lines
4. **Helper Utilities** (`CloudPeHelper.php`) - 89 lines
5. **Hooks** (`hooks.php`) - 205 lines
6. **Templates** - 3 Smarty template files

---

## Module Architecture

### 1. CloudPe Admin Module (`cloudpe_admin.php`)

**Purpose**: Admin-facing module for managing CloudPe resources, creating configurable options, and handling module updates.

**Key Components**:

| Function | Description |
|----------|-------------|
| `cloudpe_admin_config()` | Module metadata (version 3.30) |
| `cloudpe_admin_activate()` | Creates `mod_cloudpe_settings` database table |
| `cloudpe_admin_output()` | Main UI renderer with tabbed interface |
| `cloudpe_admin_check_update()` | Checks GitHub for updates |
| `cloudpe_admin_install_update()` | Downloads and installs updates |
| `cloudpe_admin_get_all_releases()` | Fetches all GitHub releases |
| `cloudpe_admin_create_config_group()` | Creates WHMCS configurable options |

**AJAX Endpoints**:
- `check_update`, `install_update`, `get_releases`
- `load_images`, `load_flavors`
- `save_images`, `save_flavors`, `save_disks`
- `save_image_names`, `save_flavor_names`
- `save_image_prices`, `save_flavor_prices`
- `create_config_group`

**Database Schema**:
```sql
mod_cloudpe_settings (
  id INT AUTO_INCREMENT,
  server_id INT,
  setting_key VARCHAR(100),
  setting_value TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(server_id, setting_key)
)
```

---

### 2. Server Provisioning Module (`cloudpe.php`)

**Purpose**: Core WHMCS provisioning module handling VM lifecycle.

**WHMCS Integration Functions**:

| Function | Type | Description |
|----------|------|-------------|
| `cloudpe_MetaData()` | Config | Module metadata |
| `cloudpe_ConfigOptions()` | Config | 7 product settings (flavor, image, network, IP version, security group, min volume, volume type) |
| `cloudpe_TestConnection()` | Utility | Server connection test |
| `cloudpe_CreateAccount()` | Lifecycle | VM provisioning |
| `cloudpe_SuspendAccount()` | Lifecycle | VM suspension |
| `cloudpe_UnsuspendAccount()` | Lifecycle | VM resumption |
| `cloudpe_TerminateAccount()` | Lifecycle | VM deletion |
| `cloudpe_ChangePackage()` | Lifecycle | Upgrade/downgrade handling |

**Admin Actions**:
- `AdminStart`, `AdminStop`, `AdminRestart`
- `AdminConsole`, `AdminChangePassword`
- `AdminUpgrade`, `AdminSync`

**Client Actions**:
- `ClientStart`, `ClientStop`, `ClientRestart`
- `ClientConsole`, `ClientChangePassword`

**Config Options Processing**:
- Server Size/Flavor from: `configoptions['Server Size']`, `['Flavor']`, `['Plan']`, or `configoption1`
- OS Image from: `configoptions['Operating System']`, `['Image']`, `['OS']`, or `configoption2`
- Disk Space from: `configoptions['Disk Space']`, `['Volume Size']`, or `configoption6`

---

### 3. CloudPeAPI Library (`CloudPeAPI.php`)

**Purpose**: OpenStack API client using Application Credentials authentication.

**Authentication Flow**:
```
1. Build server URL: protocol + hostname + accessHash + /v3
2. POST to /v3/auth/tokens with application_credential auth
3. Extract X-Subject-Token from response headers
4. Parse service catalog for endpoints
5. Cache token until expiration
```

**Service Endpoints Handled**:
- `compute` (Nova) - Server management
- `image` (Glance) - Image management
- `network` (Neutron) - Network/security groups
- `volumev3`/`volume` (Cinder) - Volume management

**Key Methods** (50+ methods total):

| Category | Methods |
|----------|---------|
| Auth | `authenticate()`, `testConnection()`, `getProjectId()` |
| Compute | `createServer()`, `getServer()`, `deleteServer()`, `startServer()`, `stopServer()`, `rebootServer()`, `suspendServer()`, `resumeServer()`, `resizeServer()`, `confirmResize()`, `revertResize()`, `changePassword()`, `shelveServer()`, `unshelveServer()`, `rebuildServer()` |
| Images | `listImages()`, `getImage()` |
| Flavors | `listFlavors()`, `getFlavor()` |
| Networks | `listNetworks()`, `listSecurityGroups()`, `listExternalNetworks()`, `listServerPorts()`, `deletePort()`, `assignFloatingIP()`, `releaseFloatingIP()` |
| Volumes | `listVolumeTypes()`, `getVolume()`, `createBootVolume()`, `extendVolume()`, `deleteVolume()`, `getServerVolumes()` |
| Console | `getConsoleUrl()`, `getVncConsole()` |

---

### 4. CloudPeHelper Library (`CloudPeHelper.php`)

**Purpose**: Utility functions for hostname generation, password generation, IP extraction, and status formatting.

| Method | Purpose |
|--------|---------|
| `generateHostname()` | Creates `vm-{clientId}-{serviceId}` pattern |
| `generatePassword()` | 16-char password with uppercase, lowercase, numbers, special chars |
| `extractIPs()` | Parses OpenStack addresses to get IPv4/IPv6 |
| `getStatusLabel()` | Converts status to Bootstrap label HTML |

---

### 5. Hooks (`hooks.php`)

**Hooks Implemented**:

1. **ShoppingCartValidateCheckout** - Prevents disk downgrades during upgrades
2. **ClientAreaPageUpgrade** - Adds disk size warning to upgrade page

---

## Data Flow Diagrams

### VM Creation Flow
```
User Order -> WHMCS -> cloudpe_CreateAccount()
  |
  v
CloudPeAPI::authenticate() -> Get token
  |
  v
Parse configoptions for flavor/image/disk
  |
  v
CloudPeAPI::createServer() -> Nova API
  |
  v
Poll for ACTIVE status (max 120s)
  |
  v
Extract IPs from addresses
  |
  v
Update custom fields (VM ID, IPv4, IPv6)
  |
  v
Update tblhosting (dedicatedip, password)
```

### Upgrade/Resize Flow
```
Upgrade Order -> cloudpe_ChangePackage()
  |
  v
Get current server from API
  |
  v
Compare flavors -> If different -> resizeServer()
  |
  v
Wait for VERIFY_RESIZE -> confirmResize()
  |
  v
Compare disk -> If larger -> extendVolume()
  |
  v
Return success/errors
```

---

## Bug Analysis & Issues Found

### Critical Issues

#### 1. Missing `getVncConsole()` Method Called But Doesn't Exist
- **Location**: `cloudpe.php:862`, `cloudpe.php:1106`
- **Issue**: Code calls `$api->getVncConsole($serverId)` but the API class has `getConsoleUrl()` instead
- **Impact**: VNC console functionality will fail with method not found error
- **Severity**: HIGH

```php
// cloudpe.php calls:
$result = $api->getVncConsole($serverId);

// But CloudPeAPI.php has:
public function getConsoleUrl(string $serverId, string $type = 'novnc'): array
```

#### 2. Version Mismatch Across Files
- **Files**: Different version numbers in different files
  - `cloudpe_admin.php`: v3.30
  - `cloudpe.php`: v3.23 (in header comment)
  - `CloudPeAPI.php`: v3.30
  - `CloudPeHelper.php`: v3.16
  - `hooks.php`: v3.17
- **Impact**: Confusion about actual version, potential update issues
- **Severity**: LOW

#### 3. Security Group Name vs ID Inconsistency
- **Location**: `cloudpe.php:270-271`, `CloudPeAPI.php:353-355`
- **Issue**: Product config stores security group ID, but createServer expects name
- **Code Path**:
```php
// cloudpe.php stores ID:
$securityGroupId = trim($params['configoption5'] ?? '');

// Then passes it as is:
if (!empty($securityGroupId)) {
    $serverData['security_groups'] = [$securityGroupId];
}

// But CloudPeAPI wraps in name format:
if (!empty($params['security_groups'])) {
    $serverData['server']['security_groups'] = array_map(function($sg) {
        return ['name' => trim($sg)];  // Expects NAME not ID
    }, (array)$params['security_groups']);
}
```
- **Impact**: Security group assignment may fail with UUID instead of name
- **Severity**: MEDIUM

### Logic Issues

#### 4. Redundant `cloudpeSelectAll`/`cloudpeSelectNone` Function Definitions
- **Location**: `cloudpe_admin.php:1101-1107` and `cloudpe_admin.php:1230-1236`
- **Issue**: These functions are defined twice (once in images section, once in flavors), and the flavors version ignores the `type` parameter
- **Impact**: Minor - works but inconsistent
- **Severity**: LOW

```php
// Images version (correct):
function cloudpeSelectAll(type) {
    $("." + (type === "images" ? "img" : "flv") + "-check").prop("checked", true);
}

// Flavors version (overrides, ignores type):
function cloudpeSelectAll(type) {
    $(".flv-check").prop("checked", true);  // Always flavors
}
```

#### 5. No Cleanup on Partial VM Creation Failure
- **Location**: `cloudpe.php:276-340`
- **Issue**: If VM creation succeeds but IP extraction or custom field updates fail, no rollback occurs
- **Impact**: Orphaned VMs could exist without being tracked in WHMCS
- **Severity**: MEDIUM

#### 6. Token Expiration Buffer Too Short
- **Location**: `CloudPeAPI.php:85`
- **Issue**: Token is considered valid if `expires_at > time() + 60` (1 minute buffer)
- **Impact**: Long-running operations could fail mid-request if token expires
- **Recommendation**: Increase to 5 minutes (300 seconds)
- **Severity**: LOW

### Potential Issues

#### 7. No Rate Limiting on API Requests
- **Location**: `CloudPeAPI.php:952-1020`
- **Impact**: Rapid requests could trigger OpenStack rate limits
- **Severity**: LOW

#### 8. Image List Limit Hardcoded to 100
- **Location**: `CloudPeAPI.php:252`
- **Issue**: `?status=active&limit=100` - users with 100+ images won't see all
- **Impact**: Missing images in admin module and product config
- **Severity**: LOW

#### 9. No Validation of UUID Format
- **Location**: Throughout codebase
- **Issue**: UUIDs from configurable options are not validated before API calls
- **Impact**: API errors with unhelpful messages
- **Severity**: LOW

#### 10. Potential XSS in Admin Module
- **Location**: `cloudpe_admin.php` (multiple locations)
- **Issue**: Some user inputs are output without escaping in JavaScript context
- **Example**: Line 1088 - `displayName` could contain quotes
- **Severity**: LOW (admin-only area)

### Flow Issues

#### 11. Upgrade Disk Size Not Persisted to Custom Fields
- **Location**: `cloudpe.php:441-600`
- **Issue**: `ChangePackage` extends volume but doesn't update any custom field
- **Impact**: WHMCS may show old disk size
- **Severity**: LOW

#### 12. Hooks Don't Check for Suspended Services
- **Location**: `hooks.php:20-98`
- **Issue**: Validation runs even for suspended services where API calls may fail
- **Impact**: Could block legitimate upgrades
- **Severity**: LOW

---

## Recommendations

### High Priority

1. **Fix VNC Console Method Name**
   - Rename `getConsoleUrl()` to `getVncConsole()` or add alias method

2. **Fix Security Group ID/Name Issue**
   - Either lookup name from ID, or change config to store name

### Medium Priority

3. **Synchronize Version Numbers**
   - Update all files to consistent version (3.30)

4. **Add VM Creation Rollback**
   - Delete server if post-creation steps fail

5. **Increase Token Buffer**
   - Change 60 to 300 in authentication check

### Low Priority

6. **Add Image Pagination**
   - Implement pagination or increase limit for large deployments

7. **Add UUID Validation**
   - Validate UUID format before API calls

8. **Fix JavaScript Function Duplication**
   - Consolidate `cloudpeSelectAll`/`cloudpeSelectNone` functions

---

## Security Assessment

| Area | Status | Notes |
|------|--------|-------|
| SQL Injection | SAFE | Uses Laravel Capsule with parameterized queries |
| XSS | MOSTLY SAFE | `htmlspecialchars()` used, some JS context concerns |
| CSRF | DEFERRED TO WHMCS | Uses WHMCS admin framework |
| Auth | SAFE | Application Credentials, no hardcoded secrets |
| File Operations | SAFE | Update downloads validated |
| Password Storage | SAFE | Uses WHMCS `encrypt()` function |

---

## Code Quality Overview

| Metric | Rating | Notes |
|--------|--------|-------|
| Code Organization | Good | Clear separation of concerns |
| Error Handling | Good | Try/catch throughout, logging |
| Documentation | Moderate | Headers present, inline comments sparse |
| Security | Good | No major vulnerabilities |
| Maintainability | Good | Consistent patterns |
| Test Coverage | None | No automated tests |

### Statistics

| Component | Lines | Functions |
|-----------|-------|-----------|
| Admin Module | 1,648 | 19 |
| Server Module | 1,289 | 32 |
| API Library | 1,127 | 45 |
| Helper Library | 89 | 4 |
| Hooks | 205 | 3 |
| **Total** | **4,358** | **103** |

---

## Critical Bug Fix Required

The most important bug is the **missing `getVncConsole()` method**. Here's the fix:

In `CloudPeAPI.php`, the method `getConsoleUrl()` exists but `cloudpe.php` calls `getVncConsole()`. You should either:

**Option A**: Add an alias method to `CloudPeAPI.php`:
```php
public function getVncConsole(string $serverId): array
{
    $result = $this->getConsoleUrl($serverId, 'novnc');
    if ($result['success'] && isset($result['url'])) {
        return ['success' => true, 'console' => ['url' => $result['url']]];
    }
    return $result;
}
```

**Option B**: Update `cloudpe.php` to call `getConsoleUrl()` and handle its response format.

---

## File Structure

```
modules/
├── addons/
│   └── cloudpe_admin/
│       ├── cloudpe_admin.php    # Admin management module
│       ├── README.md            # Admin module documentation
│       └── CHANGELOG.md         # Admin module changelog
└── servers/
    └── cloudpe/
        ├── cloudpe.php          # Main provisioning module
        ├── hooks.php            # WHMCS hooks
        ├── lib/
        │   ├── CloudPeAPI.php   # OpenStack API client
        │   └── CloudPeHelper.php # Helper functions
        ├── templates/
        │   ├── overview.tpl     # Client area VM overview
        │   ├── no_vm.tpl        # No VM provisioned template
        │   └── error.tpl        # Error display template
        ├── README.md            # Server module documentation
        └── CHANGELOG.md         # Server module changelog
```

---

## API Reference

### Authentication
The module uses OpenStack Application Credentials for authentication:

```php
$authData = [
    'auth' => [
        'identity' => [
            'methods' => ['application_credential'],
            'application_credential' => [
                'id' => $credentialId,
                'secret' => $credentialSecret,
            ],
        ],
    ],
];
```

### URL Construction
```php
// Server URL format:
https://{hostname}/{accessHash}/v3

// Auth endpoint:
https://{hostname}/{accessHash}/v3/auth/tokens

// Example:
https://cmp.dashboard.controlcloud.app/openstack/14/v3/auth/tokens
```

### Service Endpoints (from token catalog)
- **Compute (Nova)**: `{catalog_url}/servers`
- **Network (Neutron)**: `{catalog_url}/v2.0/networks`
- **Image (Glance)**: `{catalog_url}/v2/images`
- **Volume (Cinder)**: `{catalog_url}/volumes`

---

## Custom Fields Required

For each CloudPe product, create these custom fields:

| Field Name | Field Type | Admin Only | Description |
|------------|------------|------------|-------------|
| `VM ID` | Text Box | Yes | Stores OpenStack server UUID |
| `Public IPv4` | Text Box | Yes | Stores VM's public IPv4 address |
| `Public IPv6` | Text Box | Yes | Stores VM's public IPv6 address |

**Important**: Field names are case-sensitive and must match exactly.

---

## Configurable Options Format

The module expects configurable options with these naming conventions:

| Option Name | Format | Example |
|-------------|--------|---------|
| Operating System | `{image_id}\|{display_name}` | `abc123\|Ubuntu 22.04` |
| Server Size | `{flavor_id}\|{display_name}` | `xyz789\|2 vCPU, 4GB RAM` |
| Disk Space | `{size_gb}\|{display_name}` | `50\|50 GB SSD` |

---

## Troubleshooting Guide

### HTTP 405 Error
- Ensure Access Hash includes project path (e.g., `/openstack/14`)
- Auth URL should be: `https://hostname/openstack/14/v3/auth/tokens`

### HTTP 401 Error
- Verify Application Credential ID and Secret
- Check credentials haven't expired
- Ensure credentials have appropriate permissions

### HTTP 404 Error
- Check Server URL format
- Verify the project path exists

### Resources Not Loading
1. Verify server connection in Setup -> Servers
2. Check API endpoints are accessible
3. Review WHMCS module debug logs

### VNC Console Not Working
- This is a known bug - see Critical Bug Fix section above
- Apply the fix to add `getVncConsole()` method

### VM Creation Fails
- Check all required fields are configured (flavor, image, network)
- Verify security group exists (if specified)
- Check volume type exists (if specified)
- Review module logs for detailed error

---

*Documentation generated: December 2024*
*Module Version: 3.30*
