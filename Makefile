.PHONY: build clean install framework-composer

# Clean build artifacts
clean:
	rm -rf framework/vendor-prefix || true
	rm -f framework/composer.json || true

# Generate framework composer.json
framework-composer:
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

install:
	rm composer.lock || true
	rm -rf vendor || true
	composer install

# Main build using official Strauss method
build: clean framework-composer install
