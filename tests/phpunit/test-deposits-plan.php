<?php

use PHPUnit\Framework\TestCase;

/**
 * The CartManagerTests class tests the functions associated with managing a cart containing Deposit products.
 */
class DepositsPlanTests extends TestCase {
    
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
	public function testIsAnInstanceOfDepositsPlanClass() {

        $plan = new stdClass();
        $plan->ID = 0;
        $plan->name = "Deposit";
        $plan->description = "Deposit Plan";

		$cart_manager = new WC_Deposits_Plan( $plan );
		$this->assertInstanceOf( 'WC_Deposits_Plan', $cart_manager );
    }  
}