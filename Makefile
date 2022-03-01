watch:
	watchexec -e php -cr -- "make stan && make tests"

watch-debug:
	watchexec -e php -cr -- docker-compose exec php ./vendor/bin/phpunit --group debug

stan:
	vendor/bin/phpstan analyse src tests --level 5

.PHONY: tests
tests:
	docker-compose exec php ./vendor/bin/phpunit
