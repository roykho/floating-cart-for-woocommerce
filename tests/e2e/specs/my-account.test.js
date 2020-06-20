/**
 * Internal dependencies.
 */

import { StoreOwnerFlow, CustomerFlow } from '../utils/flows.js';
import { DepositsCustomerFlow } from '../deposits/deposits_flows';

describe( 'My Account Tests', () => {
	beforeAll( async () => {
		await StoreOwnerFlow.logout();
		await CustomerFlow.login();
	} );

	afterAll( async () => {
		await DepositsCustomerFlow.logout();
	} );

	test( 'Check that Scheduled Orders is added to My Account Menu', async () => {
		await CustomerFlow.goToOrders();
		await expect( page ).toMatchElement( 'li', { text: 'Scheduled Orders' } );
	} );
} );
