# CloudPe WHMCS Module - Changelog

## Version 3.44-beta.2 (2026-01-12)
### Added
- **VM Console Access Features**
  - View Boot Log: Display VM boot logs with configurable line count
  - Share Console Access: Create time-limited shareable console links (1h to 30d)
  - Manage Shares: List, view usage statistics, and revoke share tokens
  - Standalone shared console page with dark-themed UI
  - Token security: SHA-256 hashing, constant-time comparison, rate limiting

### Fixed
- **Console URL retrieval**: Added all 4 fallback methods for OpenStack compatibility
  - Method 1: remote-consoles (Nova microversion 2.6+)
  - Method 2: os-getVNCConsole (legacy)
  - Method 3: getVNCConsole (alternative legacy)
  - Method 4: os-getSPICEConsole (SPICE fallback)
- Better error messages showing which console methods failed

### New Files
- `console_share.php` - Standalone page for shared console access
- `console_share_api.php` - Public API for token validation

---

## Version 3.20 (2025-12-12)
### Added
- **Auto-Update System** in Admin Module
  - New "Updates" tab to check for and install updates
  - Automatic version checking against remote server
  - One-click download and installation
  - Automatic backup before update
  - Progress indicators and status messages
  - Configurable update server URL in module settings

### How Updates Work
1. Go to **Addons → CloudPe Manager → Updates**
2. Module checks `version.json` on your update server
3. If new version available, shows changelog and download button
4. Click "Download & Install Update" to:
   - Download the update package
   - Backup current modules to `modules/cloudpe_backup_[timestamp]/`
   - Extract and install new files
   - Display success/error message
5. Refresh page to use new version

### Update Server Setup
The module checks for updates at:
```
https://cloudpe.com/modules/whmcs/version.json
```

Download URL:
```
https://cloudpe.com/modules/whmcs/cloudpe-whmcs-module-latest.zip
```

### Changed
- Admin module version now synced with provisioning module (3.20)
- Added module version constant `CLOUDPE_MODULE_VERSION`
- Dashboard now shows module version and update link

---

## Version 3.19 (2025-12-12)
### Added
- **VM Configuration Display in Admin Area** (`AdminServicesTabFields`)
  - Shows VM Status with color-coded label
  - Displays VM ID, Hostname
  - Shows Operating System name (fetched from API)
  - Shows CPU & RAM (e.g., "2 vCPU, 4 GB RAM")
  - Shows Disk size in GB
  - Displays IPv4 and IPv6 addresses
  - Shows creation date

- **VM Configuration Display in Client Area**
  - New two-column layout: Server Details + Configuration
  - Shows Operating System name
  - Shows vCPU count
  - Shows RAM in GB
  - Shows Disk size in GB
  - Shows Plan/Flavor name

- **New API Methods**
  - `getFlavor($flavorId)` - Get single flavor details
  - `getImage($imageId)` - Get single image details

### Changed
- Client area template redesigned with better layout
- Admin service view now shows real-time VM specs from API

---

## Version 3.18 (2025-12-12)
### Added
- **Disk Shrink Prevention Hook** (`hooks.php`)
  - Validates disk size during upgrade checkout
  - Prevents customers from selecting smaller disk than current
  - Shows clear error message: "Your current disk is XXX GB. Please select XXX GB or larger."
  - Passes current disk size to upgrade page template

### Changed
- **Graceful disk shrink handling**: If somehow a smaller disk is requested:
  - No longer returns an error
  - Keeps the current disk size unchanged
  - Logs the action
  - Other upgrades (flavor change) still proceed

### How It Works
1. Customer goes to upgrade page
2. Hook fetches current VM disk size from CloudPe API
3. On checkout, validates selected disk ≥ current disk
4. If validation fails, shows error and prevents order
5. If order somehow proceeds with smaller disk, module skips disk change

---

## Version 3.17 (2025-12-12)
### Added
- **VM Upgrade/Downgrade Support** (`ChangePackage` function)
  - Automatically triggered when Configurable Options change
  - Supports flavor/size changes (resize VM)
  - Supports disk expansion (only increase, shrink not supported)
  - Auto-confirms resize after completion
  - Full logging of upgrade process
