clean-scope:
	rm -rf framework/vendor-prefix

scoper:
	php-scoper add-prefix --output-dir=framework/vendor-prefix --config=scoper.inc.php --force

autoload:
	COMPOSER_VENDOR_DIR=framework/vendor-prefix composer dump-autoload

scope: clean-scope scoper autoload
