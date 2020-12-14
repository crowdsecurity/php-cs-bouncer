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

## How to generate PHP Doc

```bash
docker-compose run --rm app vendor/bin/phpdoc-md
```

## The git workflow we use

We use the git workflow [Github Flow](https://guides.github.com/introduction/flow/).

### Cheatsheet

#### New feature

```bash
git checkout -b new-feature # the name is not important now.
git commit # as mush as necessary.
git branch -m <name-of-the-branch> # to rename the branch to what has really be done
git push -u origin <name-of-the-branch>
gh pr create --fill
```

#### New release

```bash
git describe --tags # to verify what is the current tag
gh release create --draft vx.x.x --title vx.x.x
```