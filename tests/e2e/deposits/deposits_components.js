/**
 * @format
 */

/**
 * Internal dependencies
 */
import { StoreOwnerFlow } from '../utils/flows';
import { clickTab } from '../utils/index';
import { verifyAndPublish } from '../utils/components';
import { DepositsStoreOwnerFlow } from '../deposits/deposits_flows';

const config = require( 'config' );
const simpleDepositProductName = config.get( 'products.simpleDeposit.name' );
const simplePaymentPlanProductName = config.get( 'products.simplePaymentPlan.name' );

/**
 * Create simple product and enable 50% Deposit.
 */
const createSimpleDepositProduct = async () => {
	// Go to "add product" page.
	await StoreOwnerFlow.openNewProduct();

	// Make sure we're on the add product page.
	await expect( page.title() ).resolves.toMatch( 'Add new product' );

	// Set product data.
	await clickTab( 'General' );
	await expect( page ).toFill( '#_regular_price', '10' );

	// Enable Deposits.
	await clickTab( 'Deposits' );
	await expect( page ).toSelect( '#_wc_deposit_enabled', 'optional' );
	await expect( page ).toSelect( '#_wc_deposit_type', 'percent' );
	await expect( page ).toFill( '#_wc_deposit_amount', '50' );

	// Set product title.
	await expect( page ).toFill( '#title', simpleDepositProductName );

	await verifyAndPublish();

	const simplePostId = await page.$( '#post_ID' );
	const simplePostIdValue = ( await ( await simplePostId.getProperty( 'value' ) ).jsonValue() );
	return simplePostIdValue;
};
/**
 * Create Payment Plan.
*/
const createPaymentPlan = async ( planName ) => {
	await DepositsStoreOwnerFlow.openPaymentPlansPage();
	await page.waitForSelector( '.submit' );
	if ( planName ) {
		await expect( page ).toFill( '#deposit-plan-form > :nth-child(1) > #plan_name', planName );
	}
	await expect( page ).toClick( '.add-row' );
	await expect( page ).toClick( '.add-row' );
	await expect( page ).toFill( '.wc-deposits-plan > tbody:nth-child(3) > tr:nth-child(1) > td > input', '34' );
	await expect( page ).toFill( '.wc-deposits-plan > tbody:nth-child(3) > tr:nth-child(2) > td > input', '33' );
	await expect( page ).toFill( '.wc-deposits-plan > tbody:nth-child(3) > tr:nth-child(3) > td > input', '33' );
	await page.click( 'input[type="submit"]' );
	await page.waitForSelector( '.updated.success' );
	await expect( page ).toMatchElement( '.updated.success > p', { text: 'Plan saved successfully' } );
};

/**
 * Delete Payment Plan.
 */
const deletePaymentPlan = async ( planName ) => {
	let index = 0;
	if ( ! planName ) {
		planName = 'Payment Plan';
		index = 1;
	}
	await DepositsStoreOwnerFlow.openPaymentPlansPage();
	await page.waitForSelector( '#the-list' );

	const planLinks = await page.$x( "//a[contains(text(), '" + planName + "')]" );
	const deleteLink = await page.evaluate( ( el ) => {
		return el.parentElement.nextElementSibling.childNodes[ 3 ].href;
	}, planLinks[ index ] );
	await page.goto( deleteLink, {
		waitUntil: 'networkidle0',
	} );
};
/**
 * Create simple product and attach existing Payment Plan.
 */
const createSimplePaymentPlanProduct = async () => {
	// Go to "add product" page.
	await StoreOwnerFlow.openNewProduct();

	// Make sure we're on the add product page.
	await expect( page.title() ).resolves.toMatch( 'Add new product' );
	
	// Set product title.
	await clickTab( 'General' );
	await expect( page ).toFill( '#_regular_price', '10' );

	// Enable Deposits.
	await clickTab( 'Deposits' );
	await expect( page ).toSelect( '#_wc_deposit_enabled', 'optional' );
	await expect( page ).toSelect( '#_wc_deposit_type', 'plan' );
	await page.waitForSelector( '#_wc_deposit_payment_plans' );
	await expect( page ).toSelect( '#_wc_deposit_payment_plans', 'Payment Plan' );

	// Set product title.
	await expect( page ).toFill( '#title', simplePaymentPlanProductName );

	await verifyAndPublish();

	const simplePostId = await page.$( '#post_ID' );
	const simplePostIdValue = ( await ( await simplePostId.getProperty( 'value' ) ).jsonValue() );
	return simplePostIdValue;
};

/**
 *  Delete product.
 */
const deleteProduct = async ( productId ) => {
	// Go to "Edit Product" page
	await DepositsStoreOwnerFlow.editProduct( productId );
	await page.waitForSelector( '.submitdelete' );
	await page.click( '.submitdelete' );
	await expect( page ).toMatchElement( 'h1', { text: 'Products' } );
	await expect( page ).toMatchElement( '#message', { text: '1 product moved to the Trash.' } );
};
/**
 * Create Coupon with 50% percentage discount
 * @param restricted bool True if usage is restricted.
 */
const createCoupon = async ( restricted = false ) => {
	await StoreOwnerFlow.openNewCoupon();

	// Make sure we're on the add coupon page
	await expect( page.title() ).resolves.toMatch( 'Add new coupon' );

	// Fill in coupon code and description
	await expect( page ).toFill( '#title', 'code-' + new Date().getTime().toString() );
	await expect( page ).toFill( '#woocommerce-coupon-description', 'test coupon' );

	// Set general coupon data
	await clickTab( 'General' );
	await expect( page ).toSelect( '#discount_type', 'Percentage discount' );
	await expect( page ).toFill( '#coupon_amount', '50' );

	if ( restricted ) {
		await clickTab( 'Usage limits' );
		await expect( page ).toFill( '#usage_limit', '1' );
		await expect( page ).toFill( '#limit_usage_to_x_items', '1' );
		await expect( page ).toFill( '#usage_limit_per_user', '1' );
	}

	// Publish and verify
	// Wait for auto save
	await page.waitFor( 2000 );
	await expect( page ).toClick( '#publish' );
	await page.waitForSelector( '#message' );
	await expect( page ).toMatchElement( '#message', { text: 'Coupon updated' } );

	// Get coupon code
	const couponCodeId = await page.$( '#original_post_title' );
	const couponCodeIdValue = ( await ( await couponCodeId.getProperty( 'value' ) ).jsonValue() );
	return couponCodeIdValue;
};

export {
	createSimpleDepositProduct,
	createSimplePaymentPlanProduct,
	createPaymentPlan,
	deletePaymentPlan,
	deleteProduct,
	createCoupon,
};
