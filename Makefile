.PHONY: clean-scope scoper autoload write-scope-composer scope

# Step 1: Clean existing scoped vendor folder
clean-scope:
	rm -rf framework/vendor-prefix || true \
    rm -f framework/composer.json || true

# Step 2: Run PHP-Scoper
scoper:
	php-scoper add-prefix --output-dir=framework/vendor-prefix --config=scoper.inc.php --force

# Step 3: Dump autoload files into prefixed vendor directory
autoload:
	COMPOSER_VENDOR_DIR=framework/vendor-prefix composer dump-autoload

# Step 4: Generate framework/composer.json using root version
write-scope-composer:
	$(eval VERSION=$(shell jq -r .version composer.json))
	@mkdir -p framework
	@echo '{ \
	"name": "rolis/archetype", \
	"description": "A modern attribute-based framework for WordPress plugin development", \
	"version": "$(VERSION)", \
	"type": "library", \
	"license": "GPL-2.0-or-later", \
	"authors": [{"name": "Vitalii Sili","email": "vitaliisili@yahoo.com","homepage": "https://vitaliisili.com"}], \
	"require": {"php": ">=8.2"}, \
	"autoload": {"psr-4": {"Archetype\\\\": "src/"}}, \
	"config": {"optimize-autoloader": true, "sort-packages": true}, \
	"minimum-stability": "dev", \
	"prefer-stable": true \
	}' | jq . > framework/composer.json

# Master target: fully scoped framework including new composer.json
build: clean-scope write-scope-composer scoper autoload
