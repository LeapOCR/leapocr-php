.PHONY: help install fetch-spec filter-spec generate clean test test-unit test-integration format lint

OPENAPI_URL := http://localhost:8443/api/v1/docs/openapi.json
GEN_TMP := .openapi-generator-tmp
GEN_SRC := src/Generated
COMPOSER_TOOL := ubi:composer/composer@2.8.12
PHP_TOOL := php@8.3

help:
	@echo "LeapOCR PHP SDK - Available Commands"
	@echo ""
	@echo "Setup:"
	@echo "  make install           Install tooling and Composer dependencies"
	@echo ""
	@echo "Generation:"
	@echo "  make fetch-spec        Download the live OpenAPI spec"
	@echo "  make filter-spec       Keep only SDK-tagged operations"
	@echo "  make generate          Generate the PHP client into src/Generated"
	@echo ""
	@echo "Testing:"
	@echo "  make test              Run unit tests"
	@echo "  make test-integration  Run integration tests (requires LEAPOCR_API_KEY)"
	@echo ""
	@echo "Quality:"
	@echo "  make format            Format PHP sources"
	@echo "  make lint              Lint PHP sources"
	@echo ""
	@echo "Utilities:"
	@echo "  make clean             Remove generated files, cache, and vendor deps"

install:
	mise install
	mise exec $(PHP_TOOL) $(COMPOSER_TOOL) -- composer install

fetch-spec:
	curl -sS $(OPENAPI_URL) > openapi.json

filter-spec: fetch-spec
	python3 scripts/filter_sdk_endpoints.py openapi.json openapi-sdk.json

generate: filter-spec
	rm -rf $(GEN_TMP)
	npx @openapitools/openapi-generator-cli generate \
		-i openapi-sdk.json \
		-g php \
		-o $(GEN_TMP) \
		--skip-validate-spec \
		--additional-properties=invokerPackage=LeapOCRGenerated,packageName=LeapOCRGenerated,composerPackageName=leapocr/leapocr-php,srcBasePath=src/Generated,artifactVersion=2.0.1,apiPackage=Api,modelPackage=Model,variableNamingConvention=snake_case \
		--global-property=apiDocs=false,modelDocs=false,apiTests=false,modelTests=false
	rm -rf $(GEN_SRC)
	mkdir -p $(GEN_SRC)
	rsync -a --delete $(GEN_TMP)/src/Generated/ $(GEN_SRC)/
	rm -rf $(GEN_TMP)

test:
	mise exec $(PHP_TOOL) -- php vendor/bin/phpunit --testsuite unit

test-unit: test

test-integration:
	@if [ -z "$$LEAPOCR_API_KEY" ]; then \
		echo "LEAPOCR_API_KEY environment variable is required"; \
		exit 1; \
	fi
	mise exec $(PHP_TOOL) -- php vendor/bin/phpunit --testsuite integration

format:
	mise exec $(PHP_TOOL) -- php vendor/bin/php-cs-fixer fix --allow-risky=yes

lint:
	mise exec $(PHP_TOOL) -- php -l src/LeapOCR.php
	find src tests -name '*.php' -print0 | xargs -0 -n1 mise exec $(PHP_TOOL) -- php -l

clean:
	rm -rf $(GEN_TMP) $(GEN_SRC) vendor .phpunit.cache coverage
	rm -f openapi.json openapi-sdk.json composer.lock
