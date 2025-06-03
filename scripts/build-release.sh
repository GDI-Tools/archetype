#!/bin/bash
echo "🔧 Building conflict-free Archetype release..."

# Clean previous build
rm -rf build/

# Run PHP-Scoper
echo "📦 Running PHP-Scoper..."
php -d memory_limit=-1 php-scoper add-prefix --config=scoper.inc.php --force

# Copy Composer autoloader files (unscoped)
echo "📋 Copying Composer autoloader..."
cp -r vendor/composer/ build/vendor/composer/
cp vendor/autoload.php build/vendor/autoload.php

# Create production autoloader
echo "🔧 Creating production autoloader..."
cat > build/autoload.php << 'EOF'
<?php
/**
 * Archetype Framework - Production Autoloader
 */
if (defined('ARCHETYPE_AUTOLOADER_LOADED')) {
    return;
}
define('ARCHETYPE_AUTOLOADER_LOADED', true);

require_once __DIR__ . '/vendor/scoper-autoload.php';
EOF

# Create production composer.json
echo "📄 Creating production composer.json..."
cat > build/composer.json << 'EOF'
{
  "name": "rolis/archetype",
  "description": "A modern attribute-based framework for WordPress plugin development (Conflict-Free)",
  "version": "1.0.6",
  "type": "library",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.2"
  },
  "autoload": {
    "files": ["autoload.php"]
  }
}
EOF

# Copy additional files
cp README.md build/ 2>/dev/null || true

echo "✅ Build complete! Test with: php test-scoped.php"