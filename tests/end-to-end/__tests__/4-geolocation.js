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

    it("Should call the database as we did not save result", async () => {
        await runGeolocationTest(FRANCE_IP, false, true);
        await expect(page).toMatchText(/Error message: The file/);
    });

    it("Should not call the GeoIp database as result is saved in cache", async () => {
        await runGeolocationTest(FRANCE_IP, true);
        await expect(page).toMatchText(/Country: FR/);
        await runGeolocationTest(FRANCE_IP, true, true);
        await expect(page).toMatchText(/Country: FR/);
    });
});