- **Admin "Apply Upgrade" Button**: Manually trigger upgrade/downgrade
- **Enhanced VNC Console**: Added multiple fallback methods
  - remote-consoles endpoint (microversion 2.6+)
  - os-getVNCConsole (legacy)
  - getVNCConsole (platform specific)
  - get_vnc_console (underscore format)
  - os-getSPICEConsole (SPICE fallback)
  - os-getSerialConsole (Serial fallback)
  - Detailed error reporting for debugging

### Fixed
- Console functions now include proper logging
- Better error messages when console URL not received

### Notes
- Disk shrinking is not supported by OpenStack/CloudPe
- VM must be in appropriate state for resize operations
- Check Module Log for detailed upgrade/console debugging

---

## Version 3.16 (2025-12-11)
### Fixed
- Fixed corrupted admin module file from v3.15
- Resolved PHP syntax error causing raw code to display

---

## Version 3.15 (2025-12-11)
### Added - Admin Module v1.1.0
- **Multi-currency support**: Set monthly prices for each WHMCS currency
- **Billing cycle auto-calculation**: Monthly price × multiplier for all cycles
- **Customizable multipliers**: Adjust for volume discounts (e.g., 10× for annual instead of 12×)
- **Custom config group names**: Create multiple groups for different product sets
- **Select All / Select None buttons**: Bulk selection for images, flavors, and products
- **Proper pricing records**: Fixes blank dropdown issue - no manual save required

### Changed
- Configurable Options now created with pricing for all currencies automatically
- Products selection shows all CloudPe products, not just those linked to current server

---

## Version 3.14 (2025-12-11)
### Added
- **CloudPe Admin Module v1.0.0**: New addon module for managing resources
  - Dashboard showing server info and configuration status
  - Images tab: Select images from API, set display names and pricing
  - Flavors tab: Select flavors from API, set display names and pricing
  - Disk Sizes tab: Configure disk options with pricing
  - Auto-create Configurable Options groups linked to products
- Provisioning module now reads Flavor from Configurable Options
- Support for "Server Size", "Flavor", "Plan" option names

---

## Version 3.13 (2025-12-11)
### Added
- Customer image selection via Configurable Options during order
- Support for "Operating System", "Image", "OS" option names
- Fallback to product default if customer doesn't select

### Changed
- Updated README with Configurable Options setup instructions

---

## Version 3.12 (2025-12-11)
### Added
- **Change Password functionality**
  - Admin: "Change Password" button in Module Commands
  - Client: "Reset Password" button in client area (when VM is ACTIVE)
  - Generates secure 16-character passwords
  - Updates WHMCS service record with new password
  - Full module logging for password changes

---

## Version 3.11 (2025-12-11)
### Added
- **Comprehensive module logging** for all functions
  - Logs visible at Utilities → Logs → Module Log
  - CreateAccount, Suspend, Unsuspend, Terminate all logged
  - Admin and Client actions logged with request/response details
- **Port cleanup on termination**: Deletes Neutron ports when VM is terminated
- `listServerPorts()` and `deletePort()` API methods

### Fixed
- Client area UI: Removed duplicate sidebar buttons
- Actions now only appear in template panel (right side)
- Dynamic buttons based on VM status (ACTIVE, SHUTOFF, etc.)
- Console button only shows when VM is ACTIVE

### Changed
- Removed `cloudpe_ClientAreaCustomButtonArray()` function
- Added `cloudpe_ClientAreaAllowedFunctions()` for URL-based actions

---

## Version 3.10 (2025-12-11)
### Changed
- Removed auto-creation of custom fields (was unreliable)
- Custom fields must be manually created by admin
- Simplified `updateServiceCustomField()` function

### Documentation
- Added detailed custom field setup instructions to README
- Required fields: VM ID, Public IPv4, Public IPv6 (all Admin Only)

---

## Version 3.9 (2025-12-11)
### Added
- Attempted auto-creation of custom fields if missing
- Product ID lookup for field creation

### Note
- This approach was reverted in v3.10 due to reliability issues

---

## Version 3.8 (2025-12-11)
### Added
- **Microversion 2.67 support** for `volume_type` in block_device_mapping_v2
- Storage Policy dropdown restored in product configuration
- `X-OpenStack-Nova-API-Version: 2.67` header sent with server creation

