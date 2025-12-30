# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.39] - 2025-12-30

### Added
- **Repair Data Utility**: Added "Repair Data" button on Create Config Group page to fix corrupted database entries
- **Comprehensive Quote Sanitization**: Extended sanitization to handle 20+ Unicode quote variants including:
  - Curly/smart quotes (U+201C, U+201D, U+2018, U+2019)
  - Double low-9 and high-reversed quotes (U+201E, U+201F)
  - Prime symbols (U+2032, U+2033, U+2035, U+2036)
  - Fullwidth quotes (U+FF02, U+FF07)
  - Angle quotation marks (U+00AB, U+00BB, U+2039, U+203A)
- **Hex Dump Debug**: Debug output now includes hex dump of first 20 bytes to identify exact problematic characters

### Fixed
- **Persistent Curly Quote Issue**: Previous fix only handled 4 quote variants; now handles all common Unicode quote characters
- Database repair utility can fix existing corrupted data without manual re-entry

## [3.38] - 2025-12-23

### Fixed
- **Syntax Error**: Fixed PHP parse error in v3.37 caused by literal curly quotes in source code
- Now using hex escape sequences (`\xE2\x80\x9C` etc.) instead of literal Unicode characters

## [3.37] - 2025-12-23

### Fixed
- **Smart/Curly Quotes**: Fixed JSON parsing failure caused by curly quotes (`"` `"`) instead of straight quotes (`"`)
- Settings are now sanitized on both save and read to convert smart quotes to straight quotes
- This fixes "NO options were added" error when data was saved with curly quotes

## [3.36] - 2025-12-23

### Added
- **Debug Settings Endpoint**: Added `ajax=debug_settings&server_id=X` to inspect stored settings
- **Debug Info Display**: Create Config Group now shows debug info when no options are added
- Enhanced debug output shows all stored settings, their lengths, and JSON validity

## [3.35] - 2025-12-23

### Fixed
- **count() Error**: Fixed `count(): Argument #1 must be of type Countable|array, null given`
- `json_decode()` can return null for invalid JSON; now defaults to empty array
- Fixed for images, flavors, and disks arrays in create_config_group

## [3.34] - 2025-12-23

### Fixed
- **500 Error**: Fixed Internal Server Error when creating configurable options group
- Added proper validation for required fields (server_id, group_name, products)
- Added validation to require at least one option type (OS, Size, or Disk)
- Changed exception handling from `\Exception` to `\Throwable` to catch all PHP errors
- Improved products array handling to support various form serialization formats

## [3.33] - 2025-12-23

### Fixed
- **JavaScript Error**: Fixed `&quot;` HTML entities in saved settings breaking JavaScript
- Saved images/flavors/names/prices from database contained HTML-encoded quotes
- Added `html_entity_decode()` to all saved settings output to JavaScript

## [3.32] - 2025-12-23

### Fixed
- **JavaScript Error**: Fixed `Unexpected token '&'` and `loadImages is not defined` errors
- WHMCS module link contains HTML-encoded `&amp;` which broke AJAX calls
- All JavaScript AJAX URLs now properly decode HTML entities

## [3.31] - 2025-12-23

### Added
- **Unencoded Release**: Plain PHP source code without ionCube encoding
- No ionCube Loader required - works on any PHP 7.4+ environment
- Full source code access for customization and debugging

## [3.30] - 2025-12-12

### Fixed
- **ionCube Compatibility**: Re-encoded with ionCube Encoder 14.0 for Loader v14.x compatibility
- Servers with ionCube Loader v14.4.1 can now decode the module files
- Encoder 15.0 files required Loader v15.0.0+, now fixed

## [3.29] - 2025-12-12

### Fixed
- **CloudPe Manager**: Fixed `cloudpeSelectAll`/`cloudpeSelectNone` not defined on Flavors tab
- **CloudPe Manager**: Settings table now auto-creates if missing
- **CloudPe Manager**: Improved save feedback with proper error messages
- Config groups now populate correctly after saving images/flavors

## [3.28] - 2025-12-12

### Added
- **Release Management**: View all releases with release notes in admin panel
- **Version History**: Browse complete release history from GitHub
- **Downgrade Support**: Ability to install any previous version
- Upgrade/downgrade buttons with version comparison
- Collapsible panels with formatted release notes

## [3.27] - 2025-12-12

### Fixed
- **VM Creation**: Fixed adminPass not being passed to OpenStack API
- VM passwords are now correctly set during provisioning

## [3.26] - 2025-12-12

### Fixed
- **VM Creation**: Fixed empty metadata causing `[] is not of type 'object'` error
- OpenStack requires metadata as `{}` (object), not `[]` (array)

## [3.25] - 2025-12-12

### Fixed
- **VM Creation**: Fixed flavorRef parameter name mismatch
- CloudPeAPI now accepts both `flavorRef` and `flavor_id` parameter names

## [3.24] - 2025-12-12

### Fixed
- **VM Creation**: Fixed error when security group is configured
- Fixed security_groups parameter format causing `trim(): Argument #1 must be string, array given` error

## [3.23] - 2025-12-12

### Fixed
- **Admin Area**: Start/Stop/Restart VM actions now work correctly with status sync

### Changed
- Admin Start action now waits for VM to reach ACTIVE status and syncs IPs
- Admin Stop action now waits for VM to reach SHUTOFF status
- Admin Restart action now waits for VM to reach ACTIVE status and syncs IPs
- Consistent behavior between client and admin VM controls

## [3.22] - 2025-12-12

### Fixed
- **Client Dashboard**: Start/Stop/Restart VM actions now work correctly from client area
- Fixed `serviceid` variable not being passed to client area template (caused broken action URLs)

### Changed
- Client Start action now waits for VM to reach ACTIVE status before returning (max 30s)
- Client Stop action now waits for VM to reach SHUTOFF status before returning (max 30s)
- Client Restart action now waits for VM to reach ACTIVE status before returning (max 30s)
- Client Start and Restart actions now automatically sync IPs after VM status change

## [3.21] - 2024-12-12

### Fixed
- **Critical**: Fixed authentication URL construction that caused HTTP 405 errors
  - v3.20 incorrectly used `/identity/v3/auth/tokens` instead of `/{project_path}/v3/auth/tokens`
  - Restored proper URL building logic that includes the Access Hash (project path)

### Added
- `getImage()` method to fetch specific image details
- `getFlavor()` method to fetch specific flavor details
- `suspendServer()` method for VM suspension
- `resumeServer()` method to resume suspended VMs
- `getServerVolumes()` method to list attached volumes
- `extendVolume()` method to increase volume size
- GitHub-based auto-update support
- Improved error messages for authentication failures (405, 401, 404 specific messages)

### Changed
- Default update URL now points to GitHub raw content
- Version checking uses GitHub releases

## [3.20] - 2024-12-11

### Added
- Auto-update feature with one-click installation
- Update checking from remote server
- Backup creation before updates

### Changed
- Refactored CloudPeAPI class (introduced breaking change - fixed in 3.21)

### Broken
- Authentication URL construction was incorrect (fixed in 3.21)

## [3.15] - 2024-12-10

### Added
- Configurable Options auto-generation
- CloudPe Manager admin module
- Dynamic resource loading from API
- "Load from API" buttons in product configuration

### Fixed
- Various stability improvements

## [3.0] - 2024-12-08

### Added
- Initial release with Application Credentials authentication
- Full VM lifecycle management
- Customer self-service portal
- VNC console access
- Multi-region support
- Floating IP management
- Boot from volume support

## [2.x] - Legacy

Previous versions using username/password authentication (deprecated).
