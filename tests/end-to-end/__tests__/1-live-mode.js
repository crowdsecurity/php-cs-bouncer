/* eslint-disable no-undef */
const {
    CURRENT_IP,
    FORCED_TEST_FORWARDED_IP,
    STREAM_MODE,
    GEOLOC_ENABLED,
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

describe(`Live mode run`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
    });

    it("Should have correct settings", async () => {
        if (STREAM_MODE) {
            const errorMessage = `Stream mode must be disabled for this test`;
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
        if (GEOLOC_ENABLED) {
            const errorMessage = "Geolocation MUST be disabled to test this.";
            console.error(errorMessage);
            throw new Error(errorMessage);
        }
    });

    it("Should display the homepage with no remediation", async () => {
        await publicHomepageShouldBeAccessible();
    });

    it("Should display a captcha wall with mentions", async () => {
        await captchaIpForSeconds(15 * 60, FORCED_TEST_FORWARDED_IP ? FORCED_TEST_FORWARDED_IP : CURRENT_IP);
        await publicHomepageShouldBeCaptchaWallWithMentions();
    });

    it("Should display a ban wall", async () => {
        await banIpForSeconds(15 * 60, FORCED_TEST_FORWARDED_IP ? FORCED_TEST_FORWARDED_IP : CURRENT_IP);
        await publicHomepageShouldBeBanWall();
    });

    it("Should display back the homepage with no remediation", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeAccessible();
    });

    it("Should fallback to the selected remediation for unknown remediation", async () => {
        await removeAllDecisions();
        await addDecision(FORCED_TEST_FORWARDED_IP ? FORCED_TEST_FORWARDED_IP : CURRENT_IP, "mfa", 15 * 60);
        await wait(1000);
        await publicHomepageShouldBeCaptchaWall();
    });
});
