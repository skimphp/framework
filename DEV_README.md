docker compose exec bin/skim list --agent


test:
docker compose exec app ./vendor/bin/pest 

pest:
docker compose exec app ./vendor/bin/pest --coverage

test-filter:
docker compose exec app ./vendor/bin/pest --filter=$(filter)