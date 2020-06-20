import { StoreOwnerFlow, CustomerFlow } from '../utils/flows';
import { uiUnblocked } from '../utils/index';
import { createSimpleDepositProduct, deleteProduct, createCoupon } from '../deposits/deposits_components.js';
import { DepositsCustomerFlow, DepositsStoreOwnerFlow } from '../deposits/deposits_flows';

/**
 * External dependencies.
 */
const config = require( 'config' );
/**
 * Define constants.
 */
const customerAddress = config.get( 'addresses.customer.billing' );

describe( 'Cart with Restricted Coupon Tests', () => {
	let simpleDepositsProductId,
		couponCode = '';

	beforeAll( async () => {
		await StoreOwnerFlow.login();
		simpleDepositsProductId = await createSimpleDepositProduct();
		couponCode = await createCoupon( true );
		await StoreOwnerFlow.logout();
	} );

	afterAll( async () => {
		await StoreOwnerFlow.login();
		await deleteProduct( simpleDepositsProductId );
		await StoreOwnerFlow.logout();
	} );
	test( 'Check coupon usage is increased after deposit is paid', async () => {
		// View product
		await CustomerFlow.login();
		await CustomerFlow.goToProduct( simpleDepositsProductId );
		await page.waitForSelector( '#wc-option-pay-deposit' );
		await page.click( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );

		// Add to cart and verify
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCart();
		await CustomerFlow.productIsInCart( 'Simple Deposit Product', 1 );

		// Add coupon
		await expect( page ).toFill( '#coupon_code', couponCode );
		await uiUnblocked();
		await DepositsCustomerFlow.applyCoupon();
		await uiUnblocked();

		// Checkout
		await CustomerFlow.goToCheckout();
		await CustomerFlow.fillBillingDetails( customerAddress );
		await uiUnblocked();
		await CustomerFlow.placeOrder();
		await DepositsCustomerFlow.verifyOrderPlaced();

		// Verify coupon usage is increased
		await StoreOwnerFlow.login();
		await DepositsStoreOwnerFlow.openCouponsPage();
		// Select the row containing the coupon
		const row = await expect( page ).toMatchElement( 'tr', { text: couponCode } );
		// Check Usage has been increased
		await expect( row ).toMatch( '1 / 1' );
	} );
} );
