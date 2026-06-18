# Makefile — raccourcis pour le projet my-money
# Usage : make <commande>  (ex : make watch, make test)
# Tape "make" ou "make help" pour voir la liste.

# Exécutables
PHP      = php
CONSOLE  = $(PHP) bin/console
COMPOSER = composer
SYMFONY  = symfony

# Couleurs
GREEN  = \033[0;32m
CYAN   = \033[1;36m
YELLOW = \033[1;33m
RED    = \033[0;31m
NC     = \033[0m

# Bannière de section : $(call title,Texte de la section)
define title
	@printf "\n$(CYAN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)\n"
	@printf "$(CYAN) ▶  %s$(NC)\n" "$(1)"
	@printf "$(CYAN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)\n"
endef

.DEFAULT_GOAL := help
.PHONY: help watch phpstan rector rector-fix cs cs-fix test qa

## —— Aide ———————————————————————————————————————————————
help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}{printf "$(GREEN)%-18s$(NC) %s\n", $$1, $$2}' \
		| sed -e 's/\[32m##/\n/'

## —— Assets (JS / SCSS) —————————————————————————————————
watch: ## Compile le SCSS en continu (à laisser tourner pendant le dev)
	$(call title,🎨  SCSS — compilation en continu)
	$(CONSOLE) sass:build --watch

## —— Qualité de code ————————————————————————————————————
phpstan: ## Analyse statique (PHPStan)
	$(call title,🔍  PHPStan — analyse statique)
	$(PHP) vendor/bin/phpstan analyse

rector: ## Montre les refactos proposées (sans rien changer)
	$(call title,♻️  Rector — refactos proposées (dry-run))
	$(PHP) vendor/bin/rector process --dry-run

rector-fix: ## Applique les refactos de Rector
	$(call title,♻️  Rector — application des refactos)
	$(PHP) vendor/bin/rector process

cs: ## Montre les corrections de style (sans rien changer)
	$(call title,✨  Coding Standards — vérification du style (dry-run))
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige le style du code
	$(call title,✨  Coding Standards — correction du style)
	$(PHP) vendor/bin/php-cs-fixer fix

test: ## Lance les tests
	$(call title,🧪  Tests — PHPUnit)
	$(PHP) bin/phpunit

qa: cs phpstan rector test ## Lance tous les contrôles qualité (style + analyse + rector + tests)
	@printf "\n$(GREEN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)\n"
	@printf "$(GREEN) ✅  QA terminée — tous les contrôles sont passés$(NC)\n"
	@printf "$(GREEN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)\n\n"
