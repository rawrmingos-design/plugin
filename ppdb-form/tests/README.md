# PPDB Form Plugin Tests

Unit tests untuk WordPress plugin PPDB Form menggunakan PHPUnit dan WordPress test framework.

## Setup

### Prerequisites

1. PHP 7.4 atau lebih tinggi
2. MySQL/MariaDB database
3. Composer (untuk dependencies)
4. Git dan SVN (untuk download WordPress test suite)

### Instalasi

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Setup WordPress test environment:**
   ```bash
   bash bin/install-wp-tests.sh wordpress_test wp_test wp_test localhost latest
   ```
   
   Ganti kredensial database sesuai kebutuhan:
   - `wordpress_test` - nama database test
   - `wp_test` - username database
   - `wp_test` - password database
   - `localhost` - host database
   - `latest` - versi WordPress

3. **Buat database test:**
   ```sql
   CREATE DATABASE wordpress_test;
   GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wp_test'@'localhost' IDENTIFIED BY 'wp_test';
   FLUSH PRIVILEGES;
   ```

### Menjalankan Tests

**Jalankan semua tests:**
```bash
composer test
# atau
phpunit
```

**Jalankan tests dengan coverage:**
```bash
composer test:coverage
```

**Jalankan test file tertentu:**
```bash
phpunit tests/test-installer.php
phpunit tests/test-submission.php
```

**Jalankan test method tertentu:**
```bash
phpunit --filter test_activation_creates_tables tests/test-installer.php
```

## Struktur Test

### File Test

- **`bootstrap.php`** - Setup environment dan load plugin
- **`test-installer.php`** - Tests untuk instalasi dan upgrade plugin
- **`test-submission.php`** - Tests untuk form rendering dan submission
- **`README.md`** - Dokumentasi testing ini

### Test Coverage

#### Installer Tests (`test-installer.php`)
- ✅ **Table Creation**: Verifikasi pembuatan tabel saat aktivasi
- ✅ **Database Version**: Manajemen versi database
- ✅ **Table Structure**: Validasi struktur kolom tabel
- ✅ **Upgrade Functionality**: Testing upgrade dari versi lama
- ✅ **Default Data**: Pembuatan data default (departments)
- ✅ **Database Indexes**: Verifikasi pembuatan index
- ✅ **Activation Hook**: Testing hook aktivasi plugin

#### Submission Tests (`test-submission.php`)
- ✅ **Form Rendering**: Shortcode render dengan benar
- ✅ **Field Registry**: Validasi field registry dan struktur
- ✅ **Data Submission**: Insert submission data ke database
- ✅ **Multi-step Forms**: Konfigurasi dan rendering multi-step
- ✅ **Data Sanitization**: Protection terhadap XSS dan injection
- ✅ **Honeypot Security**: Field honeypot untuk anti-bot
- ✅ **Form Validation**: Required fields dan validation
- ✅ **File Upload Fields**: Rendering field upload dokumen
- ✅ **Nonce Security**: Verifikasi nonce security
- ✅ **Error Handling**: Handling form tidak valid/tidak ada
- ✅ **Department Options**: Dropdown jurusan dari database

## Environment Variables

Customize database settings dengan environment variables:

```bash
export WP_TESTS_DB_NAME="custom_test_db"
export WP_TESTS_DB_USER="custom_user"
export WP_TESTS_DB_PASSWORD="custom_pass"
export WP_TESTS_DB_HOST="custom_host"
```

## Troubleshooting

### Error: Cannot connect to database
```bash
# Pastikan database exists dan credentials benar
mysql -u wp_test -p -e "SHOW DATABASES;"

# Re-run installer
bash bin/install-wp-tests.sh wordpress_test wp_test wp_test localhost latest
```

### Error: WordPress test suite not found
```bash
# Manual install WordPress test suite
rm -rf /tmp/wordpress-tests-lib
bash bin/install-wp-tests.sh wordpress_test wp_test wp_test localhost latest
```

### Error: Class not found
```bash
# Pastikan autoloading berjalan
composer dump-autoload
```

## Continuous Integration

Untuk CI environments (GitHub Actions, GitLab CI, etc):

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
          MYSQL_USER: wp_test
          MYSQL_PASSWORD: wp_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        extensions: mysqli, mbstring
        
    - name: Install dependencies
      run: composer install
      
    - name: Setup WordPress test environment
      run: bash bin/install-wp-tests.sh wordpress_test wp_test wp_test 127.0.0.1 latest
      
    - name: Run tests
      run: composer test
```

## Coverage Report

Generate HTML coverage report:

```bash
phpunit --coverage-html coverage/
```

Buka `coverage/index.html` untuk melihat detailed coverage report.

## Best Practices

1. **Isolasi Tests**: Setiap test method harus independen
2. **Setup/Teardown**: Gunakan `setUp()` dan `tearDown()` untuk cleanup
3. **Assertions**: Gunakan assertion yang spesifik dan descriptive
4. **Mocking**: Mock external dependencies dan WordPress functions
5. **Data Providers**: Gunakan data providers untuk test multiple scenarios

## Menambah Test Baru

```php
<?php
class Test_New_Feature extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Setup test data
    }

    public function tearDown(): void
    {
        // Cleanup
        parent::tearDown();
    }

    public function test_new_functionality()
    {
        // Test implementation
        $this->assertTrue(true);
    }
}
```