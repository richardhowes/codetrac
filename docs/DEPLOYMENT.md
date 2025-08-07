# DevTrack Deployment Guide

This guide will help you deploy DevTrack to a production environment.

## Prerequisites

- PHP 8.2 or higher
- PostgreSQL or MySQL database
- Composer
- Node.js and npm
- A web server (Nginx or Apache)
- SSL certificate (Let's Encrypt recommended)

## Step 1: Server Setup

### 1.1 Clone the Repository

```bash
git clone https://github.com/yourusername/devtrack.git /var/www/devtrack
cd /var/www/devtrack
```

### 1.2 Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 1.3 Environment Configuration

```bash
cp .env.production .env
```

Edit `.env` and update:
- `APP_KEY` - Generate with `php artisan key:generate`
- `APP_URL` - Your domain (e.g., https://devtrack.yourdomain.com)
- Database credentials
- Mail configuration (optional)

### 1.4 Database Setup

Create your database:

```sql
CREATE DATABASE devtrack;
CREATE USER devtrack_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE devtrack TO devtrack_user;
```

Run migrations:

```bash
php artisan migrate --force
```

### 1.5 Set Permissions

```bash
chown -R www-data:www-data /var/www/devtrack
chmod -R 755 /var/www/devtrack
chmod -R 775 /var/www/devtrack/storage
chmod -R 775 /var/www/devtrack/bootstrap/cache
```

## Step 2: Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/devtrack`:

```nginx
server {
    listen 80;
    server_name devtrack.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name devtrack.yourdomain.com;
    root /var/www/devtrack/public;

    ssl_certificate /etc/letsencrypt/live/devtrack.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/devtrack.yourdomain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:

```bash
ln -s /etc/nginx/sites-available/devtrack /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## Step 3: Queue Workers

Create a systemd service for queue workers:

```bash
sudo nano /etc/systemd/system/devtrack-queue.service
```

```ini
[Unit]
Description=DevTrack Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/devtrack/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
systemctl enable devtrack-queue
systemctl start devtrack-queue
```

## Step 4: API Token Setup

### Generate API Tokens for Developers

Once deployed, generate API tokens for each developer:

```bash
php artisan api:generate-token
```

The command will:
1. List available developers (if any)
2. Generate a secure token
3. Display the token (save it - it won't be shown again)

### Update Hook Scripts

Each developer should update their `devtrack-hook.sh` script:

1. Change the `API_URL` to your production URL:
   ```bash
   API_URL="https://devtrack.yourdomain.com/api/webhook/session"
   ```

2. Replace `test-token-123` with their generated API token:
   ```bash
   API_TOKEN="your-generated-token-here"
   ```

## Step 5: SSL Certificate

Install Certbot for Let's Encrypt:

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d devtrack.yourdomain.com
```

## Step 6: Optimization

### Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Enable OPcache

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

## Step 7: Monitoring

### Application Logs

Monitor Laravel logs:

```bash
tail -f /var/www/devtrack/storage/logs/laravel.log
```

### Queue Worker Logs

```bash
journalctl -u devtrack-queue -f
```

### Webhook Activity

Track incoming webhooks:

```bash
php artisan tinker
>>> App\Models\ApiToken::with('developer')->get();
>>> App\Models\ClaudeSession::latest()->take(10)->get();
```

## Security Considerations

1. **API Tokens**: Never commit tokens to version control
2. **Database**: Use strong passwords and restrict access
3. **Firewall**: Only allow necessary ports (80, 443)
4. **Updates**: Keep PHP, Laravel, and dependencies updated
5. **Backups**: Regular database backups recommended

## Troubleshooting

### 500 Error
- Check Laravel logs: `storage/logs/laravel.log`
- Verify permissions on storage and cache directories
- Ensure `.env` file exists and is configured

### Queue not processing
- Check queue worker status: `systemctl status devtrack-queue`
- Restart worker: `systemctl restart devtrack-queue`

### API authentication failing
- Verify token is correct in hook script
- Check if token is active: `php artisan tinker` then `App\Models\ApiToken::where('token_hash', hash('sha256', 'your-token'))->first()`

## Updating DevTrack

```bash
cd /var/www/devtrack
git pull origin main
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
systemctl restart devtrack-queue
```