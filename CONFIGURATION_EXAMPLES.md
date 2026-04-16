# Configuration Examples

## Default Configuration File

**Location:** `backend/config/app-settings.json`

This file is auto-created on first API call, but you can pre-populate it with initial values.

### Example 1: Basic Setup

```json
{
  "hotelWhatsappNo": "919371519999",
  "menuBlockerStaffCode": "NKSTAFF2026",
  "updatedAt": "2024-01-01T10:00:00",
  "updatedBy": "system"
}
```

### Example 2: Different WhatsApp Number

```json
{
  "hotelWhatsappNo": "919876543210",
  "menuBlockerStaffCode": "NK_STAFF_2024",
  "updatedAt": "2024-01-15T14:30:00",
  "updatedBy": "admin_john"
}
```

### Example 3: Multiple Locations (Extended Format)

```json
{
  "hotelWhatsappNo": "919371519999",
  "menuBlockerStaffCode": "NKSTAFF2026",
  "updatedAt": "2024-01-15T14:30:00",
  "updatedBy": "admin_123",
  "location": "Mumbai",
  "timezone": "Asia/Kolkata",
  "staffCodeExpiry": "2025-12-31"
}
```

---

## Environment Variables (Optional)

If you want to use environment variables instead of the file:

### .env File

```
NK_WHATSAPP_NUMBER=919371519999
NK_STAFF_CODE=NKSTAFF2026
NK_SETTINGS_STORAGE=file  # or 'database'
```

### Usage in PHP

```php
// In bootstrap.php or config
$settings = [
    'whatsappNo' => getenv('NK_WHATSAPP_NUMBER') ?: '919371519999',
    'staffCode' => getenv('NK_STAFF_CODE') ?: 'NKSTAFF2026'
];
```

---

## Database Storage (Optional)

Instead of JSON file, store in database:

### Database Table

```sql
CREATE TABLE app_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial values
INSERT INTO app_settings (setting_key, setting_value, updated_by) VALUES
('hotelWhatsappNo', '919371519999', 'system'),
('menuBlockerStaffCode', 'NKSTAFF2026', 'system');
```

### PHP Implementation

```php
<?php
// In api_settings.php, modify getSettings() function

function getSettingsFromDB() {
    global $db;
    
    try {
        $stmt = $db->query('SELECT setting_key, setting_value FROM app_settings');
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log('DB Query Error: ' . $e->getMessage());
        return getDefaultSettings();
    }
}

function saveSettingsToDB($settings) {
    global $db;
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare('
                INSERT INTO app_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ');
            
            $adminId = Auth::getAdminId();
            $stmt->execute([
                $key,
                $value,
                'admin_' . $adminId
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('DB Error: ' . $e->getMessage());
        return false;
    }
}
?>
```

---

## WordPress Integration (Optional)

If using WordPress:

### Using WordPress Options

```php
<?php
// In api_settings.php

function getSettingsWordPress() {
    return [
        'hotelWhatsappNo' => get_option('nk_hotel_whatsapp', '919371519999'),
        'menuBlockerStaffCode' => get_option('nk_staff_code', 'NKSTAFF2026')
    ];
}

function saveSettingsWordPress($settings) {
    update_option('nk_hotel_whatsapp', $settings['hotelWhatsappNo']);
    update_option('nk_staff_code', $settings['menuBlockerStaffCode']);
    return true;
}
?>
```

### WordPress Admin Menu

```php
<?php
// In plugin or theme functions.php

add_action('admin_menu', function() {
    add_menu_page(
        'App Settings',
        'App Settings',
        'manage_options',
        'nk-app-settings',
        function() {
            echo '<iframe src="' . home_url('/admin_settings_standalone.html') . '" width="100%" height="800" frameborder="0"></iframe>';
        }
    );
});
?>
```

---

## Docker Configuration (Optional)

### Dockerfile

```dockerfile
FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libicu-dev

# Copy application
COPY . /var/www/html

# Create config directory
RUN mkdir -p /var/www/html/backend/config && \
    chown -R www-data:www-data /var/www/html/backend/config

# Enable mod_rewrite
RUN a2enmod rewrite

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "80:80"
    volumes:
      - ./backend/config:/var/www/html/backend/config
    environment:
      NK_WHATSAPP_NUMBER: "919371519999"
      NK_STAFF_CODE: "NKSTAFF2026"
```

---

## Kubernetes Configuration (Optional)

### ConfigMap

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: nk-app-settings
  namespace: default
data:
  hotelWhatsappNo: "919371519999"
  menuBlockerStaffCode: "NKSTAFF2026"
```

### Pod Environment Variables

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: nk-app
spec:
  containers:
  - name: web
    image: nk-app:latest
    envFrom:
    - configMapRef:
        name: nk-app-settings
```

---

## Environment-Specific Configs

### Development

```json
{
  "hotelWhatsappNo": "919999999999",
  "menuBlockerStaffCode": "DEV_STAFF",
  "debugMode": true
}
```

### Production

