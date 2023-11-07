install:
	composer install

test: install
	composer test-ci
