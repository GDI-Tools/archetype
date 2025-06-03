install:
	rm -rf build && composer install

build:
	chmod +x scripts/build-release.sh && ./scripts/build-release.sh