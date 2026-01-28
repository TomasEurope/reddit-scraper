up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker exec reddit-php ./bin/console cache:clear
	cd ./app && npm run dev
	make down
	make up

watch:
	docker exec reddit-php ./bin/console cache:clear
	cd ./app && npm run watch

dev:
	docker exec reddit-php ./bin/console cache:clear
	cd ./app && npm run dev

logs:
	docker compose logs -f

ps:
	docker ps

cache:
	docker exec reddit-php ./bin/console cache:clear

fetch:
	docker exec reddit-php ./bin/console app:reddit:fetch-mp4 --pages 5

import:
	docker exec reddit-php ./bin/console app:reddit:import-mp4

reindex:
	docker exec reddit-php ./bin/console app:reddit:reindex

flush:
	docker exec reddit-php ./bin/console app:reddit:flush

php:
	docker exec reddit-php sh

nginx:
	docker exec reddit-nginx sh

db:
	docker exec reddit-postgres psql -U app app

adminer:
	docker logs -f reddit-adminer

opensearch:
	docker exec reddit-opensearch sh

redis:
	docker exec reddit-redis sh

cache-clear:
	docker exec reddit-php php bin/console cache:clear

migrate:
	docker exec reddit-php php bin/console doctrine:migrations:migrate --no-interaction

consume:
	docker exec reddit-php php bin/console messenger:consume async -vv

perm:
	sudo chown -R $$USER:$$USER app data 

install:
	printf "\nStarting install :)\n\n"
	sleep 3
	mkdir -p ./data/postgres/data
	make down
	make up
	docker exec reddit-php composer install
	cd ./app && npm install
	make dev
	make migrate
	printf "\n\nWaititng 60 seconds for Opensearch...\n\n"
	sleep 60
	docker exec reddit-php ./bin/console app:reddit:fetch-mp4 --pages 10 --debug
	printf "\n\nStarting consumer, you should see result in few moments at:\n\n"
	printf "http://localhost/\n"
	make import