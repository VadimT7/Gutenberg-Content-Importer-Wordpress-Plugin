#!/bin/bash

# Gutenberg Content Importer - Development Setup Script
# This script will install all dependencies and start the WordPress environment

set -e

echo "🚀 Setting up Gutenberg Content Importer development environment..."

# Check if required tools are installed
echo "📋 Checking prerequisites..."

if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 16 or higher."
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm."
    exit 1
fi

if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 7.4 or higher."
    exit 1
fi

if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer."
    exit 1
fi

if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker."
    exit 1
fi

echo "✅ All prerequisites are installed!"

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Create necessary directories
echo "📁 Creating build directories..."
mkdir -p assets/js/dist
mkdir -p assets/css/dist

# Build assets
echo "🔨 Building assets..."
npm run build

# Start WordPress environment
echo "🌐 Starting WordPress environment..."
npm run start

echo ""
echo "🎉 Setup complete! Your development environment is ready."
echo ""
echo "📱 Access your local WordPress site:"
echo "   • Main site: http://localhost:8888"
echo "   • Admin panel: http://localhost:8888/wp-admin"
echo "   • Username: admin"
echo "   • Password: password"
echo ""
echo "🧪 Test site: http://localhost:8889"
echo ""
echo "📚 Useful commands:"
echo "   • npm run start    - Start WordPress environment"
echo "   • npm run stop     - Stop WordPress environment"
echo "   • npm run dev      - Build assets in development mode"
echo "   • npm run test     - Run tests"
echo "   • npm run lint     - Check coding standards"
echo ""
echo "📖 For more information, see README-DEVELOPMENT.md"

