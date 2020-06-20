import { StoreOwnerFlow, CustomerFlow } from '../utils/flows';
import { uiUnblocked } from '../utils/index';
import { createSimpleDepositProduct, createSimplePaymentPlanProduct, deletePaymentPlan, createPaymentPlan, deleteProduct, createCoupon } from '../deposits/deposits_components.js';
import { DepositsCustomerFlow } from '../deposits/deposits_flows';

describe( 'Cart with Coupon Tests', () => {
	let simpleDepositsProductId,
		simplePaymentPlanProductId,
		couponCode = '';

	beforeAll( async () => {
		await StoreOwnerFlow.login();
		await createPaymentPlan();
		simpleDepositsProductId = await createSimpleDepositProduct();
		simplePaymentPlanProductId = await createSimplePaymentPlanProduct();
		couponCode = await createCoupon();
		await StoreOwnerFlow.logout();
		await CustomerFlow.login();
	} );

	afterAll( async () => {
		await StoreOwnerFlow.login();
		await deleteProduct( simplePaymentPlanProductId );
		await deleteProduct( simpleDepositsProductId );
		await deletePaymentPlan();
	} );
	test( 'Check order totals on simple deposits product after applying 50% discount', async () => {
		// View product
		await CustomerFlow.goToProduct( simpleDepositsProductId );
		await expect( page ).toClick( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );

		// Add to cart and verify
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCart();
		await CustomerFlow.productIsInCart( 'Simple Deposit Product', 1 );

		// Check Subtotal is $5.00
		await expect( page ).toMatchElement( '.cart-subtotal .amount', { text: '$5.00' } );
		//Check Future payments is $5.00
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$5.00' } );

		// Add coupon
		await expect( page ).toFill( '#coupon_code', couponCode );
		await uiUnblocked();
		await DepositsCustomerFlow.applyCoupon();
		await uiUnblocked();

		// Check future payment is $2.50
		await page.waitForSelector( '.woocommerce-remove-coupon' );
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$2.50' } );

		// // Remove coupon
		await DepositsCustomerFlow.removeCoupon();
		await page.waitForSelector( 'table.cart' ); // Wait till cart is fully visible.

		// // Clear cart
		await CustomerFlow.removeFromCart( 'Simple Deposit Product' );
		// // Verify the cart is clear so that other tests do not fail
		await uiUnblocked();
		await page.waitForSelector( '.cart-empty' );
		await expect( page ).toMatchElement( '.cart-empty', { text: 'Your cart is currently empty.' } );
	} );
	test( 'Check order totals on product with payment plan after applying 50% discount', async () => {
		// View product
		await CustomerFlow.goToProduct( simplePaymentPlanProductId );
		await expect( page ).toClick( '#wc-option-pay-deposit' );
		await page.waitForSelector( 'button.single_add_to_cart_button' );

		// Add to cart and verify
		await CustomerFlow.addToCart();
		await page.waitForSelector( '.wc-forward', { text: 'View cart' } );
		await CustomerFlow.goToCart();
		await CustomerFlow.productIsInCart( 'Simple Payment Plan Product', 1 );
		//Check Subtotal is $3.4
		await expect( page ).toMatchElement( '.cart-subtotal .amount', { text: '$3.4' } );
		//Check Future payments is $6.60
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$6.60' } );

		// Add coupon
		await expect( page ).toFill( '#coupon_code', couponCode );
		await uiUnblocked();
		await DepositsCustomerFlow.applyCoupon();
		await uiUnblocked();

		// Check future payment is $3.30
		await page.waitForSelector( '.woocommerce-remove-coupon' );
		await expect( page ).toMatchElement( '.order-total .amount', { text: '$3.30' } );

		// Remove coupon
		await DepositsCustomerFlow.removeCoupon();
		await page.waitForSelector( 'table.cart' ); // Wait till cart is fully visible.

		// Clear cart
		await CustomerFlow.removeFromCart( 'Simple Payment Plan Product' );
		// Verify the cart is clear so that other tests do not fail
		await uiUnblocked();
		await page.waitForSelector( '.cart-empty' );
		await expect( page ).toMatchElement( '.cart-empty', { text: 'Your cart is currently empty.' } );
	} );
} );
