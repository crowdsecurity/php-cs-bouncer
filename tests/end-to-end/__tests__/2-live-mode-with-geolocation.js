/* eslint-disable no-undef */
const {
    CURRENT_IP,
    GEOLOC_ENABLED,
    GEOLOC_TEST_IP,
    GEOLOC_BAD_COUNTRY,
} = require("../utils/constants");

const {
    publicHomepageShouldBeBanWall,
    publicHomepageShouldBeCaptchaWallWithMentions,
    publicHomepageShouldBeAccessible,
    publicHomepageShouldBeCaptchaWall,
    banIpForSeconds,
    captchaIpForSeconds,
    removeAllDecisions,
    wait,
} = require("../utils/helpers");
const { addDecision } = require("../utils/watcherClient");

describe(`Live mode run with geolocation`, () => {
    beforeAll(async () => {
        if (!GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be enabled to test this.";
            console.error(errorMessage);
            fail(errorMessage);
        }
        await removeAllDecisions();
    });

    it("Should bypass a clean IP with a clean country", async () => {
        await publicHomepageShouldBeAccessible();
    });

    it("Should ban a bad IP (ban) with a clean country", async () => {
        await banIpForSeconds(15 * 60, CURRENT_IP);
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
        await addDecision(CURRENT_IP, "ban", 15 * 60);
        await wait(1000);
        await publicHomepageShouldBeBanWall();
    });
});
