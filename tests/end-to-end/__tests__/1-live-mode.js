/* eslint-disable no-undef */
const {
    CURRENT_IP,
    FORCED_TEST_IP,
    STREAM_MODE,
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
        if (STREAM_MODE) {
            const errorMessage = `Stream mode must be disabled for this test`;
            console.error(errorMessage);
            fail(errorMessage);
        }
        if (FORCED_TEST_IP !== null) {
            const errorMessage = `A forced test ip MUST NOT be set."forced_test_ip" setting was: ${FORCED_TEST_IP}`;
            console.error(errorMessage);
            fail(errorMessage);
        }
        await removeAllDecisions();
    });

    it("Should display the homepage with no remediation", async () => {
        await publicHomepageShouldBeAccessible();
    });

    it("Should display a captcha wall with mentions", async () => {
        await captchaIpForSeconds(15 * 60, CURRENT_IP);
        await publicHomepageShouldBeCaptchaWallWithMentions();
    });

    it("Should display a ban wall", async () => {
        await banIpForSeconds(15 * 60, CURRENT_IP);
        await publicHomepageShouldBeBanWall();
    });

    it("Should display back the homepage with no remediation", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeAccessible();
    });

    it("Should fallback to the selected remediation for unknown remediation", async () => {
        await removeAllDecisions();
        await addDecision(CURRENT_IP, "mfa", 15 * 60);
        await wait(1000);
        await publicHomepageShouldBeCaptchaWall();
    });
});
