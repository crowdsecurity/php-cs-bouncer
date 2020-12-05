Run tests
---------

We explain here how to run the tests.

.. _1-build-crowdesc-docker-image:

1) Build crowdesc docker image
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

At this day, there is no crowdsec images on docker hub, so you have to build it by yourself.

TODO P3 when v1.0.0 will be release, get the latest stable version.

.. code-block:: sh
   git clone --branch v1.0.0-rc4 git@github.com:crowdsecurity/crowdsec.git .tmp-crowdsec \
      && docker build -t crowdsec:v1.0.0-rc4 ./.tmp-crowdsec \
      && rm -rf ./.tmp-crowdsec

.. _2-install-composer-dependencies:

2) Install composer dependencies
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The install composer dependencies:

.. code-block:: sh

   docker-composer run app composer install

.. _3-run-tests:

3) Run tests
~~~~~~~~~~~~

Finally, run the tests.sh file:

.. code-block:: sh

   ./test.sh