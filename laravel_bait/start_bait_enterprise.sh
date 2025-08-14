#!/bin/bash

# BAIT Service Enterprise - Linux/WSL Startup Script
# This script starts and configures the BAIT Service Enterprise system

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===============================================${NC}"
echo -e "${BLUE}   BAIT Service Enterprise - Startup Script${NC}"
echo -e "${BLUE}===============================================${NC}"
echo

# Function to check if a service is running
check_service() {
    if pgrep -f "$1" > /dev/null; then
        echo -e "${GREEN}✓${NC} $2 is running"
        return 0
    else
        echo -e "${RED}❌${NC} $2 is not running"
        return 1
    fi
}

# Check Apache/XAMPP
echo -e "${YELLOW}[1/6]${NC} Checking Web Server..."
if check_service "httpd\|apache2" "Web Server"; then
    true
else
    echo "Starting Apache..."
    if command -v systemctl &> /dev/null; then
        sudo systemctl start apache2 2>/dev/null || sudo systemctl start httpd 2>/dev/null
    elif command -v service &> /dev/null; then
        sudo service apache2 start 2>/dev/null || sudo service httpd start 2>/dev/null
    fi
fi

# Check MySQL
echo
echo -e "${YELLOW}[2/6]${NC} Checking MySQL Server..."
if check_service "mysqld\|mysql" "MySQL Server"; then
    true
else
    echo "Starting MySQL..."
    if command -v systemctl &> /dev/null; then
        sudo systemctl start mysql 2>/dev/null || sudo systemctl start mysqld 2>/dev/null
    elif command -v service &> /dev/null; then
        sudo service mysql start 2>/dev/null || sudo service mysqld start 2>/dev/null
    fi
fi

# Test Database Connection
echo
echo -e "${YELLOW}[3/6]${NC} Testing Database Connection..."
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;port=3306', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '${GREEN}✓${NC} MySQL connection successful\n';
    
    \$stmt = \$pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    \$stmt->execute(['bait_service_real']);
    
    if (\$stmt->fetch()) {
        echo '${GREEN}✓${NC} Database bait_service_real exists\n';
    } else {
        echo '${RED}❌${NC} Database bait_service_real not found\n';
        echo 'Creating database...\n';
        \$pdo->exec('CREATE DATABASE IF NOT EXISTS bait_service_real CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        echo '${GREEN}✓${NC} Database created\n';
    }
} catch (Exception \$e) {
    echo '${RED}❌${NC} Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

# Check Database Schema
echo
echo -e "${YELLOW}[4/6]${NC} Checking Database Schema..."
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;port=3306;dbname=bait_service_real', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    \$tables = ['technicians', 'clients', 'activities', 'alerts', 'timbratures'];
    \$existingTables = [];
    
    foreach (\$tables as \$table) {
        try {
            \$stmt = \$pdo->query('SELECT 1 FROM ' . \$table . ' LIMIT 1');
            \$existingTables[] = \$table;
        } catch (Exception \$e) {
            // Table doesn't exist
        }
    }
    
    echo '${GREEN}✓${NC} Found ' . count(\$existingTables) . '/' . count(\$tables) . ' tables\n';
    
    if (count(\$existingTables) < count(\$tables)) {
        echo '${YELLOW}Missing tables: ' . implode(', ', array_diff(\$tables, \$existingTables)) . '${NC}\n';
        echo 'To create missing tables, run: mysql -u root < bait_service_real_database_setup.sql\n';
    }
} catch (Exception \$e) {
    echo '${RED}❌${NC} Schema check failed: ' . \$e->getMessage() . '\n';
}
"

# Test API Endpoints
echo
echo -e "${YELLOW}[5/6]${NC} Testing API Endpoints..."
php -r "
\$baseUrl = 'http://localhost/controlli/laravel_bait/public/api/';
\$endpoints = ['health', 'status', 'dashboard/data'];
\$working = 0;

foreach (\$endpoints as \$endpoint) {
    \$context = stream_context_create(['http' => ['timeout' => 5]]);
    \$response = @file_get_contents(\$baseUrl . \$endpoint, false, \$context);
    
    if (\$response && json_decode(\$response)) {
        echo '${GREEN}✓${NC} API /' . \$endpoint . ' working\n';
        \$working++;
    } else {
        echo '${RED}❌${NC} API /' . \$endpoint . ' failed\n';
    }
}

echo 'API Status: ' . \$working . '/' . count(\$endpoints) . ' endpoints working\n';
"

# Final Status
echo
echo -e "${YELLOW}[6/6]${NC} System Status Summary..."
echo
echo -e "${GREEN}===============================================${NC}"
echo -e "${GREEN}   BAIT Service Enterprise Started!${NC}"
echo -e "${GREEN}===============================================${NC}"
echo
echo -e "${BLUE}Available URLs:${NC}"
echo -e "  📊 Dashboard: ${YELLOW}http://localhost/controlli/laravel_bait/public/index_standalone.php${NC}"
echo -e "  🧪 Test Suite: ${YELLOW}http://localhost/controlli/laravel_bait/public/test_full_system.php${NC}"
echo -e "  🔍 DB Test: ${YELLOW}http://localhost/controlli/laravel_bait/public/test_database_connection.php${NC}"
echo
echo -e "${BLUE}API Endpoints:${NC}"
echo -e "  📡 Health: ${YELLOW}http://localhost/controlli/laravel_bait/public/api/health${NC}"
echo -e "  📊 Dashboard Data: ${YELLOW}http://localhost/controlli/laravel_bait/public/api/dashboard/data${NC}"
echo -e "  📈 KPIs: ${YELLOW}http://localhost/controlli/laravel_bait/public/api/kpis${NC}"
echo -e "  🚨 Alerts: ${YELLOW}http://localhost/controlli/laravel_bait/public/api/alerts${NC}"
echo -e "  ⚙️  Status: ${YELLOW}http://localhost/controlli/laravel_bait/public/api/status${NC}"
echo

# Try to open browser (WSL specific)
if command -v explorer.exe &> /dev/null; then
    echo "Opening dashboard in browser..."
    explorer.exe "http://localhost/controlli/laravel_bait/public/index_standalone.php" 2>/dev/null &
elif command -v xdg-open &> /dev/null; then
    echo "Opening dashboard in browser..."
    xdg-open "http://localhost/controlli/laravel_bait/public/index_standalone.php" 2>/dev/null &
elif command -v open &> /dev/null; then
    echo "Opening dashboard in browser..."
    open "http://localhost/controlli/laravel_bait/public/index_standalone.php" 2>/dev/null &
fi

echo "Setup complete! Press Ctrl+C to exit."