### Fixed
- Volume type now properly passed to Nova API
- Resolved "Additional properties are not allowed" error

### Configuration
- Select "General Purpose" in Storage Policy dropdown

---

## Version 3.7 (2025-12-10)
### Fixed
- Final working configuration matching OpenStack audit logs
- Volume created inline during VM creation (not pre-created)
- Removed volume_type from block_device_mapping (not supported without microversion)

### Note
- Storage policy must be set as project default in OpenStack
- Or use microversion 2.67 (implemented in v3.8)

---

## Version 3.6 (2025-12-10)
### Attempted
- Cinder-first approach: Create bootable volume, then boot VM from existing volume
- More complex than needed, reverted

---

## Version 3.5 (2025-12-10)
### Attempted
- Made storage policy configurable via dropdown
- Still rejected by Nova API without microversion

---

## Version 3.4 (2025-12-10)
### Attempted
- Hardcoded 'General Purpose' volume type
- Nova API rejected: "Additional properties are not allowed"

---

## Version 3.3 (2025-12-10)
### Attempted
- Added volume_type to block_device_mapping_v2
- Nova API rejected without microversion header

---

## Version 3.2 (2025-12-10)
### Fixed
- Network UUID error: CloudPeAPI.php was looking for `$params['network_id']`
- CreateAccount now correctly passes `networks` array

---

## Version 3.1 (2025-12-10)
### Removed
- Private network field from configuration
- "No Public IP" option

---

## Version 3.0 (2025-12-10)
### Major Rewrite
- Removed all legacy branding
- Implemented WHMCS Loader Functions for dynamic dropdowns
- Changed to public network with IPv4/IPv6 selection
- Simplified to one product per region architecture
- Clean CloudPe branding throughout

### Configuration Options
1. Flavor (dropdown via API)
2. Default Image (dropdown via API)
3. Network (dropdown via API)
4. IP Assignment (IPv4 Only / IPv6 Only / Both)
5. Security Group (dropdown via API)
6. Minimum Volume Size (text, default 30GB)

---

## Version 2.6 (2025-12-10)
### Added
- Floating IP management
- Enhanced error handling

---

## Version 2.5 (2025-12-10)
### Added
- Dynamic API loading improvements
- Better connection handling

---

## Version 2.4 (2025-12-10)
### Added
- Multi-region support enhancements

---

## Version 2.3 (2025-12-10)
### Fixed
- API authentication improvements

---

## Version 2.2 (2025-12-10)
### Added
- Additional API endpoints

---

## Version 2.1 (2025-12-10)
### Fixed
- Minor bug fixes

---

## Version 2.0 (2025-12-10)
### Initial Release
- Application Credentials authentication
- Basic VM provisioning (create, suspend, unsuspend, terminate)
- Admin actions (start, stop, restart, console)
- Client area with VM management
- Boot from volume support

---

## Server Configuration

### Server Setup
- **Hostname**: Your CloudPe/OpenStack endpoint (e.g., `cmp.dashboard.controlcloud.app`)
- **Username**: Application Credential ID
- **Password**: Application Credential Secret
- **Access Hash**: Project path (e.g., `/openstack/14`)

### Product Module Settings
1. Flavor (API dropdown)
2. Default Image (API dropdown)
3. Network (API dropdown)
4. IP Assignment (IPv4 Only / IPv6 Only / Both)
5. Security Group (API dropdown)
6. Minimum Volume Size (default: 30GB)
7. Storage Policy (API dropdown) - select "General Purpose"

### Required Custom Fields (Manual Setup)
| Field Name | Field Type | Admin Only |
|------------|------------|------------|
| VM ID | Text Box | ✓ Yes |
| Public IPv4 | Text Box | ✓ Yes |
| Public IPv6 | Text Box | ✓ Yes |

---

## Admin Module (CloudPe Manager)

### Features
- Pull images/flavors from CloudPe API
- Select resources to offer customers
- Set display names and pricing per currency
- Auto-create Configurable Options groups
- Support for multiple billing cycles

### Access
Addons → CloudPe Manager

---

## Support

For issues or feature requests, contact CloudPe support.
