/* eslint-disable no-undef */
const { publicHomepageShouldBeAccessible } = require("../utils/helpers");

describe(`Should not display errors`, () => {
    it("Should not display error", async () => {
        await publicHomepageShouldBeAccessible();
        await expect(page).not.toHaveText("body", "Fatal error");
    });
});
