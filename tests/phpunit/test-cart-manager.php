<?php

use PHPUnit\Framework\TestCase;

/**
 * The CartManagerTests class tests the functions associated with managing a cart containing Deposit products.
 */
class CartManagerTests extends TestCase {
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

	/** @test - The most basic of PHPUnit tests to make sure it's working.
	 **/
	public function testTheBasics() {
		$this->assertEquals( true, true );
    }

	/** @test - Test that instantiation of the class is working.
	 **/
	public function testIsAnInstanceOfWCDepositsCartManagerClass() {

		$cart_manager = new WC_Deposits_Cart_Manager();
		$this->assertInstanceOf( 'WC_Deposits_Cart_Manager', $cart_manager );
    }  
}