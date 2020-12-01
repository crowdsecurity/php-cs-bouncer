Run tests
---------

We explain here how to run the tests.

.. _1-build-crowdesc-docker-image:

1) Build crowdesc docker image
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

First we need to create the crowdsec docker image.

.. code-block:: sh

   git clone git@github.com:crowdsecurity/crowdsec.git && cd $_ && docker build -t crowdsec . && cd .. && rm -rf ./crowdsec

.. _2-install-composer-dependencies:

2) Install composer dependencies
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The install composer dependencies:

.. code-block:: sh

   docker-compose run --rm composer install

.. _3-run-tests:

3) Run tests
~~~~~~~~~~~~

Finally, run the tests.sh file:

.. code-block:: sh

   ./test.sh