```json
{
  "hotelWhatsappNo": "919371519999",
  "menuBlockerStaffCode": "NKSTAFF2026",
  "debugMode": false,
  "cacheEnabled": true
}
```

### Staging

```json
{
  "hotelWhatsappNo": "919876543210",
  "menuBlockerStaffCode": "STAGE_STAFF",
  "debugMode": false
}
```

---

## Advanced: Custom Validation Rules

### Extend api_settings.php

```php
<?php

class SettingsValidator {
    public static function validateWhatsappNo($no) {
        // Remove non-digits
        $digits = preg_replace('/\D/', '', $no);
        
        // Must be 10+ digits
        if (strlen($digits) < 10) {
            throw new Exception('WhatsApp number must be at least 10 digits');
        }
        
        // Optional: Check country code (91 for India)
        if (!preg_match('/^91\d{10}$/', $digits) && !preg_match('/^\d{10}$/', $digits)) {
            throw new Exception('Invalid WhatsApp number format');
        }
        
        return $digits;
    }
    
    public static function validateStaffCode($code) {
        // Must be 4-20 characters, alphanumeric + special chars
        if (strlen($code) < 4 || strlen($code) > 20) {
            throw new Exception('Staff code must be 4-20 characters');
        }
        
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
            throw new Exception('Staff code can only contain letters, numbers, hyphens and underscores');
        }
        
        return strtoupper($code);
    }
}

// Usage in api_settings.php
try {
    $wa = SettingsValidator::validateWhatsappNo($input['hotelWhatsappNo']);
    $code = SettingsValidator::validateStaffCode($input['menuBlockerStaffCode']);
} catch (Exception $e) {
    Response::json(['error' => $e->getMessage()], 400);
}

?>
```

---

## Monitoring & Analytics

### Track Settings Changes

```php
<?php
// Log all changes
function logSettingsChange($key, $oldValue, $newValue, $adminId) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'admin_id' => $adminId,
        'key' => $key,
        'old_value' => substr($oldValue, 0, 3) . '***', // Hide sensitive data
        'new_value' => substr($newValue, 0, 3) . '***',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    error_log(json_encode($log));
    // Or save to database
}
?>
```

### Audit Trail

```sql
CREATE TABLE settings_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id VARCHAR(255),
    setting_key VARCHAR(255),
    old_value TEXT,
    new_value TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
);
```

---

## Backup & Recovery

### Automated Backup

```bash
#!/bin/bash
# save_settings.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/nk_settings"
mkdir -p $BACKUP_DIR

cp /var/www/html/backend/config/app-settings.json \
   $BACKUP_DIR/app-settings_$DATE.json

# Keep last 30 days
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/app-settings_$DATE.json"
```

### Restore from Backup

```bash
#!/bin/bash
# restore_settings.sh

BACKUP_FILE=$1

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Backup file not found: $BACKUP_FILE"
    exit 1
fi

cp "$BACKUP_FILE" /var/www/html/backend/config/app-settings.json
echo "Settings restored from: $BACKUP_FILE"
```

---

## API Rate Limiting (Optional)

```php
<?php
// Add to api_settings.php

class RateLimiter {
    public static function checkLimit($adminId, $maxRequests = 10, $timeWindow = 3600) {
        $key = "rate_limit_$adminId";
        $current = apcu_fetch($key) ?: 0;
        
        if ($current >= $maxRequests) {
            http_response_code(429); // Too Many Requests
            die(json_encode(['error' => 'Rate limit exceeded']));
        }
        
        apcu_store($key, $current + 1, $timeWindow);
    }
}

// Usage
Auth::verifyRequest();
RateLimiter::checkLimit(Auth::getAdminId());
?>
```

---

## Testing Configuration

### PHPUnit Test

```php
<?php
// tests/SettingsApiTest.php

class SettingsApiTest extends TestCase {
    public function testGetSettings() {
        $response = $this->get('/backend/api_settings.php');
        
        $this->assertEquals(200, $response->status);
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('hotelWhatsappNo', $response->json()['data']);
    }
    
    public function testUpdateSettings() {
        $response = $this->post('/backend/api_settings.php', [
            'hotelWhatsappNo' => '919876543210',
            'menuBlockerStaffCode' => 'TEST2024'
        ]);
        
        $this->assertEquals(200, $response->status);
        $this->assertTrue($response->json()['success']);
    }
}
?>
```

---

## Migration Guide

### From Hardcoded to Dynamic

```javascript
// Before: Hardcoded
const HOTEL_WHATSAPP_NO = "919371519999";
const MENU_BLOCKER_STAFF_CODE = "NKSTAFF2026";

// After: Dynamic via API
// (No code changes needed! menu-blocker-init.js handles it)
```

**The beautiful part:** Your existing `menu-blocker.js` doesn't need to change at all! The initializer script sets the window variables automatically.

---

## Conclusion

Choose the configuration approach that best fits your stack:

- **Simple:** JSON file (included, no setup needed)
- **Professional:** Database + Audit logging
- **Enterprise:** Kubernetes + ConfigMaps
- **WordPress:** WordPress options table

All configurations work with the same API endpoint and admin panel!
