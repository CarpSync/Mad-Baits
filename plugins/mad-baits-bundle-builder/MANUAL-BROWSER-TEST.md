# MadBaits Bundle Manager — staging browser tests

Run these on a staging shop after this plugin build is installed. Do not run them on production.

Admin screen: `/wp-admin/admin.php?page=madbaits-bundles`

Use a shop manager or administrator. A subscriber must not see the MadBaits menu.

Mark each box when the expected result happens.

## A. Legacy 10kg deal untouched

Setup: Pick the existing 10kg boilie deal. Do not open it in MadBaits → Bundles before this test.

Action:

1. Open the product page on desktop.
2. Make the usual choices and add the bundle to the basket.
3. Open the basket, mini-basket, and checkout.
4. Place a test order if checkout is available.

Expected:

- The builder, button text, and price match the current live deal.
- The basket price is the existing bundle price, not a percentage off the chosen baits.
- Checkout shows the same price.
- The order line is one bundle, with the choices listed on that line.

- [ ] Pass
- [ ] Fail

## B. Legacy 20kg deal untouched

Setup: Pick the existing 20kg deal. Do not edit it first.

Action: Repeat the product page, basket, and checkout checks from test A.

Expected: The 20kg choices and price are unchanged.

- [ ] Pass
- [ ] Fail

## C. Existing bundle price-only edit

Setup: MadBaits → Bundles. Open the 10kg deal from test A.

Action:

1. Change only the bundle price. Leave ranges, sizes, and quantity alone.
2. Save Changes.
3. Reload the product page and add the same choices as before.

Expected:

- The editor warns that this bundle is already on the website.
- The customer choices on the product page are the original slots, not ten new “bag” steps.
- The new price is charged.
- No extra helper badge appears above the builder.

- [ ] Pass
- [ ] Fail

## D. New 10kg / 10-bag £74.99 mix-and-match

Setup: At least two in-stock bait choices in the ticked ranges and sizes. The same bait may be chosen more than once.

Action:

1. MadBaits → Bundles → Create Bundle.
2. Name: `10kg Boilie Bundle`.
3. Mix & Match. Customer chooses 10 bags.
4. Tick Strawberry, The Nutz, and Monster Crab, or the closest live ranges.
5. Tick 15mm and 18mm.
6. Fixed bundle price `74.99`.
7. Save & Activate.
8. Open the new product page. Choose 10 bags. Add to basket.

Expected:

- The editor shows a live count such as “24 eligible product variations”, with product names rather than IDs.
- The preview reads “Choose any 10 bags” and “Customer pays: £74.99”.
- The product page can be completed with exactly 10 choices.
- The basket line is £74.99.

- [ ] Pass
- [ ] Fail

## E. New 20-bag bundle

Setup: Same ranges as test D.

Action: Create a mix-and-match bundle, exact quantity 20, fixed price of the usual 20kg price, Save & Activate. Add it from the product page.

Expected: The builder asks for 20 choices. The basket uses the price you entered.

- [ ] Pass
- [ ] Fail

## F. Duplicate

Setup: Use the bundle from test D.

Action: On the edit screen, click Duplicate Bundle.

Expected: A new draft named `10kg Boilie Bundle Copy`. It keeps the ranges, quantity, price, and button text. It is not on the shop.

- [ ] Pass
- [ ] Fail

## G. Disable and re-enable

Setup: Use an active bundle from test D.

Action:

1. Click Disable. Try to open the product URL while logged out.
2. Back in MadBaits → Bundles, click Enable.
3. Add the bundle to the basket again.

Expected:

- While disabled, the product is not in the catalogue and cannot be added, including with a direct `?add-to-cart=` link.
- After enable, the same bundle can be bought at the same price.

- [ ] Pass
- [ ] Fail

## H. Scheduled bundle

Setup: Duplicate the 10-bag bundle.

Action:

1. Set the start date to tomorrow. Save & Activate.
2. While logged out, look at the shop and open the product URL directly. Try `?add-to-cart=` as well.
3. Edit the bundle, clear the dates, and save.

Expected:

- Before the start date the badge says Scheduled. The product is absent from the catalogue.
- The direct URL does not add it to the basket.
- After the dates are cleared and it is saved active, it can be bought.

- [ ] Pass
- [ ] Fail

## I. Out-of-stock variation

Setup: A new mix-and-match bundle that includes one bait you can set out of stock.

Action:

1. Confirm that bait is listed in the editor.
2. Set that variation out of stock in WooCommerce → Products.
3. Reload the bundle product page.

