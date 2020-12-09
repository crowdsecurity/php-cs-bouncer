## Useful tips

We tried to list here every useful tip to use this library.

### use the CURL container to call LAPI

#### As a bouncer:

```bash
docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions | jq
docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions?ip=1.2.3.4 | jq
docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions/stream?startup=true | jq
docker-compose run --rm curl -H "X-Api-Key: `cat .bouncer-key`" http://crowdsec:8080/v1/decisions/stream | jq
```

#### As a watcher:

```bash
docker-compose run --rm curl \
-X POST "http://crowdsec:8080/v1/alerts" \
-H  "accept: application/json" -H  "Content-Type: application/json" -H  "Autorization: Bearer " \
-d "[{\"capacity\":0,\"decisions\":[{\"duration\":\"12h\",\"end_ip\":16909060,\"origin\":\"cscli\",\"scenario\":\"setup captcha on single IP for 12h for PHPUnit tests\",\"scope\":\"Ip\",\"start_ip\":16909060,\"type\":\"captcha\",\"value\":\"1.2.3.4\"},{\"duration\":\"24h\",\"end_ip\":16909060,\"origin\":\"cscli\",\"scenario\":\"setup ban on range 1.2.3.4 to 1.2.3.7 for 24h for PHPUnit tests\",\"scope\":\"Range\",\"start_ip\":16909063,\"type\":\"ban\",\"value\":\"1.2.3.4/30\"}],\"events\":[],\"events_count\":1,\"labels\":null,\"leakspeed\":\"0\",\"message\":\"setup for PHPUnit tests\",\"scenario\":\"setup for PHPUnit tests\",\"scenario_hash\":\"\",\"scenario_version\":\"\",\"simulated\":false,\"source\":{\"scope\":\"Range\",\"value\":\"1.2.3.4/30\"},\"start_at\":\"2020-11-29T14:55:10Z\",\"stop_at\":\"2021-11-29T14:55:10Z\"}]"
```

### Play with `cscli`

```bash
docker-compose exec crowdsec sh

# add captcha to ip 1.2.3.4 for 12h
docker-compose exec crowdsec cscli decisions add --ip 1.2.3.4 --duration 12h --type captcha -o json

# ban range 1.2.3.4 to 1.2.3.7 for 24h
docker-compose exec crowdsec cscli decisions add --range 1.2.3.4/30 --duration 24h --type ban -o json
```

### Manually create a Watcher JWT

Create the watcher:

```bash
docker-compose exec crowdsec cscli machines add PhpUnitTestMachine --password PhpUnitTestMachinePassword
```

### HTTPIE

Use httpie inside crowdsec container to play with LAPI

```bash

# use the crowdsec container shell
docker-compose crowdsec sh

# install httpie
apk --no-cache add httpie

# add a bouncer
cscli bouncers add my-bouncer -o raw > .bouncer-key

# make a call a a bouncer
http GET http://localhost:8085/v1/decisions\?ip\=1.2.3.4 'X-Api-Key: `cat .bouncer-key`'

# make a call a a watcher
http POST http://localhost:8085/v1/watchers/login machine_id=PhpUnitTestMachine password=PhpUnitTestMachinePassword
```