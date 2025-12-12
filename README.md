# CloudPe WHMCS Module 
A comprehensive WHMCS provisioning module for CloudPe that enables partners to sell virtual machines to their customers.

## Features

- **VM Lifecycle Management**: Create, suspend, unsuspend, and terminate VMs
- **Customer Self-Service**: Start, stop, restart VMs and access VNC console
- **Dynamic Resource Loading**: Fetch flavors, images, and networks directly from CloudPe API
- **Configurable Options**: Auto-generate WHMCS configurable options from CloudPe resources
- **Auto-Updates**: Automatic update checking and one-click installation
- **Multi-Region Support**: Support for multiple regions/projects

## Requirements

- WHMCS 8.0 or higher
- PHP 7.4 or higher
- CloudPe account with API access
- Application Credentials from CloudPe CMP

## Installation

### Method 1: Download Release

1. Download the latest release from [Releases](https://github.com/Leapswitch-Networks/cloudpe-whmcs/releases)
2. Extract the ZIP file
3. Upload the `modules` folder to your WHMCS root directory
4. Go to **Setup → Addon Modules** and activate "CloudPe Manager"
5. Go to **Setup → Products/Services → Servers** and add a new server

### Method 2: Git Clone

```bash
cd /path/to/whmcs
git clone https://github.com/Leapswitch-Networks/cloudpe-whmcs.git temp_cloudpe
cp -r temp_cloudpe/modules/* modules/
rm -rf temp_cloudpe
```

## Configuration

### Server Setup

1. Go to **Setup → Products/Services → Servers**
2. Click **Add New Server**
3. Configure:
   - **Name**: CloudPe (or your preferred name)
   - **Hostname**: Your CMP hostname (e.g., `cmp.dashboard.controlcloud.app`)
   - **Username**: Application Credential ID
   - **Password**: Application Credential Secret
   - **Access Hash**: Project path (e.g., `/openstack/14`)
   - **Type**: CloudPe
   - **Secure**: ✓ (checked for HTTPS)

4. Click **Test Connection** to verify

### Creating Application Credentials

1. Log into CloudPe Cloud Management Platform
2. Navigate to your project
3. Go to **Identity → Application Credentials**
4. Create new credentials with appropriate roles
5. Copy the Credential ID and Secret

### Product Setup

1. Create a new product or edit existing
2. Go to **Module Settings** tab
3. Select **CloudPe** as the module
4. Configure:
   - **Flavor**: Select or enter flavor UUID
   - **Image**: Select or enter image UUID
   - **Network**: Select or enter network UUID
   - **Security Groups**: Comma-separated list

### CloudPe Manager (Admin Module)

Access via **Addons → CloudPe Manager**:

- **Flavors Tab**: View and load flavors from API
- **Images Tab**: View and load images from API
- **Networks Tab**: View and load networks from API
- **Config Options Tab**: Auto-generate WHMCS configurable options
- **Updates Tab**: Check for and install module updates

## Auto-Updates

The module checks for updates from this GitHub repository. To update:

1. Go to **Addons → CloudPe Manager**
2. Click **Updates** tab
3. If an update is available, click **Install Update**

Updates are downloaded directly from GitHub releases.

## File Structure

```
modules/
├── addons/
│   └── cloudpe_admin/
│       └── cloudpe_admin.php    # Admin management module
└── servers/
    └── cloudpe/
        ├── cloudpe.php          # Main provisioning module
        ├── hooks.php            # WHMCS hooks
        ├── lib/
        │   ├── CloudPeAPI.php   # API client
        │   └── CloudPeHelper.php # Helper functions
        └── templates/
            ├── overview.tpl     # Client area template
            ├── no_vm.tpl        # No VM template
            └── error.tpl        # Error template
```

## API Methods

The CloudPeAPI class provides these methods:

### Authentication
- `authenticate()` - Authenticate with Application Credentials
- `testConnection()` - Test API connectivity

### Compute
- `listFlavors()` - List available flavors
- `getFlavor($id)` - Get specific flavor
- `createServer($params)` - Create a new VM
- `getServer($id)` - Get VM details
- `deleteServer($id)` - Delete a VM
- `startServer($id)` - Start a VM
- `stopServer($id)` - Stop a VM
- `rebootServer($id)` - Reboot a VM
- `suspendServer($id)` - Suspend a VM
- `resumeServer($id)` - Resume a VM
- `resizeServer($id, $flavorId)` - Resize a VM
- `getVncConsole($id)` - Get VNC console URL

### Images
- `listImages()` - List available images
- `getImage($id)` - Get specific image

### Networks
- `listNetworks()` - List available networks
- `listSecurityGroups()` - List security groups
- `assignFloatingIP($serverId, $networkId)` - Assign floating IP

### Volumes
- `listVolumeTypes()` - List volume types
- `getVolume($id)` - Get volume details
- `createBootVolume($name, $imageId, $size)` - Create boot volume
- `extendVolume($id, $newSize)` - Extend volume size

## Troubleshooting

### HTTP 405 Error
- Ensure the Access Hash includes your project path (e.g., `/openstack/14`)
- The authentication URL should be: `https://hostname/openstack/14/v3/auth/tokens`

### Authentication Failed
- Verify Application Credential ID and Secret
- Check that credentials have appropriate permissions
- Ensure the project path is correct

### Resources Not Loading
- Verify server connection in **Setup → Servers**
- Check that the API endpoints are accessible
- Review WHMCS module debug logs

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Development with Claude Code

This project is developed using Claude Code. To contribute:

```bash
# Clone the repository
git clone https://github.com/Leapswitch-Networks/cloudpe-whmcs.git
cd cloudpe-whmcs

# Open with Claude Code for AI-assisted development
claude
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/Leapswitch-Networks/cloudpe-whmcs/issues)
- **Documentation**: [Wiki](https://github.com/Leapswitch-Networks/cloudpe-whmcs/wiki)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.
