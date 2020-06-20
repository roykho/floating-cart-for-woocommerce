<?php

use PHPUnit\Framework\TestCase;

/**
 * The CartManagerTests class tests the functions associated with managing a cart containing Deposit products.
 */
class DepositsMyAccountTests extends TestCase {
	/**
	 * Set up our mocked WP functions. Rather than setting up a database we can mock the returns of core WordPress functions.
	 *
	 * @return void
	 */
	public function setUp() {
		\WP_Mock::setUp();
	}
	/**
	 * Tear down WP Mock.
	 *
	 * @return void
	 */
	public function tearDown() {
		\WP_Mock::tearDown();
	}

	/** Test that we properly add Scheduled Orders as a menu item.
	 **/
	public function testScheduledOrdersMenuAdded() {

		$deposits_my_account = new WC_Deposits_My_Account();
		$original_menu_items = array(
			'dashboard'     => "Dashboard",
			'orders'        => "Orders",
			'downloads'     => "Downloads",
			'edit-address'  => "Addresses",
			'edit-account'  => "Account details",
			'customer-logout' => "Logout"
		);

		$expected_menu_items = array(
			'dashboard'        => "Dashboard",
			'orders'           => "Orders",
			'scheduled-orders' => "Scheduled Orders",
			'downloads'        => "Downloads",
			'edit-address'     => "Addresses",
			'edit-account'     => "Account details",
			'customer-logout'  => "Logout",
		);

		$new_menu_items = $deposits_my_account->new_menu_items( $original_menu_items );
		$this->assertEquals( $expected_menu_items, $new_menu_items );
	}  
}
