Contribute to this library
--------------------------

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
================

.. code-block:: sh

   docker-compose run phpdoc -d . --setting="guides.enabled=true" --template="./.phpdoc-template"

Git Flow
--------

To check if code works for each version:

.. code-block:: bash

   gh pr checks

To create a new release:

.. code-block:: bash

   gh release create (...)

TODO P2 Improve this doc.