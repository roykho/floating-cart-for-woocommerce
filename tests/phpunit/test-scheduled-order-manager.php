<?php

use PHPUnit\Framework\TestCase;

/**
 * The CartManagerTests class tests the functions associated with managing a cart containing Deposit products.
 */
class ScheduledOrderManagerTests extends TestCase {
	/**
	 * Set up our mocked WP functions. Rather than setting up a database we can mock the returns of core WordPress functions.
	 *
	 * @return void
	 */
	public function setUp() {
		\WP_Mock::setUp();

		Mockery::mock( 'overload:WC_Order_Data_Store_CPT' );
	}

	/**
	 * Tear down WP Mock.
	 *
	 * @return void
	 */
	public function tearDown() {
		\WP_Mock::tearDown();
	}

	/** @test - Test that instantiation of the class is working.
	 **/
	public function testIsAnInstanceOfScheduledOrderManagerClass() {

		$cart_manager = new WC_Deposits_Scheduled_Order_Manager();
		$this->assertInstanceOf( 'WC_Deposits_Scheduled_Order_Manager', $cart_manager );
	}

	/**
	 * Test deposit_pending_status() method.
	 *
	 * @dataProvider depositPendingStatusProvider
	 * @param array $args     List of arguments for `deposit_pending_status`
	 * @param bool  $expected Expected value
	 */
	public function testDepositPendingStatus( $args, $expected ) {
		// We have to mock it inside the test because you cannot use overload twice (e.g. in the data provider)
		$order = \Mockery::mock( 'WC_Order' )->makePartial();

		$order
			->shouldReceive( 'get_status' )
			->andReturn( $args[1] );

		$actual = WC_Deposits_Scheduled_Order_Manager::deposit_pending_status( $args[0], $order, $args[2] );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public function depositPendingStatusProvider() {
		return [
			[
				[ 'on-hold', 'partial-payment',   [ 'completed' ] ],
				true,
			],
			[
				[ 'on-hold', 'partial-payment',   [ 'pending' ] ],
				false,
			],
			[
				[ 'on-hold', 'scheduled-payment', [ 'completed' ] ],
				false,
			],
			[
				[ 'on-hold', 'scheduled-payment', [ 'pending' ] ],
				true,
			],
			[
				[ 'on-hold', 'pending-deposit',   [ 'completed' ] ],
				false,
			],
			[
				[ 'on-hold', 'pending-deposit',   [ 'pending' ] ],
				true,
			],
			[
				[ 'on-hold', 'pending-deposit',   'pending' ],
				true,
			]
		];
	}
}
