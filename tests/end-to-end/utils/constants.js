const { PHP_URL } = process.env;

const LAPI_URL_FROM_PHP = "http://crowdsec:8080";
const PUBLIC_URL = '/my-own-modules/crowdsec-php-lib/examples/auto-prepend/public/protected-page.php'
const { LAPI_URL_FROM_PLAYWRIGHT } = process.env;
const { BOUNCER_KEY } = process.env;
const WATCHER_LOGIN = "watcherLogin";
const WATCHER_PASSWORD = "watcherPassword";
const { DEBUG } = process.env;
const { TIMEOUT } = process.env;
const { CURRENT_IP } = process.env;
const { PROXY_IP } = process.env;

module.exports = {
    PHP_URL,
    BOUNCER_KEY,
    CURRENT_IP,
    DEBUG,
    LAPI_URL_FROM_PHP,
    LAPI_URL_FROM_PLAYWRIGHT,
    PROXY_IP,
    PUBLIC_URL,
    TIMEOUT,
    WATCHER_LOGIN,
    WATCHER_PASSWORD,
};
