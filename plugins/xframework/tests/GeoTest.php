<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/Geo.php");

class GeoTest extends XFramework\Test
{
    public function __construct() {
        parent::__construct();
    }

    public function testGetUserData()
    {
        $_SERVER['REMOTE_ADDR'] = "5.157.7.42";
        $data = \XFramework\Geo::getUserData();
        $this->assertTrue(!empty($data));
        $this->assertEquals($data['country_code'], "SE");
        $this->assertEquals($data['country_name'], "Sweden");
    }

    public function testGetDataFromIp()
    {
        $data = \XFramework\Geo::getDataFromIp("5.157.7.42");
        $this->assertTrue(!empty($data));
        $this->assertEquals($data['country_code'], "SE");
        $this->assertEquals($data['country_name'], "Sweden");
    }

    public function testGetCountryArray()
    {
        $array = \XFramework\Geo::getCountryArray(true, "en");
        $this->assertTrue(is_array($array));
        $this->assertEquals($array['IT'], "Italy");
    }

    public function testGetCountryName()
    {
        $this->assertEquals(\XFramework\Geo::getCountryName("IT"), "Italy");
    }
}