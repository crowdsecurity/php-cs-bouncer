/* eslint-disable no-undef */
const {
    GEOLOC_ENABLED,
    FORCED_TEST_FORWARDED_IP,
    GEOLOC_BAD_COUNTRY,
    STREAM_MODE,
    JAPAN_IP
} = require("../utils/constants");

const {
    publicHomepageShouldBeBanWall,
    publicHomepageShouldBeAccessible,
    banIpForSeconds,
    removeAllDecisions,
    wait,
} = require("../utils/helpers");
const { addDecision } = require("../utils/watcherClient");

describe(`Live mode run with geolocation`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
    });

    it("Should have correct settings", async () => {
        if (STREAM_MODE) {
            const errorMessage = `Stream mode must be disabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (!GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be enabled to test this.";
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        // Test with a Japan IP
        if (FORCED_TEST_FORWARDED_IP !== JAPAN_IP) {
            const errorMessage = `A forced test forwarded ip MUST be set and equals to '${JAPAN_IP}'."forced_test_forwarded_ip" setting was: ${FORCED_TEST_FORWARDED_IP}`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should bypass a clean IP with a clean country", async () => {
        await publicHomepageShouldBeAccessible();
    });

    it("Should ban a bad IP (ban) with a clean country", async () => {
        await banIpForSeconds(15 * 60, FORCED_TEST_FORWARDED_IP);
        await publicHomepageShouldBeBanWall();
    });

    it("Should ban a clean IP with a bad country (ban)", async () => {
        await removeAllDecisions();
        await addDecision(GEOLOC_BAD_COUNTRY, "ban", 15 * 60, "Country");
        await wait(1000);
        await publicHomepageShouldBeBanWall();
    });

    it("Should ban a bad IP (ban) with a bad country (captcha)", async () => {
        await removeAllDecisions();
        await addDecision(GEOLOC_BAD_COUNTRY, "captcha", 15 * 60, "Country");
        await addDecision(FORCED_TEST_FORWARDED_IP, "ban", 15 * 60);
        await wait(1000);
        await publicHomepageShouldBeBanWall();
    });
});
