/* eslint-disable no-undef */
const { JAPAN_IP, FRANCE_IP } = require("../utils/constants");

const { removeAllDecisions, runGeolocationTest } = require("../utils/helpers");

describe(`Geolocation standalone run`, () => {
    beforeAll(async () => {
        await removeAllDecisions();
    });

    it("Should get JP", async () => {
        await runGeolocationTest(JAPAN_IP, false);
        await expect(page).toMatchText(/Country: JP/);
    });

    it("Should get FR", async () => {
        await runGeolocationTest(FRANCE_IP, false);
        await expect(page).toMatchText(/Country: FR/);
    });

    it("Should get FR and FR as it will be saved in session", async () => {
        await runGeolocationTest(FRANCE_IP, true);
        await expect(page).toMatchText(/Country: FR/);
        await runGeolocationTest(JAPAN_IP, true);
        await expect(page).toMatchText(/Country: FR/);
    });
});
