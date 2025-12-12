# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
