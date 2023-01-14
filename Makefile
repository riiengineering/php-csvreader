PHP ?= php

SRC_DIR = src
TESTS_DIR = tests

all: csvreader.php

csvreader.php: .FORCE
	$(PHP) compile.php $(SRC_DIR)/CSVReader.php >$@

test: .FORCE
	@if test -n '$(PHPUNIT)'; then set -- $(PHPUNIT); elif test -x vendor/bin/phpunit; then set -- vendor/bin/phpunit; else set -- phpunit; fi; set -- "$$@" --test-suffix .php -vv $(TESTS_DIR); echo "$$@"; "$$@"

sast: .FORCE
	@if test -n '$(PHPSTAN)'; then set -- $(PHPSTAN); elif test -x vendor/bin/phpstan; then set -- vendor/bin/phpstan; else set -- phpstan; fi; set -- "$$@" analyse --no-progress --level=8 $(SRC_DIR); echo "$$@"; "$$@"


.FORCE:
