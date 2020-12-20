# Contribute to this library

Here is the development environment for this library:

- Docker dev environment (Dockerized Crowdsec, Redis, Memcached, PHP)
- Continuous Integration (CI, includes Integration Tests and Super Linter)
- Integration tests (with TDD)
- Documented (Static documentation, PHP Doc)

## The guidelines

-  We use TDD to code the library, with PHP Unit
-  CI via Github actions: run all tests over each PHP versions
-  Git workflow: [Github Flow](https://guides.github.com/introduction/flow/)
-  PHP Source fully documented
-  Versioning system: Semver
-  Code coverage (not now)
-  Coding standards using [php-cs-fixer](https://cs.symfony.com/) configuration in **.php_cs**

## Run tests

First of all, install composer dependencies:

```bash
   ./composer-install.sh
```
Then run tests:
```bash
./tests-local.sh # This will test with PHP 7.2 version
```

Alternatively, you can tests with various php versions:

```bash
   ./tests-local-php7.3.sh
   ./tests-local-php7.4.sh
   ./tests-local-php8.0.sh
```
## How to lint the code

You can run "Super linter" locally:

```bash
docker pull github/super-linter:latest
docker run -e "FILTER_REGEX_INCLUDE=/tmp/lint/src/.*" -e RUN_LOCAL=true -v ${PWD}:/tmp/lint github/super-linter
```

Full details here: https://github.com/github/super-linter/blob/master/docs/run-linter-locally.md

To autolint with phpcs-fixer:

```bash
composer install --working-dir=tools/php-cs-fixer
PHP_CS_FIXER_IGNORE_ENV=1 tools/php-cs-fixer/vendor/bin/php-cs-fixer fix
```

## How to generate PHP Doc

```bash
docker-compose run --rm app vendor/bin/phpdoc-md
```

### Git workflow cheat sheet

We use the git workflow [Github Flow](https://guides.github.com/introduction/flow/).

#### New feature

```bash
git checkout -b <basic-name> # the name is not important now, you can type "new-features"
git commit # as mush as necessary.

PHP_CS_FIXER_IGNORE_ENV=1 tools/php-cs-fixer/vendor/bin/php-cs-fixer fix # fix coding standards
docker run -e "FILTER_REGEX_INCLUDE=/tmp/lint/src/.*" -e RUN_LOCAL=true -v ${PWD}:/tmp/lint github/super-linter # super linter local pass
./tests-local.sh # check tests are still OK
docker-compose run --rm app vendor/bin/phpdoc-md # Regenerate php doc

git branch -m <name-of-the-branch> # to rename the branch to what has really be done.
git push origin :<basic-name> && git push origin <name-of-the-branch> # Only if already pushed

gh pr create --fill
```

After the merge, don't forget to delete to branch.

#### New release

```bash
git describe --tags # to verify what is the current tag
gh release create --draft vx.x.x --title vx.x.x
```
