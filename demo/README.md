# Laravel Logging Demo

A fully configured Laravel application demonstrating the `laravel-logging` (Superlog) package.

## Setup Instructions

### 1. Install Dependencies
```bash
cd demo
composer install
```

### 2. Generate Application Key
```bash
php artisan key:generate
```

### 3. Run the Application
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

## Available Demo Endpoints

- **GET /** - Demo home page with list of examples
- **GET /demo/log** - Basic logging test
- **GET /demo/error** - Error handling test
- **GET /demo/context** - Context data logging test
- **GET /demo/multiple** - Multiple sequential logs test
- **GET /demo/json** - JSON formatted logging test

## Configuration

### Key Configuration Files

- `.env` - Environment configuration with Superlog settings
- `config/logging.php` - Laravel logging configuration
- `config/superlog.php` - Superlog package configuration
- `bootstrap/app.php` - Application bootstrap with Superlog middleware

### Superlog Configuration

The demo is pre-configured with:
- **Format**: Text (can be changed to `json`)
- **Channel**: `superlog`
- **Context**: Enabled with stack traces
- **Middleware**: Enabled for automatic request tracking

## Useful Commands

### Check Configuration
```bash
php artisan superlog:check
```

### Check with Test Entry
```bash
php artisan superlog:check --test
```

### Run Diagnostics
```bash
php artisan superlog:check --diagnostics
```

### View Logs
Logs are stored in `storage/logs/`

```bash
# View recent logs (Linux/Mac)
tail -f storage/logs/superlog-*.log

# View logs (Windows)
Get-Content storage/logs/superlog-*.log -Tail 20 -Wait
```

## Project Structure

```
demo/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Controller.php
│   │       └── DemoController.php
├── bootstrap/
│   └── app.php
├── config/
│   ├── app.php
│   ├── logging.php
│   └── superlog.php
├── resources/
│   └── views/
│       └── demo/
│           └── index.blade.php
├── routes/
│   ├── web.php
│   └── console.php
├── storage/
│   └── logs/
├── .env
├── .env.example
├── composer.json
└── README.md
```

## Features Demonstrated

### 1. Basic Logging
Different log levels: debug, info, notice, warning, error

### 2. Error Handling
Exception catching and error logging with context

### 3. Context Data
Logging with additional context information like user ID, IP address, etc.

### 4. Sequential Logs
Multiple log entries in sequence with proper tracking

### 5. JSON Format
Logging structured data as JSON

## Troubleshooting

### Logs not appearing?
1. Check that `LOG_CHANNEL=superlog` in `.env`
2. Verify `SUPERLOG_ENABLED=true` in `.env`
3. Ensure `storage/logs` directory is writable
4. Run `php artisan superlog:check` for diagnosis

### Permission denied on log directory?
```bash
# Linux/Mac
chmod 775 storage/logs

# Windows (PowerShell)
icacls "storage\logs" /grant:r "%USERNAME%:F"
```

### Not seeing trace IDs or sequence numbers?
Ensure middleware is enabled in `config/superlog.php`:
```php
'middleware' => [
    'enabled' => true,
],
```

## Next Steps

- Modify `DemoController.php` to add more logging examples
- Change logging format in `.env` (SUPERLOG_FORMAT=json)
- Explore the log output in `storage/logs/`
- Check out the main package documentation at `../README.md`

## License

This demo is part of the Laravel Logging package. See the main repository for license information.