#!/bin/bash

echo "================================"
echo "PPDB Form Plugin - Test Runner"
echo "================================"
echo

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "ERROR: Composer not found. Please install Composer first."
    echo "Download from: https://getcomposer.org/"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to install dependencies"
        exit 1
    fi
    echo
fi

# Check if phpunit.xml exists
if [ ! -f "phpunit.xml" ]; then
    echo "ERROR: phpunit.xml not found"
    echo "Make sure you're running this from the plugin root directory"
    exit 1
fi

echo "Available commands:"
echo "1. Run all tests"
echo "2. Run installer tests only"
echo "3. Run submission tests only"
echo "4. Run admin tests only"
echo "5. Run tests with coverage"
echo "6. Setup WordPress test environment"
echo "7. Exit"
echo

read -p "Choose an option (1-7): " choice

case $choice in
    1)
        echo "Running all tests..."
        vendor/bin/phpunit
        ;;
    2)
        echo "Running installer tests..."
        vendor/bin/phpunit tests/test-installer.php
        ;;
    3)
        echo "Running submission tests..."
        vendor/bin/phpunit tests/test-submission.php
        ;;
    4)
        echo "Running admin tests..."
        vendor/bin/phpunit tests/test-admin.php
        ;;
    5)
        echo "Running tests with coverage..."
        vendor/bin/phpunit --coverage-text
        ;;
    6)
        echo "Setting up WordPress test environment..."
        echo "Please ensure MySQL is running and you have database credentials"
        
        read -p "Database name (default: wordpress_test): " db_name
        db_name=${db_name:-wordpress_test}
        
        read -p "Database user (default: root): " db_user
        db_user=${db_user:-root}
        
        read -s -p "Database password: " db_pass
        echo
        
        read -p "Database host (default: localhost): " db_host
        db_host=${db_host:-localhost}
        
        echo "Running WordPress test setup..."
        bash bin/install-wp-tests.sh "$db_name" "$db_user" "$db_pass" "$db_host" latest
        ;;
    7)
        echo "Goodbye!"
        exit 0
        ;;
    *)
        echo "Invalid choice. Please run the script again."
        exit 1
        ;;
esac

echo
echo "Test run completed."
