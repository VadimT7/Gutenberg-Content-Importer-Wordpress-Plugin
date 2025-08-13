# Development Setup Guide

This guide will help you set up the Gutenberg Content Importer plugin for local development.

## Prerequisites

Before you begin, make sure you have the following installed:

- **Node.js** (version 16 or higher)
- **npm** or **yarn**
- **PHP** (version 7.4 or higher)
- **Composer**
- **Docker** (for wp-env)

## Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd gutenberg-content-importer
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   # or
   yarn install
   ```

4. **Start the WordPress environment**
   ```bash
   npm run start
   # or
   yarn start
   ```

5. **Access your local WordPress site**
   - **Main site**: http://localhost:8888
   - **Tests site**: http://localhost:8889
   - **Admin**: http://localhost:8888/wp-admin
   - **Default credentials**: admin / password

## Available Scripts

### WordPress Environment
- `npm run start` - Start the WordPress environment
- `npm run stop` - Stop the WordPress environment
- `npm run destroy` - Destroy the WordPress environment
- `npm run clean` - Clean the WordPress environment

### Development
- `npm run dev` - Build JavaScript in development mode with watch
- `npm run build` - Build JavaScript for production

### Testing & Linting
- `npm run test` - Run PHPUnit tests
- `npm run lint` - Run PHP CodeSniffer
- `npm run lint:fix` - Fix PHP coding standards automatically

## Project Structure

```
gutenberg-content-importer/
├── assets/                 # Frontend assets
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── includes/              # PHP source code
│   ├── admin/             # Admin interface
│   ├── api/               # REST API
│   ├── blocks/            # Block handling
│   ├── core/              # Core plugin functionality
│   ├── importers/         # Content importers
│   └── utils/             # Utility functions
├── .wp-env.json           # WordPress environment config
├── composer.json          # PHP dependencies
├── package.json           # Node.js dependencies
├── webpack.config.js      # Webpack configuration
└── phpcs.xml             # PHP coding standards
```

## Development Workflow

1. **Make changes** to your code
2. **Build assets** (if modifying JavaScript/CSS):
   ```bash
   npm run dev
   ```
3. **Test your changes**:
   ```bash
   npm run test
   npm run lint
   ```
4. **Commit your changes** following the project's coding standards

## WordPress Environment Details

The project uses `@wordpress/env` for local development, which provides:

- **WordPress trunk** (latest development version)
- **MySQL database**
- **PHP 8.0+**
- **Automatic plugin activation**
- **Debug mode enabled**

### Environment Configuration

The `.wp-env.json` file configures:
- WordPress version (trunk)
- Plugin mapping
- Port configuration (8888 for main, 8889 for tests)
- Debug settings
- Theme and plugin setup

## Troubleshooting

### Common Issues

1. **Port already in use**
   ```bash
   # Check what's using the port
   lsof -i :8888
   # Kill the process or change port in .wp-env.json
   ```

2. **Docker issues**
   ```bash
   # Restart Docker
   docker system prune -a
   npm run start
   ```

3. **Permission issues**
   ```bash
   # On Linux/Mac, you might need to fix permissions
   sudo chown -R $USER:$USER .
   ```

4. **Composer issues**
   ```bash
   # Clear composer cache
   composer clear-cache
   composer install
   ```

### Getting Help

- Check the [WordPress Environment documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- Review the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Check the main [README.md](README.md) for plugin-specific information

## Contributing

1. Follow the WordPress coding standards
2. Write tests for new functionality
3. Update documentation as needed
4. Submit pull requests with clear descriptions

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

