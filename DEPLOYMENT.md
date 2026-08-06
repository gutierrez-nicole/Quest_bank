# 🌐 QuestBank — Production Deployment Guide

This guide outlines production hardening, server webserver configurations, directory permissions, SSL setup, and maintenance procedures for deploying **QuestBank v2.2-RC1** in production.

---

## 🏗️ Production Server Architecture

```
                       ┌────────────────────────┐
                       │     HTTPS Client       │
                       └───────────┬────────────┘
                                   │ SSL / TLS (443)
                                   ▼
                       ┌────────────────────────┐
                       │ Nginx / Apache Server  │
                       └───────────┬────────────┘
                                   │ PHP 8.0+ FastCGI / FPM
                                   ▼
                       ┌────────────────────────┐
                       │   QuestBank Core App   │
                       └─────┬────────────┬─────┘
                             │            │
         ┌───────────────────▼─┐        ┌─▼──────────────────┐
         │ MySQL / MariaDB     │        │ Groq AI Cloud API  │
         │ Database            │        │ (Vision & LLM)     │
         └─────────────────────┘        └────────────────────┘
```

---

## 🔒 Security & Web Server Configuration

### 1. Nginx Production Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name questbank.edu.ph;

    root /var/www/questbank;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/questbank.edu.ph/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/questbank.edu.ph/privkey.pem;

    # Restrict sensitive dotfiles and configuration
    location ~ /\. {
        deny all;
    }

    # Restrict direct web access to internal storage and database backups
    location ~ ^/(storage|database/backups|app|includes)/ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }
}
```

### 2. Apache Production Configuration (`.htaccess`)
Ensure Apache has `AllowOverride All` enabled. QuestBank automatically enforces internal directory blockages via `.htaccess` in:
- `storage/tmp/.htaccess` -> `Deny from all`
- `database/backups/.htaccess` -> `Deny from all`

---

## 🛡️ Production Security Checklist

- [x] **HTTPS Enforcement:** Set `ini_set('session.cookie_secure', 1)` for HTTPS environments.
- [x] **Camera API Requirement:** Browser `getUserMedia()` media streams require HTTPS TLS encryption or `localhost` origin. Ensure SSL certificates (Let's Encrypt / TLS 1.3) are properly configured on production domains so mobile devices can access rear-facing cameras (`facingMode: "environment"`).
- [x] **Environment Security:** `APP_ENV=production` in `.env`.
- [x] **Database Isolation:** Restrict MySQL user permissions to `bankquest_db` only.
- [x] **Backup Protection:** Direct HTTP access to `database/backups/` is prohibited.
- [x] **Storage Permissions:** `chmod 775` on upload folders, webserver user ownership (`www-data:www-data`).
- [x] **Maintenance Mode:** Use `SystemSettingsService::setSetting('maintenance_mode', 'on')` during maintenance.

---

## 📦 Automated Backups & System Maintenance

### 1. Scheduled Database Backup Cron Job
Run automated nightly database backups at 2:00 AM:
```bash
0 2 * * * cd /var/www/questbank && php -r "require 'app/bootstrap.php'; require 'app/services/BackupService.php'; BackupService::createBackup(null);" >> /var/log/questbank_backup.log 2>&1
```

### 2. Storage Cleanup Cron Job
Clean expired previews and temporary files weekly:
```bash
0 3 * * 0 cd /var/www/questbank && php -r "require 'app/bootstrap.php'; require 'app/services/StorageManagementService.php'; StorageManagementService::cleanTemporaryFiles();" >> /var/log/questbank_clean.log 2>&1
```

---

## 🩺 System Health Diagnostics

Access the System Health Console (`admin/health.php`) to verify production readiness:
- Database Connection Status (`PASS`)
- Writable Storage Paths (`PASS`)
- Protected Backup Directory (`PASS`)
- Required PHP Extensions (`PASS`)
- OCR Command Availability (`PASS` / `WARNING`)
- Groq AI Key Status (`PASS`)
- Maintenance Mode (`OFF`)
