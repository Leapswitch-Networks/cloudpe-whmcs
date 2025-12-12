# CloudPe WHMCS Provisioning Module

Provisions virtual machines on CloudPe/OpenStack infrastructure using Application Credentials authentication.

## Installation

1. Upload `modules/servers/cloudpe/` to your WHMCS installation
2. Configure a server in **Setup → Products/Services → Servers**
3. Create a product using the CloudPe module

## Server Configuration

| Field | Value |
|-------|-------|
| Hostname | Your CloudPe endpoint (e.g., `cmp.dashboard.controlcloud.app`) |
| Username | Application Credential ID |
| Password | Application Credential Secret |
| Access Hash | Project path (e.g., `/openstack/14`) |
| Secure | Check for HTTPS |

## Product Configuration

### Module Settings (configoption1-7)

1. **Flavor** - VM size (dropdown from API)
2. **Default Image** - Default OS image (dropdown from API)
3. **Network** - Network for VM (dropdown from API)
4. **IP Assignment** - IPv4 Only / IPv6 Only / Both
5. **Security Group** - Firewall rules (dropdown from API)
6. **Minimum Volume Size** - Minimum disk in GB (default: 30)
7. **Storage Policy** - Volume type (e.g., "General Purpose")

### Required Custom Fields

Create these fields in **Products/Services → [Product] → Custom Fields**:

| Field Name | Field Type | Admin Only |
|------------|------------|------------|
| VM ID | Text Box | ✓ Yes |
| Public IPv4 | Text Box | ✓ Yes |
| Public IPv6 | Text Box | ✓ Yes |

**Important:** Field names must match exactly (case-sensitive).

## Configurable Options (Customer Selection)

The module reads from these Configurable Option names:

| Resource | Supported Names |
|----------|----------------|
| Flavor | `Server Size`, `Flavor`, `Plan` |
| Image | `Operating System`, `Image`, `OS` |
| Disk | `Disk Space`, `Volume Size` |

Use the **CloudPe Manager** addon module to easily create Configurable Options.

## Admin Actions

- Start VM
- Stop VM
- Restart VM
- VNC Console
- Change Password
- Sync Status

## Client Actions

- Start (when stopped)
- Stop (when running)
- Restart (when running)
- VNC Console (when running)
- Reset Password (when running)

## Logging

All module actions are logged to **Utilities → Logs → Module Log**.

## Support

See CHANGELOG.md for version history.
