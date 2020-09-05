<?php 

namespace WooMS\Tests;

use Mockery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;


abstract class TestCase extends PhpUnitTestCase {


    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        setUp();
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void
    {
        tearDown();

        Mockery::close();

        parent::tearDown();

    }
    
}