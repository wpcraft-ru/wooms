<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

use PHPUnit\Framework\TestCase;

/**
 * Sample test case.
 */
class StarterTests extends TestCase {

  public function test_CanStart() {
    $can_start = wooms_can_start();

		$this->assertTrue( $can_start );
	}

}
