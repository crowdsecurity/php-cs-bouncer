Useful tips
===========

We tried to list here every useful tips to develop or use this libary.

Decision fields
~~~~~~~~~~~~~~~

This is how look a decision:

.. code-block:: json

    {
        "duration":"19h10m33.3465483s",
        "end_ip":16909060,
        "id":1,
        "origin":"cscli",
        "scenario":"manual 'captcha' from '25b9f1216f9344b780963bd281ae5573UIxCiwc74i2mFqK4'",
        "scope":"Ip",
        "start_ip":16909060,
        "type":"captcha",
        "value":"1.2.3.4"
    }

Call LAPI with CURL
~~~~~~~~~~~~~~~~~~~

As a bouncer:

.. code-block:: sh

    docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions | jq
    docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions?ip=1.2.3.4 | jq
    docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions/stream?startup=true | jq

As a watcher:

.. code-block:: sh

    docker-compose run --rm curl \
    -X POST "http://crowdsec:8080/v1/alerts" \
    -H  "accept: application/json" -H  "Content-Type: application/json" -H  "Autorization: Bearer " \
    -d "[{\"capacity\":0,\"decisions\":[{\"duration\":\"12h\",\"end_ip\":16909060,\"origin\":\"cscli\",\"scenario\":\"setup captcha on single IP for 12h for PHPUnit tests\",\"scope\":\"Ip\",\"start_ip\":16909060,\"type\":\"captcha\",\"value\":\"1.2.3.4\"},{\"duration\":\"24h\",\"end_ip\":16909060,\"origin\":\"cscli\",\"scenario\":\"setup ban on range 1.2.3.4 to 1.2.3.7 for 24h for PHPUnit tests\",\"scope\":\"Range\",\"start_ip\":16909063,\"type\":\"ban\",\"value\":\"1.2.3.4/30\"}],\"events\":[],\"events_count\":1,\"labels\":null,\"leakspeed\":\"0\",\"message\":\"setup for PHPUnit tests\",\"scenario\":\"setup for PHPUnit tests\",\"scenario_hash\":\"\",\"scenario_version\":\"\",\"simulated\":false,\"source\":{\"scope\":\"Range\",\"value\":\"1.2.3.4/30\"},\"start_at\":\"2020-11-29T14:55:10Z\",\"stop_at\":\"2021-11-29T14:55:10Z\"}]"

Play with ``cscli``
~~~~~~~~~~~~~~~~~~~

.. code-block:: sh

    docker-compose exec crowdsec sh

    docker-compose exec crowdsec /usr/local/bin/cscli decisions add \
        --ip 1.2.3.4 --duration 12h --type captcha -o json

    docker-compose exec crowdsec /usr/local/bin/cscli decisions add \
        --range 1.2.3.4/30 --duration 24h --type ban -o json # [1.2.3.4-1.2.3.7]

Manually create a Watcher JWT
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Create the watcher:

.. code-block:: sh

    docker-compose exec crowdsec cscli machines add PhpUnitTestMachine --password PhpUnitTestMachinePassword > /dev/null 2>&1

Use httpie inside crowdsec container to play with LAPI
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: sh

    docker-compose crowdsec sh

Install httpie
^^^^^^^^^^^^^^

.. code-block:: sh

    apk --no-cache add httpie

Make calls as a bouncer:
^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: sh

    cscli bouncers add bouncer-php-library -o raw > .bouncer-key
    http GET http://localhost:8085/v1/decisions\?ip\=1.2.3.4 'X-Api-Key: `cat .bouncer-key`'

Make calls as a watcher:
^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: sh

    http POST http://localhost:8085/v1/watchers/login machine_id=PhpUnitTestMachine password=PhpUnitTestMachinePassword
