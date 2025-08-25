@echo off
echo ================================
echo PPDB Form Plugin - Test Runner
echo ================================
echo.

REM Check if composer is installed
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Composer not found. Please install Composer first.
    echo Download from: https://getcomposer.org/
    pause
    exit /b 1
)

REM Check if vendor directory exists
if not exist "vendor\" (
    echo Installing dependencies...
    composer install
    if %ERRORLEVEL% NEQ 0 (
        echo ERROR: Failed to install dependencies
        pause
        exit /b 1
    )
    echo.
)

REM Check if phpunit.xml exists
if not exist "phpunit.xml" (
    echo ERROR: phpunit.xml not found
    echo Make sure you're running this from the plugin root directory
    pause
    exit /b 1
)

echo Available commands:
echo 1. Run all tests
echo 2. Run installer tests only
echo 3. Run submission tests only  
echo 4. Run admin tests only
echo 5. Run tests with coverage
echo 6. Setup WordPress test environment
echo 7. Exit
echo.

set /p choice="Choose an option (1-7): "

if "%choice%"=="1" (
    echo Running all tests...
    vendor\bin\phpunit
) else if "%choice%"=="2" (
    echo Running installer tests...
    vendor\bin\phpunit tests\test-installer.php
) else if "%choice%"=="3" (
    echo Running submission tests...
    vendor\bin\phpunit tests\test-submission.php
) else if "%choice%"=="4" (
    echo Running admin tests...
    vendor\bin\phpunit tests\test-admin.php
) else if "%choice%"=="5" (
    echo Running tests with coverage...
    vendor\bin\phpunit --coverage-text
) else if "%choice%"=="6" (
    echo Setting up WordPress test environment...
    echo Please ensure MySQL is running and you have database credentials
    set /p db_name="Database name (default: wordpress_test): "
    if "%db_name%"=="" set db_name=wordpress_test
    
    set /p db_user="Database user (default: root): "
    if "%db_user%"=="" set db_user=root
    
    set /p db_pass="Database password: "
    if "%db_pass%"=="" set db_pass=
    
    set /p db_host="Database host (default: localhost): "
    if "%db_host%"=="" set db_host=localhost
    
    echo Running WordPress test setup...
    bash bin\install-wp-tests.sh %db_name% %db_user% %db_pass% %db_host% latest
) else if "%choice%"=="7" (
    echo Goodbye!
    exit /b 0
) else (
    echo Invalid choice. Please run the script again.
)

echo.
echo Test run completed.
pause
