@echo off
REM Gutenberg Content Importer - Development Setup Script for Windows
REM This script will install all dependencies and start the WordPress environment

echo 🚀 Setting up Gutenberg Content Importer development environment...

REM Check if required tools are installed
echo 📋 Checking prerequisites...

node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js is not installed. Please install Node.js 16 or higher.
    pause
    exit /b 1
)

npm --version >nul 2>&1
if errorlevel 1 (
    echo ❌ npm is not installed. Please install npm.
    pause
    exit /b 1
)

php --version >nul 2>&1
if errorlevel 1 (
    echo ❌ PHP is not installed. Please install PHP 7.4 or higher.
    pause
    exit /b 1
)

composer --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Composer is not installed. Please install Composer.
    pause
    exit /b 1
)

docker --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker is not installed. Please install Docker Desktop.
    pause
    exit /b 1
)

echo ✅ All prerequisites are installed!

REM Install PHP dependencies
echo 📦 Installing PHP dependencies...
composer install

REM Install Node.js dependencies
echo 📦 Installing Node.js dependencies...
npm install

REM Create necessary directories
echo 📁 Creating build directories...
if not exist "assets\js\dist" mkdir "assets\js\dist"
if not exist "assets\css\dist" mkdir "assets\css\dist"

REM Build assets
echo 🔨 Building assets...
npm run build

REM Start WordPress environment
echo 🌐 Starting WordPress environment...
npm run start

echo.
echo 🎉 Setup complete! Your development environment is ready.
echo.
echo 📱 Access your local WordPress site:
echo    • Main site: http://localhost:8888
echo    • Admin panel: http://localhost:8888/wp-admin
echo    • Username: admin
echo    • Password: password
echo.
echo 🧪 Test site: http://localhost:8889
echo.
echo 📚 Useful commands:
echo    • npm run start    - Start WordPress environment
echo    • npm run stop     - Stop WordPress environment
echo    • npm run dev      - Build assets in development mode
echo    • npm run test     - Run tests
echo    • npm run lint     - Check coding standards
echo.
echo 📖 For more information, see README-DEVELOPMENT.md
pause

