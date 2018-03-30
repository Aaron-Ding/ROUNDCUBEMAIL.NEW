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
require_once(__DIR__ . "/../common/Input.php");

class InputTest extends XFramework\Test
{
    public function __construct() {
        parent::__construct();
        $this->data = array("key1" => "value1", "key2" => "value2");
        $this->input = new \XFramework\Input(false, $this->data);
    }

    public function testGetAll()
    {
        $this->assertEquals($this->input->getAll(), $this->data);
    }

    public function testGet()
    {
        $this->assertEquals($this->input->get("key1"), "value1");
    }

    public function testHas()
    {
        $this->assertTrue($this->input->has("key1"));
        $this->assertFalse($this->input->has("nothing"));
    }

    public function testFill()
    {
        $data = $this->input->fill(array("key1", "key2", "key3"));
        $this->assertEquals($data['key1'], "value1");
        $this->assertEquals($data['key2'], "value2");
        $this->assertEquals($data['key3'], false);
    }
}