/**
 * Internal dependencies.
 */

import { CustomerFlow, StoreOwnerFlow } from '../utils/flows.js';
import { setCheckbox, settingsPageSaveChanges } from '../utils/index.js';
import { createSimpleDepositProduct, createSimplePaymentPlanProduct, deletePaymentPlan, createPaymentPlan, deleteProduct } from '../deposits/deposits_components.js';
import { verifyAndUpdate } from '../utils/components.js';
import { DepositsCustomerFlow, DepositsStoreOwnerFlow } from '../deposits/deposits_flows';

/**
 * External dependencies.
 */
const config = require( 'config' );
const simpleDepositProductTitle = config.products.simpleDeposit.name;

describe( 'Shop Page Tests', () => {

	let simpleDepositsProductId,
		simplePaymentPlanProductId;

	beforeAll( async () => {
		await StoreOwnerFlow.login();

		// Change the WC Ajax setting.
		await StoreOwnerFlow.openSettings( 'products' );
		await setCheckbox( '#woocommerce_enable_ajax_add_to_cart' );
		await settingsPageSaveChanges();

		// Create new Deposit products.
		await createPaymentPlan();
		simpleDepositsProductId = await createSimpleDepositProduct();
		simplePaymentPlanProductId = await createSimplePaymentPlanProduct();

		// Edit the product to force Deposits.
		await DepositsStoreOwnerFlow.editProduct( simpleDepositsProductId );
		await page.waitForSelector( '.wc-deposits-tab' );
		await page.click( '.wc-deposits-tab' );
		await page.waitForSelector( '#_wc_deposit_enabled' );
		await page.select( '#_wc_deposit_enabled', 'forced' );
		await page.waitForSelector( '#_wc_deposit_type' );
		await page.select( '#_wc_deposit_type', 'percent' );
		await expect( page ).toFill( '#_wc_deposit_amount', '50' );
		await page.select( '#_wc_deposit_selected_type', 'deposit' );
		await verifyAndUpdate();

		await StoreOwnerFlow.logout();
		await CustomerFlow.login();
	} );

	it( 'Can add forced deposit products to the cart via AJAX if AJAX enabled.', async () => {
		await CustomerFlow.goToShop();
		// look for the product matching the post ID
		await expect( page ).toMatchElement( `li.post-${ simpleDepositsProductId }`, { text: 'Add to cart' } );
		await CustomerFlow.addToCartFromShopPage( simpleDepositProductTitle );
		await page.waitForSelector( '.added_to_cart' );
	} );

	it( 'Should not be able to directly add Payment Plan products to cart on Shop page.', async () => {
		await CustomerFlow.goToShop();
		// Look for the product matching the post ID. We should not see "Add to Cart".
		await expect( page ).toMatchElement( `li.post-${ simplePaymentPlanProductId }`, { text: 'Select options' } );
	} );

	afterAll( async () => {
		await DepositsCustomerFlow.logout();
		await StoreOwnerFlow.login();
		await StoreOwnerFlow.deleteProduct( simpleDepositsProductId );
		await StoreOwnerFlow.deleteProduct( simplePaymentPlanProductId );
		await deletePaymentPlan();
	} );
} );
