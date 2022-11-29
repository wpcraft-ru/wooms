<?php

use PHPUnit\Framework\TestCase;

/**
 * Products sync
 */
class TempTest extends TestCase {



  /**
   * check product by UUID
   */
  public function test_check(){


  }

  function check_action( ActionScheduler_Action $item ){

    $data = $item->get_args();
    var_dump($data);

  }


  protected function setUp(): void
  {
    parent::setUp();
  }

  protected function tearDown(): void
  {
      //TBD
      parent::tearDown();
  }
}
