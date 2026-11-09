install-dev-tools:
	composer update --working-dir=dev-tools

run-php-cs-fixer:
	dev-tools/vendor/bin/php-cs-fixer fix $(ARGS)

run-phpstan:
	dev-tools/vendor/bin/phpstan analyze $(ARGS)

run-phpunit:
	php scripts/check-cluster-pins.php
	php -d max_execution_time=300 dev-tools/vendor/bin/phpunit $(ARGS)
