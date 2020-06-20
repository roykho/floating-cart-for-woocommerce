import { StoreOwnerFlow, CustomerFlow } from '../utils/flows';
import { uiUnblocked } from '../utils/index';
import { createSimpleDepositProduct, createSimplePaymentPlanProduct, deletePaymentPlan, createPaymentPlan, deleteProduct } from '../deposits/deposits_components.js';

describe( 'Cart Tests', () => {
	let simpleDepositsProductId,
		simplePaymentPlanProductId = '';

	beforeAll( async () => {
		await StoreOwnerFlow.login();
		await createPaymentPlan();
		simplePaymentPlanProductId = await createSimplePaymentPlanProduct();
		simpleDepositsProductId = await createSimpleDepositProduct();
		await StoreOwnerFlow.logout();
		await CustomerFlow.login();
	} );

	afterAll( async () => {
		await StoreOwnerFlow.login();
		await deleteProduct( simplePaymentPlanProductId );
		await deleteProduct( simpleDepositsProductId );
		await deletePaymentPlan();
	} );

	test( 'Check subtotal and order totals of product with payment plan', async () => {
		await CustomerFlow.goToProduct( simplePaymentPlanProductId );
		await page.waitForSelector( '#wc-option-pay-deposit' );
		await page.click( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCart();
		await CustomerFlow.productIsInCart( 'Simple Payment Plan Product', 1 );
		//Check Subtotal is $3.4
		await expect( page ).toMatchElement( '.cart-subtotal .amount', { text: '$3.4' } );
		//Check Future payments is $6.60
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$6.60' } );
		//Clear cart
		await CustomerFlow.removeFromCart( 'Simple Payment Plan Product' );
		// Verify the cart is clear so that other tests do not fail
		await uiUnblocked();
		await page.waitForSelector( '.cart-empty' );
		await expect( page ).toMatchElement( '.cart-empty', { text: 'Your cart is currently empty.' } );
	} );
	test( 'Check subtotal and order totals of product with 50% deposits', async () => {
		await CustomerFlow.goToProduct( simpleDepositsProductId );
		await page.waitForSelector( '#wc-option-pay-deposit' );
		await page.click( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCart();
		await CustomerFlow.productIsInCart( 'Simple Deposit Product', 1 );
		//Check Subtotal is $5.00
		await expect( page ).toMatchElement( '.cart-subtotal .amount', { text: '$5.00' } );
		//Check Future payments is $5.00
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$5.00' } );
		//Clear cart
		await CustomerFlow.goToCart();
		await CustomerFlow.removeFromCart( 'Simple Deposit Product' );
		// Verify the cart is clear so that other tests do not fail
		await uiUnblocked();
		await page.waitForSelector( '.cart-empty' );
		await expect( page ).toMatchElement( '.cart-empty', { text: 'Your cart is currently empty.' } );
	} );
} );
