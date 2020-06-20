/**
 * Internal dependencies.
 */

import { StoreOwnerFlow } from '../utils/flows.js';
import { createSimpleDepositProduct, createSimplePaymentPlanProduct, createPaymentPlan } from '../deposits/deposits_components.js';

describe( 'Admin Tests', () => {
	beforeAll( async () => {
		await StoreOwnerFlow.login();
	} );

	test( 'Should create a product with Deposits enabled', async () => {
		const postID = await createSimpleDepositProduct();
		await expect( parseInt( postID ) ).toBeGreaterThan( 1 );
	} );

	test( 'Should create a payment plan', async () => {
		await createPaymentPlan();
	} );

	test( 'Should create a product with Payment Plan enabled', async () => {
		const postID = await createSimplePaymentPlanProduct();
		await expect( parseInt( postID ) ).toBeGreaterThan( 1 );
	} );
} );
