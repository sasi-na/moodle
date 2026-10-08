# Superset Dashboard Embed Block for Moodle

This Moodle block plugin allows you to embed Apache Superset dashboards directly into your Moodle LMS using guest tokens from an API Gateway.

## Features

- Embed Superset dashboards in Moodle Dashboard, Site Home, or Course pages
- Secure token-based authentication via API Gateway
- Responsive design
- Easy configuration through Moodle admin settings

## Installation

1. **Copy the plugin files:**
   ```bash
   cd /path/to/moodle
   cp -r blocks/supersetembed /path/to/moodle/blocks/
   ```

2. **Install the plugin:**
   - Log in to Moodle as an administrator
   - Navigate to: `Site administration > Notifications`
   - Moodle will detect the new plugin and prompt you to upgrade
   - Click "Upgrade Moodle database now"

3. **Configure the plugin:**
   - Navigate to: `Site administration > Plugins > Blocks > Superset Dashboard`
   - Configure the following settings:
     - **API Gateway URL**: `https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token`
     - **Superset URL**: `https://superset.thekaios.com`
     - **Dashboard ID**: `b4d937de-338a-46d0-8df8-e37f51601290`

## Usage

### Adding the Block to Dashboard

1. Log in as a user or administrator
2. Navigate to your Dashboard (`/my/`)
3. Click "Customize this page" or turn on editing mode
4. Click "Add a block"
5. Select "Superset Dashboard" from the list
6. The dashboard will be embedded automatically

### Adding the Block to Site Home

1. Log in as an administrator
2. Navigate to Site Home
3. Turn on editing mode
4. Click "Add a block"
5. Select "Superset Dashboard"

### Adding the Block to a Course

1. Log in as a teacher or administrator
2. Navigate to the desired course
3. Turn on editing mode
4. Click "Add a block"
5. Select "Superset Dashboard"

## Technical Details

### API Integration

The block makes a POST request to the API Gateway endpoint with the following payload:

```json
{
  "resources": [
    {
      "type": "dashboard",
      "id": "b4d937de-338a-46d0-8df8-e37f51601290"
    }
  ]
}
```

The API Gateway returns a guest token that is used by the Superset Embedded SDK to authenticate and display the dashboard.

### Files Structure

```
blocks/supersetembed/
├── block_supersetembed.php     # Main block class
├── version.php                  # Plugin version information
├── settings.php                 # Admin settings
├── styles.css                   # Block styling
├── lang/
│   └── en/
│       └── block_supersetembed.php  # English language strings
├── db/
│   └── access.php              # Capability definitions
└── amd/
    └── src/
        └── embed.js            # JavaScript module for embedding
```

## Requirements

- Moodle 5.0 or higher
- Access to Superset instance with guest token support enabled
- API Gateway configured with the superset guest-token endpoint

## Configuration Details

### API Gateway Endpoint
- **URL**: `https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token`
- **Method**: POST
- **Content-Type**: application/json

### Superset Instance
- **URL**: `https://superset.thekaios.com`
- **Dashboard ID**: `b4d937de-338a-46d0-8df8-e37f51601290`
- **Credentials** (for admin access):
  - Username: `admin`
  - Password: `FGybGMmyFjqYe02I`

## Troubleshooting

### Dashboard not loading

1. Check browser console for JavaScript errors
2. Verify API Gateway URL is accessible
3. Verify Superset URL is accessible
4. Check that the dashboard ID is correct
5. Ensure CORS is properly configured on Superset

### Configuration not saving

1. Clear Moodle cache: `Site administration > Development > Purge all caches`
2. Check file permissions on the plugin directory

### Block not appearing in block list

1. Ensure plugin is installed properly
2. Run: `php admin/cli/upgrade.php` from command line
3. Clear Moodle cache

## Development

### Compiling JavaScript (AMD)

If you modify the JavaScript file, you need to compile it:

```bash
cd /path/to/moodle
grunt amd --root=blocks/supersetembed
```

Or use the Moodle development mode to auto-compile:

```bash
php admin/cli/set_config.php --name=cachejs --value=0
```

## Support

For issues or questions, please contact the development team.

## License

This plugin is licensed under the GNU GPL v3 or later.

## Credits

Developed by JO LMS Team - 2025
