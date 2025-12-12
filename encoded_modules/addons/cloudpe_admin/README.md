# CloudPe Manager - WHMCS Addon Module

Admin dashboard for managing CloudPe resources and creating Configurable Options.

## Installation

1. Upload `modules/addons/cloudpe_admin/` to your WHMCS installation
2. Go to **Setup → Addon Modules**
3. Find "CloudPe Manager" and click **Activate**
4. Configure access permissions (Full Administrator recommended)

## Requirements

- CloudPe provisioning module must be installed
- At least one CloudPe server configured in WHMCS
- At least one product using the CloudPe module

## Features

### Dashboard
- Server information and status
- Configuration overview
- Quick links to manage resources
- List of existing Configurable Option groups

### Images Tab
- Pull images from CloudPe API
- Select which images to offer customers
- Customize display names
- Set monthly pricing per currency
- Set display order

### Flavors Tab
- Pull flavors (VM sizes) from CloudPe API
- Select which flavors to offer customers
- Shows vCPU and RAM for each
- Customize display names
- Set monthly pricing per currency
- Set display order

### Disk Sizes Tab
- Configure disk size options
- Set display names
- Set monthly pricing per currency
- Add/remove sizes as needed

### Create Config Group
- Create named Configurable Options groups
- Link specific products to each group
- Select which options to include (OS, Size, Disk)
- Customize billing cycle multipliers
- Auto-calculates pricing for all cycles

## Multi-Currency Support

- Shows all WHMCS currencies as columns
- Enter monthly price for each currency
- Billing cycles auto-calculated:
  - Quarterly = Monthly × 3
  - Semi-Annual = Monthly × 6
  - Annual = Monthly × 12
  - Biennial = Monthly × 24
  - Triennial = Monthly × 36
- Customize multipliers for volume discounts

## Usage

1. Go to **Addons → CloudPe Manager**
2. Select your server from the dropdown
3. Configure Images, Flavors, and Disk Sizes
4. Go to "Create Config Group" tab
5. Name your group and select products
6. Click "Create Configurable Options Group"

Configurable Options will be created with proper pricing for all currencies - dropdowns will work immediately without manual saving.

## Database

The module creates a `mod_cloudpe_settings` table to store:
- Selected images per server
- Selected flavors per server
- Disk size configurations
- Display names and pricing

## Support

See CHANGELOG.md for version history.
