# Diviky Secure - Laravel-Aware PHP Obfuscator

A modern, Laravel-aware PHP code obfuscator that secures your packages without breaking framework compatibility.

## Features

- **Laravel-Aware**: Intelligently preserves Laravel framework compatibility
- **Selective Obfuscation**: Target specific namespaces/packages while preserving third-party dependencies
- **Modern Architecture**: Built with PHP 8.3+, Composer, and Symfony Console
- **Smart Analysis**: Preview what will be obfuscated before making changes
- **Incremental Processing**: Only re-obfuscate changed files
- **Flexible Configuration**: YAML-based configuration with environment support

## Installation

Install via Composer:

```bash
composer require diviky/secure
```

Or download the PHAR:

```bash
wget https://github.com/diviky/secure/releases/latest/download/secure.phar
chmod +x secure.phar
sudo mv secure.phar /usr/local/bin/secure
```

## Quick Start

1. **Initialize configuration:**
   ```bash
   secure init --laravel
   ```

2. **Analyze your project:**
   ```bash
   secure analyze --detailed
   ```

3. **Obfuscate your code:**
   ```bash
   secure obfuscate
   ```

## Configuration

The tool uses YAML configuration files. Here's an example for a Laravel project:

```yaml
project:
  type: laravel
  name: my-laravel-app
  version: 1.0.0

obfuscation:
  variables: true
  functions: true
  classes: true
  methods: true
  properties: true
  constants: true
  namespaces: false      # Keep namespaces for Laravel autoloading
  strings: false         # Careful with Laravel translations
  control_structures: true
  shuffle_statements: false

scope:
  include_paths:
    - app/
    - packages/
  exclude_paths:
    - vendor/
    - tests/
    - storage/
    - bootstrap/cache/
  include_extensions:
    - php
  preserve_namespaces:
    - Illuminate\
    - Laravel\
  preserve_classes:
    - Model
    - Controller
    - Middleware
    - ServiceProvider
  preserve_methods:
    - boot
    - register
    - handle
    - render

output:
  directory: dist/
  preserve_structure: true
  add_header: true
  strip_comments: true
  strip_whitespace: true

security:
  scramble_mode: identifier
  scramble_length: 8
  add_dummy_code: false
  randomize_order: false
```

## Commands

### `secure init`
Initialize configuration for your project.

**Options:**
- `--laravel`: Optimize for Laravel projects
- `--package`: Optimize for package development
- `--force`: Overwrite existing configuration

**Examples:**
```bash
secure init --laravel
secure init --package
secure init --force
```

### `secure analyze`
Analyze your project and preview obfuscation.

**Options:**
- `--config`: Path to configuration file
- `--detailed`: Show detailed analysis
- `--export`: Export analysis (json|yaml|csv)

**Examples:**
```bash
secure analyze
secure analyze --detailed
secure analyze --export json
```

### `secure obfuscate`
Obfuscate your code.

**Options:**
- `--config`: Path to configuration file
- `--dry-run`: Preview without making changes
- `--force`: Overwrite existing output
- `--backup`: Create backup before obfuscation
- `--watch`: Watch for changes and auto-obfuscate

**Examples:**
```bash
secure obfuscate --dry-run
secure obfuscate --backup
secure obfuscate --watch
```

## Laravel Integration

### For Laravel Applications

The tool automatically detects Laravel projects and:
- Preserves framework namespaces (`Illuminate\`, `Laravel\`)
- Keeps essential class types (`Model`, `Controller`, etc.)
- Maintains service provider methods (`boot`, `register`)
- Avoids breaking autoloading

### For Laravel Packages

When developing packages:
```bash
secure init --package
```

This optimizes configuration for package development by:
- Focusing on `src/` directory
- Preserving public APIs
- Maintaining PSR-4 autoloading compatibility

## Best Practices

### What to Obfuscate
✅ **Safe to obfuscate:**
- Private/protected methods and properties
- Internal business logic
- Helper functions
- Private classes

❌ **Avoid obfuscating:**
- Public APIs used by other packages
- Laravel framework interfaces
- Database migrations
- Configuration files

### Laravel-Specific Tips

1. **Preserve Service Provider Methods:**
   ```yaml
   preserve_methods:
     - boot
     - register
     - provides
   ```

2. **Keep Model Relationships Readable:**
   ```yaml
   preserve_methods:
     - belongsTo
     - hasMany
     - hasOne
     - belongsToMany
   ```

3. **Maintain Artisan Commands:**
   ```yaml
   preserve_classes:
     - Command
   preserve_methods:
     - handle
     - configure
   ```

## Advanced Configuration

### Environment-Specific Configs

Create different configs for different environments:

```bash
secure.dev.yaml      # Development
secure.staging.yaml  # Staging  
secure.prod.yaml     # Production
```

Use with:
```bash
secure obfuscate --config secure.prod.yaml
```

### Custom Name Generation

```yaml
security:
  scramble_mode: identifier  # identifier, hexadecimal, numeric
  scramble_length: 12        # Longer names = better security
```

### Performance Tuning

For large projects:
```yaml
obfuscation:
  shuffle_statements: false  # Disable for better performance
  
scope:
  exclude_paths:
    - vendor/           # Never obfuscate dependencies
    - storage/          # Skip cache/logs
    - node_modules/     # Skip frontend assets
```

## Troubleshooting

### Common Issues

**Autoloading Problems:**
- Ensure `preserve_namespaces` includes your PSR-4 namespaces
- Don't obfuscate class names used in `composer.json` autoload

**Laravel Errors:**
- Add framework classes to `preserve_classes`
- Include essential methods in `preserve_methods`

**Performance Issues:**
- Disable `shuffle_statements` for large codebases
- Use `exclude_paths` to skip unnecessary directories

### Debugging

1. **Use analyze first:**
   ```bash
   secure analyze --detailed --export csv
   ```

2. **Test with dry-run:**
   ```bash
   secure obfuscate --dry-run
   ```

3. **Enable verbose output:**
   ```bash
   secure obfuscate -v
   ```

## Development

### Requirements
- PHP 8.3+
- Composer
- ext-mbstring

### Setup
```bash
git clone https://github.com/diviky/secure.git
cd secure
composer install
./bin/secure --version
```

### Testing
```bash
composer test
composer test-coverage
```

### Code Quality
```bash
composer psalm
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📚 [Documentation](https://github.com/diviky/secure/wiki)
- 🐛 [Issue Tracker](https://github.com/diviky/secure/issues)
- 💬 [Discussions](https://github.com/diviky/secure/discussions)

---

**⚠️ Important Security Notice:**
This tool is designed to obfuscate code for protection against casual inspection. It is not a substitute for proper security practices. Always validate your obfuscated code thoroughly before deployment.
