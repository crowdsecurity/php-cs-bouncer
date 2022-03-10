/* eslint-disable no-undef */
const {
    GEOLOC_ENABLED,
    FORCED_TEST_IP,
    GEOLOC_BAD_COUNTRY,
    STREAM_MODE,
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
        if (STREAM_MODE) {
            const errorMessage = `Stream mode must be disabled for this test`;
            console.error(errorMessage);
            fail(errorMessage);
        }
        if (!GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be enabled to test this.";
            console.error(errorMessage);
            fail(errorMessage);
        }
        // Test with a Japan IP
        if (FORCED_TEST_IP !== "210.249.74.42") {
            const errorMessage = `A forced test ip MUST be set and equals to '210.249.74.42'."forced_test_ip" setting was: ${FORCED_TEST_IP}`;
            console.error(errorMessage);
            fail(errorMessage);
        }
        await removeAllDecisions();
    });

    it("Should bypass a clean IP with a clean country", async () => {
        await publicHomepageShouldBeAccessible();
    });

    it("Should ban a bad IP (ban) with a clean country", async () => {
        await banIpForSeconds(15 * 60, FORCED_TEST_IP);
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
        await addDecision(FORCED_TEST_IP, "ban", 15 * 60);
        await wait(1000);
        await publicHomepageShouldBeBanWall();
    });
});
