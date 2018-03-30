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
require_once(__DIR__ . "/../common/Database.php");

class DatabaseTest extends XFramework\Test
{
    public function __construct() {
        parent::__construct();

        $this->db = new \XFramework\Database();

        $this->testRecord = array(
            "name" => "maya",
            "char_value" => "hello",
            "int_value" => 44,
            "bool_value" => true,
        );
    }

    public function testFix()
    {
        $data = array("key1" => "1", "key2" => "0");

        $this->db->fix($data, BOOL, array("key1", "key2"));
        $this->assertTrue($data['key1'] === true);
        $this->assertTrue($data['key2'] === false);

        $this->db->fix($data, INT, array("key1", "key2"));
        $this->assertTrue($data['key1'] === 1);
        $this->assertTrue($data['key2'] === 0);
    }

    public function testQuery()
    {
        $this->db->query("DROP TABLE IF EXISTS {xunit_tests}");

        $this->db->query("CREATE TABLE IF NOT EXISTS {xunit_tests} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            char_value VARCHAR(255) NOT NULL DEFAULT '',
            int_value INT NOT NULL DEFAULT 0,
            bool_value TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modified_at TIMESTAMP NULL DEFAULT NULL,
            removed_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id)
            ) ENGINE = InnoDB DEFAULT CHARSET utf8 COLLATE utf8_unicode_ci;"
        );
    }

    public function testInsert()
    {
        $this->assertTrue($this->db->insert("xunit_tests", $this->testRecord));
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("int_value" => 66))));
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("int_value" => 77))));
    }

    public function testLastInsertId()
    {
        $this->assertEquals($this->db->lastInsertId(), 3);
    }

    public function testRow()
    {
        $data = $this->db->row("xunit_tests", array("name" => "maya"));
        $this->assertIncludesArray($data, $this->testRecord);
    }

    public function testValue()
    {
        $value = $this->db->value("char_value", "xunit_tests", array("id" => 1, "name" => "maya"));
        $this->assertEquals($value, "hello");
    }

    public function testAll()
    {
        $data = $this->db->all("SELECT * FROM {xunit_tests} WHERE name = ?", "maya");
        $this->assertTrue(!empty($data));
        $this->assertEquals($data[0]['name'], "maya");
    }

    public function testUpdate()
    {
        $this->assertTrue(
            $this->db->update(
                "xunit_tests",
                array("char_value" => "updated", "int_value" => 55),
                array("id" => 1, "name" => "maya")
            )
        );

        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("name" => "maya")), "updated");
    }

    public function testRemove()
    {
        $this->assertTrue($this->db->remove("xunit_tests", array("id" => 1, "name" => "maya")));
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("id" => 1)), null);
    }

    public function testTransaction()
    {
        $this->db->beginTransaction();
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("char_value" => "trans"))));
        $this->db->commit();
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("char_value" => "trans")), "trans");

        $this->db->beginTransaction();
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("char_value" => "roll"))));
        $this->db->rollBack();
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("char_value" => "roll")), null);
    }

    public function testTruncate()
    {
        $this->assertTrue($this->db->truncate("xunit_tests"));
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("name" => "maya")), null);

        // last test: drop table
        $this->db->query("DROP TABLE IF EXISTS {xunit_tests}");
    }
}