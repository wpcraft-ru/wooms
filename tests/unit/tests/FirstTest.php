<?php

namespace WooMS\Tests;

class FirstTest extends TestCase
{

    public function testFirst()
    {

        $this->assertTrue(true);
    }


    public function uuid_from_href_provider()
    {
        return [
            [
                "https://online.moysklad.ru/api/remap/1.2/entity/product/35427052-36e7-11e7-8a7f-40d0000000d1", "35427052-36e7-11e7-8a7f-40d0000000d1"
            ],
            [
                "https://online.moysklad.ru/api/remap/1.2/entity/product/35427052-36e7-11e7-8a7f-40d0000000d2", "35427052-36e7-11e7-8a7f-40d0000000d2"
            ],
            [
                "", false
            ]
        ];
    }


    /**
     * тестируем получение uuid из url wooms_get_wooms_id_from_href($href)
     * 
     * @dataProvider uuid_from_href_provider
     */
    public function test_wooms_get_wooms_id_from_href($href, $result)
    {
        $uuid = wooms_get_wooms_id_from_href($href);


        $this->assertSame($result, $uuid, "pizdec");
    }
}
