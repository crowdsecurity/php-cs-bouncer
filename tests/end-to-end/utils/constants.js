const { PHP_URL } = process.env;

const PUBLIC_URL =
    "/my-own-modules/crowdsec-php-lib/scripts/public/protected-page.php";
const FORCED_TEST_FORWARDED_IP =
    process.env.FORCED_TEST_FORWARDED_IP !== ""
        ? process.env.FORCED_TEST_FORWARDED_IP
        : null;
const GEOLOC_ENABLED = process.env.GEOLOC_ENABLED === "true";
const STREAM_MODE = process.env.STREAM_MODE === "true";
const GEOLOC_BAD_COUNTRY = "JP";
const JAPAN_IP = "210.249.74.42";
const FRANCE_IP = "78.119.253.85";
const { LAPI_URL_FROM_PLAYWRIGHT } = process.env;
const { BOUNCER_KEY } = process.env;
const WATCHER_LOGIN = "watcherLogin";
const WATCHER_PASSWORD = "watcherPassword";
const { DEBUG } = process.env;
const { TIMEOUT } = process.env;
const { CURRENT_IP } = process.env;
const { PROXY_IP } = process.env;
const { AGENT_TLS_PATH } = process.env;
const AGENT_CERT_PATH = `${AGENT_TLS_PATH}/agent.pem`;
const AGENT_KEY_PATH = `${AGENT_TLS_PATH}/agent-key.pem`;
const CA_CERT_PATH = `${AGENT_TLS_PATH}/ca-chain.pem`;

module.exports = {
    PHP_URL,
    BOUNCER_KEY,
    CURRENT_IP,
    DEBUG,
    FORCED_TEST_FORWARDED_IP,
    LAPI_URL_FROM_PLAYWRIGHT,
    PROXY_IP,
    PUBLIC_URL,
    TIMEOUT,
    WATCHER_LOGIN,
    WATCHER_PASSWORD,
    GEOLOC_ENABLED,
    GEOLOC_BAD_COUNTRY,
    STREAM_MODE,
    JAPAN_IP,
    FRANCE_IP,
    AGENT_CERT_PATH,
    AGENT_KEY_PATH,
    CA_CERT_PATH,
};
