Contribute to this library
==========================

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

   docker-compose run phpdoc -d . --setting="guides.enabled=true" --template="./.phpdoc-template"

Lint code
---------

.. code-block:: sh

   composer lintfix
   composer phpstan

Also, you can run "Super linter" locally:

.. code-block:: sh

   docker pull github/super-linter:latest
   docker run -e "FILTER_REGEX_INCLUDE=/tmp/lint/src/.*" -e RUN_LOCAL=true -v ${PWD}:/tmp/lint github/super-linter

Full details here: https://github.com/github/super-linter/blob/master/docs/run-linter-locally.md

Git Flow
--------

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


TODO P2 Improve this doc.