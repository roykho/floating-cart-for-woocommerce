/**
 * @format
 */

/**
 * External dependencies
 */
import { activatePlugin } from '@wordpress/e2e-test-utils';
import { uiUnblocked } from '../utils/index';

const config = require( 'config' );
const baseUrl = config.get( 'url' );

const WP_ADMIN_DASHBOARD = baseUrl + 'wp-admin';
const WP_ADMIN_EDIT_PRODUCT = baseUrl + 'wp-admin/post.php?action=edit';
export const DEPOSITS_SETTINGS_PAGE = WP_ADMIN_DASHBOARD + '/admin.php?page=wc-settings&tab=products&section=deposits';
export const PAYMENT_PLANS_PAGE = WP_ADMIN_DASHBOARD + '/edit.php?post_type=product&page=deposit_payment_plans';
const COUPONS_PAGE = WP_ADMIN_DASHBOARD + '/edit.php?post_type=shop_coupon';
const SHOP_MY_ACCOUNT_PAGE = baseUrl + 'my-account/';

/**
 * Login and check if plugin is activated. If not, activate it.
 */
export async function loginAndActivatePlugin( plugin, pluginWcCom ) {
	try {
		await activatePlugin( plugin );
	} catch ( error ) {
		await activatePlugin( pluginWcCom );
	}
}

const DepositsStoreOwnerFlow = {
	openPaymentPlansPage: async () => {
		await page.goto( PAYMENT_PLANS_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},
	openCouponsPage: async () => {
		await page.goto( COUPONS_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},
	editProduct: async ( productId ) => {
		const editPostURL = `${ WP_ADMIN_EDIT_PRODUCT }&post=${ productId }`;
		await page.goto( editPostURL, {
			waitUntil: 'networkidle0',
		} );
	},
};

const DepositsCustomerFlow = {
	logout: async () => {
		await page.goto( SHOP_MY_ACCOUNT_PAGE, {
			waitUntil: 'networkidle0',
		} );
		await expect( page.title() ).resolves.toMatch( 'My account' );
		await expect( page ).toClick( 'li', { text: 'Logout' } );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } )
	},
	applyCoupon: async () => {
		await expect( page ).toClick( 'button[name="apply_coupon"]' );
	},
	removeCoupon: async () => {
		await page.waitForSelector( '.woocommerce-remove-coupon' );
		await expect( page ).toClick( '.woocommerce-remove-coupon' );
		await uiUnblocked();
		await page.waitForSelector( '.woocommerce-message' );
		await expect( page ).toMatchElement( '.woocommerce-message', { text: 'Coupon has been removed.' } );
	},
	verifyOrderPlaced: async () => {
        await page.waitForSelector( '.woocommerce-thankyou-order-received' );
		const orderReceived = await expect( page ).toMatchElement( '.woocommerce-thankyou-order-received' );
		await expect( orderReceived ).toMatch( 'Thank you. Your order has been received.' );
    },
};

export { DepositsStoreOwnerFlow, DepositsCustomerFlow };
