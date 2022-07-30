<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

/**
 * Sample test case.
 */
class TDD1 extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_tdd_1() {
		// Replace this with some actual testing code.
		$this->assertTrue( true );
	}

  public function test_CanStart() {

    $test_cat_1 = wp_insert_term('Testing 1', 'product_cat');
    $items = get_terms();
    $can_start = wooms_can_start();

		// Replace this with some actual testing code.
		$this->assertTrue( $can_start );
	}
}
