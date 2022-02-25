/* eslint-disable no-undef */
const {
    CURRENT_IP,
    FORCED_TEST_IP,
    STREAM_MODE,
} = require("../utils/constants");

const {
    publicHomepageShouldBeAccessible,
    publicHomepageShouldBeCaptchaWall,
    captchaIpForSeconds,
    removeAllDecisions,
    runCacheAction,
} = require("../utils/helpers");

describe(`Stream mode run`, () => {
    beforeAll(async () => {
        if (!STREAM_MODE) {
            const errorMessage = `Stream mode must be enabled for this test`;
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
        await runCacheAction("warm-up");
        await publicHomepageShouldBeAccessible();
    });

    it("Should still bypass as cache has not been refreshed", async () => {
        await captchaIpForSeconds(15 * 60, CURRENT_IP);
        await publicHomepageShouldBeAccessible();
    });

    it("Should display a captcha wall after cache refresh", async () => {
        await runCacheAction("refresh");
        await publicHomepageShouldBeCaptchaWall();
    });

    it("Should still display a captcha wall as cache has not been refreshed", async () => {
        await removeAllDecisions();
        await publicHomepageShouldBeCaptchaWall();
    });

    it("Should bypass after cache refresh", async () => {
        await runCacheAction("refresh");
        await publicHomepageShouldBeAccessible();
    });
});
