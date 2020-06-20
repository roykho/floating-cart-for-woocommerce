/**
 * Internal dependencies.
 */

import { CustomerFlow, StoreOwnerFlow } from '../utils/flows.js';
import { uiUnblocked } from '../utils/index';
import { createSimpleDepositProduct, createSimplePaymentPlanProduct, createPaymentPlan, deleteProduct, deletePaymentPlan } from '../deposits/deposits_components.js';
import { DepositsCustomerFlow } from '../deposits/deposits_flows';

/**
 * External dependencies.
 */
const config = require( 'config' );
/**
 * Define constants.
 */
const customerAddress = config.get( 'addresses.customer.billing' );

describe( 'Checkout Tests', () => {
	let simpleDepositsProductId,
		simplePaymentPlanProductId = '';

	beforeAll( async () => {
		await StoreOwnerFlow.login();
		simpleDepositsProductId = await createSimpleDepositProduct();
		await createPaymentPlan();
		simplePaymentPlanProductId = await createSimplePaymentPlanProduct();
		await StoreOwnerFlow.logout();
		await CustomerFlow.login();
	} );

	afterAll( async () => {
		await StoreOwnerFlow.login();
		await deleteProduct( simpleDepositsProductId );
		await deleteProduct( simplePaymentPlanProductId );
		await deletePaymentPlan();
	} );

	test( 'Add to Cart and Checkout Simple Deposit Product', async () => {
		await CustomerFlow.goToProduct( simpleDepositsProductId );
		await page.waitForSelector( '#wc-option-pay-deposit' );
		await page.click( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCheckout();
		await CustomerFlow.fillBillingDetails( customerAddress );
		await uiUnblocked();
		await CustomerFlow.placeOrder();
		await DepositsCustomerFlow.verifyOrderPlaced();
	} );

	test( 'Add to Cart and Checkout Simple Payment Plan Product', async () => {
		await CustomerFlow.goToProduct( simplePaymentPlanProductId );
		await page.waitForSelector( '#wc-option-pay-deposit' );
		await expect( page ).toClick( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'input[name="wc_deposit_payment_plan" ]' );
		await expect( page ).toClick( 'input[name="wc_deposit_payment_plan" ]' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCheckout();
		await CustomerFlow.fillBillingDetails( customerAddress );
		await uiUnblocked();
		await CustomerFlow.placeOrder();
		await DepositsCustomerFlow.verifyOrderPlaced();
	} );
} );
