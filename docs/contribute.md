# Contribute to this library

Here is the development environment for this library:

- Docker dev environment (Dockerized Crowdsec, Redis, Memcached, PHP)
- Continuous Integration (CI, includes Integration Tests and Super Linter)
- Integration tests (with TDD)
- Documented (Static documentation, PHP Doc)

## The guidelines

-  We use TDD to code the library, with PHP Unit
-  CI via Github actions: run all tests over each PHP versions
-  Git workflow: Git Flow [as it's a software](https://github.com/nvie/gitflow#creating-featurereleasehotfixsupport-branches)
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
docker-compose run --rm phpdoc -d . --setting="guides.enabled=true" --template="./.phpdoc-template"
```

## The git workflow we use

More info here: https://danielkummer.github.io/git-flow-cheatsheet/

To start a new feature:

```bash
git flow feature start <name>
```

To push the feature to Github:

```bash
git flow feature publish <name>
```

To pull the feature from Github:

```bash
git flow feature pull <name>
```

Important: As we use pull requests, we just don't use `git flow feature finish <name>` and we just remove the local branch when the PR is merged. More info [here](https://stackoverflow.com/questions/55706856/proper-way-to-use-gitflow-with-pull-requests).

To create a release:

```bash
git flow release start vx.x.x
git flow release publish vx.x.x
git flow release finish vx.x.x
```

Then create a new release manually in Github or use `gh release create (...)` if you use **gh** cli.

And more...

```bash
usage: git flow feature [list] [-v]
   git flow feature start [-F] <name> [<base>]
   git flow feature finish [-rFk] <name|nameprefix>
   git flow feature publish <name>
   git flow feature track <name>
   git flow feature diff [<name|nameprefix>]
   git flow feature rebase [-i] [<name|nameprefix>]
   git flow feature checkout [<name|nameprefix>]
   git flow feature pull <remote> [<name>]
```