# Test steps, install, lint, test.
test: install lint-ci test-ci

# Composer install step
install:
	composer install

# Linter step
lint-ci:
	composer lint

# Test step
test-ci:
	composer test-ci
