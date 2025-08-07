# CodeTrack Production Deployment Checklist - codetrack.dev

## Prerequisites
- [ ] VPS or dedicated server with minimum 2GB RAM
- [ ] Ubuntu 22.04 LTS or similar Linux distribution
- [ ] Domain name (codetrack.dev) with DNS configured
- [ ] SSH access to server

## Server Setup

### 1. System Requirements Installation
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 and required extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl \
    php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Install Nginx
sudo apt install -y nginx

# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Certbot for SSL
sudo apt install -y certbot python3-certbot-nginx

# Install utilities
sudo apt install -y git unzip supervisor
```

### 2. Database Setup
```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE codetrack_prod;
CREATE USER codetrack_user WITH ENCRYPTED PASSWORD 'YOUR_SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE codetrack_prod TO codetrack_user;
\q

# Test connection
psql -h localhost -U codetrack_user -d codetrack_prod
```

### 3. Application Deployment
```bash
# Create web directory
sudo mkdir -p /var/www/codetrack
sudo chown -R $USER:www-data /var/www/codetrack

# Clone repository
cd /var/www
git clone https://github.com/yourusername/devtrack.git codetrack
cd codetrack

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci
npm run build

# Setup environment
cp .env.production .env
nano .env  # Update database password and other settings

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Set proper permissions
sudo chown -R www-data:www-data /var/www/codetrack
sudo chmod -R 755 /var/www/codetrack
sudo chmod -R 775 /var/www/codetrack/storage
sudo chmod -R 775 /var/www/codetrack/bootstrap/cache
```

### 4. Nginx Configuration
```bash
# Create Nginx config
sudo nano /etc/nginx/sites-available/codetrack
```

Add the following configuration:
```nginx
server {
    listen 80;
    server_name codetrack.dev www.codetrack.dev;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name codetrack.dev www.codetrack.dev;
    root /var/www/codetrack/public;

    ssl_certificate /etc/letsencrypt/live/codetrack.dev/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/codetrack.dev/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    client_max_body_size 10M;

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

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/codetrack /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. SSL Certificate
```bash
# Get SSL certificate
sudo certbot --nginx -d codetrack.dev -d www.codetrack.dev

# Test auto-renewal
sudo certbot renew --dry-run
```

### 6. Queue Worker Setup (Supervisor)
```bash
# Create supervisor config
sudo nano /etc/supervisor/conf.d/codetrack-queue.conf
```

Add:
```ini
[program:codetrack-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/codetrack/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/codetrack/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start codetrack-queue:*
```

### 7. Caching & Optimization
```bash
cd /var/www/codetrack

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 8. PHP Optimization
```bash
# Edit PHP-FPM config
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Adjust these values based on server resources
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Enable OPCache
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini

opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 9. Firewall Setup
```bash
# Install UFW
sudo apt install -y ufw

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

### 10. Create API Tokens
```bash
cd /var/www/codetrack

# Generate tokens for developers
php artisan api:generate-token

# Save the generated tokens securely
```

## Post-Deployment

### 1. Monitoring Setup
```bash
# Install monitoring tools
sudo apt install -y htop

# Create log rotation
sudo nano /etc/logrotate.d/codetrack
```

Add:
```
/var/www/codetrack/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

### 2. Backup Strategy
```bash
# Create backup script
sudo nano /usr/local/bin/backup-codetrack.sh
```

Add:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/codetrack"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Backup database
pg_dump -U codetrack_user -h localhost codetrack_prod | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www/codetrack storage .env

# Keep only last 7 days of backups
find $BACKUP_DIR -type f -mtime +7 -delete
```

```bash
# Make executable and add to cron
chmod +x /usr/local/bin/backup-codetrack.sh
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-codetrack.sh
```

### 3. Security Hardening
- [ ] Change default SSH port
- [ ] Disable root SSH login
- [ ] Setup fail2ban
- [ ] Regular security updates
- [ ] Monitor logs regularly

## Updating CodeTrack

```bash
cd /var/www/codetrack

# Put application in maintenance mode
php artisan down

# Pull latest changes
git pull origin main

# Update dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart codetrack-queue:*
sudo systemctl reload php8.2-fpm

# Bring application back online
php artisan up
```

## Verification Checklist

- [ ] Application loads at https://codetrack.dev
- [ ] SSL certificate is valid
- [ ] Can login/register users
- [ ] API webhook endpoint accepts data
- [ ] Queue workers are processing jobs
- [ ] Dashboard displays analytics
- [ ] Database backups are working
- [ ] Logs are being rotated

## Troubleshooting

### Check Application Logs
```bash
tail -f /var/www/codetrack/storage/logs/laravel.log
```

### Check Queue Worker Status
```bash
sudo supervisorctl status codetrack-queue:*
```

### Check Nginx Errors
```bash
sudo tail -f /var/log/nginx/error.log
```

### Check PHP-FPM Status
```bash
sudo systemctl status php8.2-fpm
```

### Test API Endpoint
```bash
curl -X POST https://codetrack.dev/api/webhook/session \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

## Support Contacts

- Server Issues: [Your hosting provider support]
- Application Issues: [Your email/contact]
- DNS/Domain: [Your domain registrar]

## Important URLs

- Production: https://codetrack.dev
- API Endpoint: https://codetrack.dev/api/webhook/session
- Health Check: https://codetrack.dev/up

## Notes

- Remember to update the webhook URL in all Claude Code hook scripts
- Keep API tokens secure and never commit them to version control
- Regular backups are critical - test restore procedures
- Monitor disk space, especially for transcript storage
- Consider CDN for static assets if traffic increases