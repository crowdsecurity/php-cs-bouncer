/* eslint-disable no-undef */
const { goToPublicPage } = require("../utils/helpers");

describe(`Should not display errors`, () => {
    it("Should display error (if settings ko or something wrong while bouncing)", async () => {
        await goToPublicPage();
        await expect(page).toHaveText("body", "Fatal error");
    });
});