Expected: The out-of-stock bait is not offered. Other in-stock baits still are. The bundle does not keep its own stock number.

- [ ] Pass
- [ ] Fail

## J. Exact quantity validation

Setup: The active 10-bag bundle.

Action: On the product page, choose 9 bags and try to add it. Then choose 10 and add it.

Expected: 9 is refused with “Choose exactly 10 bags.” 10 is added.

- [ ] Pass
- [ ] Fail

## K. Invalid activation validation

Setup: Create Bundle.

Action: Try Save & Activate with each of these, one at a time:

- empty name
- no ranges, products, or sizes ticked
- bundle price left blank
- start date after the end date

Expected: The bundle stays a draft. Messages are plain English, for example “Choose at least one bait range before activating this bundle.”

- [ ] Pass
- [ ] Fail

## L. Fixed price

Setup: The £74.99 bundle from test D, with 10 choices in the basket.

Action: Refresh the basket. Open the mini-basket and checkout.

Expected: Every screen shows £74.99 for quantity 1. Refreshing does not change it.

- [ ] Pass
- [ ] Fail

## M. 10% discount

Setup: Create a mix-and-match bundle whose choices are real priced products. Pricing: percentage discount, 10. Save & Activate.

Action: Choose products whose catalogue prices you know. Add the bundle. Note the sum of those prices.

Expected: Basket unit price is that sum minus 10%. Refresh the basket and the price stays the same. If a chosen product has no price, the bundle is refused instead of becoming £0.

- [ ] Pass
- [ ] Fail

## N. £10 discount

Setup: Same as M, but amount off £10.

Action: Add one bundle.

Expected: Basket unit price is the sum of the chosen products minus £10, and not less than £0. Refreshing does not take another £10 off.

- [ ] Pass
- [ ] Fail

## O. Cart refresh and recalculation

Setup: Leave the 10% bundle in the basket.

Action: Update the basket, open the mini-basket, leave the site, and come back so the session restores. Change the cart quantity and change it back to 1.

Expected: The unit price is the same after every refresh. It does not shrink a second time.

- [ ] Pass
- [ ] Fail

## P. Quantity 2 of the same bundle

Setup: The fixed £74.99 bundle and the £10-off bundle, in separate checks.

Action: Set the basket quantity to 2 for each, one at a time.

Expected:

- Fixed bundle line is £149.98.
- £10-off line is two times the one-bundle discounted price, so £20 is taken off the pair, not £10 once and not £10 again on refresh.

- [ ] Pass
- [ ] Fail

## Q. Coupon interaction

Setup: A 10% WooCommerce coupon. Basket contains the £74.99 bundle at quantity 1.

Action: Apply the coupon. Remove it. Apply it again. Also try it on the 10% bundle.

Expected:

- The coupon discounts the bundle price once.
- Removing and applying it again returns to the same totals.
- The bundle’s own 10% is not applied a second time on top of itself when the basket refreshes.

- [ ] Pass
- [ ] Fail

## R. Checkout

Setup: Basket from test L or M, with a shipping address the staging shop accepts.

Action: Complete checkout with a test payment method.

Expected: Checkout total matches the basket. Placing the order succeeds. The legacy 10kg deal can still be checked out on its own.

- [ ] Pass
- [ ] Fail

## S. Order admin line-item choices

Setup: The order from test R.

Action: Open the order in WooCommerce → Orders.

Expected: The bundle is one line at the price that was paid. The customer’s bait choices are listed on that line and are readable. A legacy order still shows its original choices.

- [ ] Pass
- [ ] Fail

## T. Mobile builder

Setup: A phone-width browser, or device toolbar at about 390px. Use the new 10-bag bundle and the untouched 10kg deal.

Action: Step through the builder, pick the required choices, and add each bundle.

Expected: The new bundle can be finished on a phone. The old 10kg deal still uses its current steps and can be added. Buttons and validation text stay on screen.

- [ ] Pass
- [ ] Fail

## Admin pass while you are there

On a laptop at about 1280px wide:

- [ ] List cards show image, name, status, type, quantity, price, eligible count, and updated date.
- [ ] Search and the status and type filters can be used together.
- [ ] Eligible-product search, Select all, and Clear work.
- [ ] The preview updates when the name, quantity, price, helper text, or image changes.
- [ ] Pricing mode, dates, and rules switch without a console error.
- [ ] Delete asks for confirmation before the bundle is moved to the bin.
- [ ] Restore the original customer choices, when offered, puts a legacy deal back.
- [ ] No owner-facing label shows a raw ID or a meta field name.
