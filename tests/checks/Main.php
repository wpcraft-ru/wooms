<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

/**
 * Sample test case.
 */
class Main extends WP_UnitTestCase {



  public function test_AddPost() {

    $new_post = array(
      'post_title'    => 'test post title',
      'post_content'  => 'test post content',
      'post_status'   => 'publish',
    );

    // Insert the post into the database
    $post_id = wp_insert_post( $new_post );


		$this->assertIsInt( $post_id );
	}

public function test_CanStart() {
    $can_start = wooms_can_start();

		$this->assertTrue( $can_start );
	}




}
