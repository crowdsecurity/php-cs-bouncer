Contribute to this library
==========================

### Dev environment

- ✅ Docker dev environment (Dockerized Crowdsec, Redis, Memcached, PHP)
- ✅ Continuous Integration (CI, includes Integration Tests and Super Linter)
- ✅ Integration tests (with TDD)
- ✅ Documented (Static documentation, PHP Doc)
- ✅ Continuous Delivery (CD)

Guidelines
~~~~~~~~~~

-  TDD (with PHP Unit)
-  CI (via Github actions): run all tests over each PHP versions,
   generate PHP doc, Publish all docs.
-  Git workflow : Git Flow (as it's a software)
   (https://github.com/nvie/gitflow#creating-featurereleasehotfixsupport-branches)
-  Versioning sytem: semver
-  PHP Documentation
-  Code coverage
-  Coding standards using default php-cs-fixer configuration:
   https://cs.symfony.com/

Generate PHP Doc
----------------

.. code-block:: sh

   docker-compose run --rm phpdoc -d . --setting="guides.enabled=true" --template="./.phpdoc-template"

Lint code
---------

You can run "Super linter" locally:

.. code-block:: sh

   docker pull github/super-linter:latest
   docker run -e "FILTER_REGEX_INCLUDE=/tmp/lint/src/.*" -e RUN_LOCAL=true -v ${PWD}:/tmp/lint github/super-linter

Full details here: https://github.com/github/super-linter/blob/master/docs/run-linter-locally.md

Git Flow
--------

More info here: https://danielkummer.github.io/git-flow-cheatsheet/

To start a new feature:

.. code-block:: sh

   git flow feature start <name>

To push the feature:

.. code-block:: sh

   git flow feature publish <name>

To pull the feature:

.. code-block:: sh
   git flow feature pull setup

Important: As we use pull requests, we just don't use `git flow feature finish <name>` and we just remove the local branch when the PR is merged.

To create a release:

.. code-block:: sh
   git flow release start vx.x.x
   git flow release publish vx.x.x
   git flow release finish vx.x.x

https://stackoverflow.com/questions/55706856/proper-way-to-use-gitflow-with-pull-requests

And more:

.. code-block:: sh
   usage: git flow feature [list] [-v]
       git flow feature start [-F] <name> [<base>]
       git flow feature finish [-rFk] <name|nameprefix>
       git flow feature publish <name>
       git flow feature track <name>
       git flow feature diff [<name|nameprefix>]
       git flow feature rebase [-i] [<name|nameprefix>]
       git flow feature checkout [<name|nameprefix>]
       git flow feature pull <remote> [<name>]

Github Cli
----------

To check if code works for each version:

.. code-block:: bash

   gh pr checks

To create a new release:

.. code-block:: bash

   gh release create (...)

Tests with various php versions
-------------------------------

.. code-block:: bash

   ./tests-local.sh # This use PHP 7.2.
   ./tests-local-php7.3.sh
   ./tests-local-php7.4.sh
   ./tests-local-php8.0.sh # These tests are ready but currenly the lib is not compatible.

TODO P2 Improve this doc.