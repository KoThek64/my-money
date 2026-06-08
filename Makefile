# Makefile — raccourcis pour le projet my-money
# Usage : make <commande>  (ex : make watch, make test)
# Tape "make" ou "make help" pour voir la liste.

# Exécutables
PHP      = php
CONSOLE  = $(PHP) bin/console
COMPOSER = composer
SYMFONY  = symfony

# Couleurs
GREEN = \033[0;32m
NC    = \033[0m

.DEFAULT_GOAL := help
.PHONY: help watch phpstan rector rector-fix cs cs-fix test qa

## —— Aide ———————————————————————————————————————————————
help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}{printf "$(GREEN)%-18s$(NC) %s\n", $$1, $$2}' \
		| sed -e 's/\[32m##/\n/'

## —— Assets (JS / SCSS) —————————————————————————————————
watch: ## Compile le SCSS en continu (à laisser tourner pendant le dev)
	$(CONSOLE) sass:build --watch

## —— Qualité de code ————————————————————————————————————
phpstan: ## Analyse statique (PHPStan)
	$(PHP) vendor/bin/phpstan analyse

rector: ## Montre les refactos proposées (sans rien changer)
	$(PHP) vendor/bin/rector process --dry-run

rector-fix: ## Applique les refactos de Rector
	$(PHP) vendor/bin/rector process

cs: ## Montre les corrections de style (sans rien changer)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style du code
	$(PHP) vendor/bin/php-cs-fixer fix

test: ## Lance les tests
	$(PHP) bin/phpunit

qa: cs phpstan test ## Lance tous les contrôles qualité (style + analyse + tests)
