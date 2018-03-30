<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This class provides the basis for plugin unit testing.
 *
 * Make sure @backupGlobals is set to disabled, otherwise you'll get the error:
 * "PDOException: You cannot serialize or unserialize PDO instances"
 * https://blogs.kent.ac.uk/webdev/2011/07/14/phpunit-and-unserialized-pdo-instances/
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/Database.php");
require_once(__DIR__ . "/Input.php");
require_once(__DIR__ . "/Format.php");
require_once(__DIR__ . "/../vendor/phpunit/phpunit/src/Framework/TestCase.php");

class Test extends \PHPUnit_Framework_TestCase
{
    public $rcmail = false;
    protected $input = false;
    protected $db = false;
    protected $userId = false;
    protected $class = false;

    public function __construct()
    {
        // start the session to prevent the headers already sent error during tests
        session_start();

        // set the server variables and include the roundcube framework
        $_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ ."/../../../index.php");
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
        require_once(__DIR__ . "/../../../program/include/iniset.php");

        // create the rcmail instance that will use config-test.inc.php
        $this->rcmail = \rcmail::get_instance(0, "test");

        // check if database name ends with test (just to make sure we're not running tests on a production database)
        if (substr($this->rcmail->config->get("db_dsnw"), -4, 4) != "test") {
            exit("\n\nError: The database name specified in config-test.inc.php should end with 'test'.\n\n");
        }

        $_SERVER["HTTP_X_CSRF_TOKEN"] = $this->rcmail->get_request_token();

        // emulate a logged in user
        $this->rcmail->set_user(
            new \rcube_user(
                null,
                array(
                    "user_id" => 1,
                    "username" => "maya",
                    "mail_host" => "localhost",
                    "created" => '2015-07-20 12:19:32',
                    "last_login" => "2015-09-22 06:49:35",
                    "password" => "",
                    "language" => "en_US",
                    "preferences" => "",
                    "active" => "1",
                )
            )
        );

        $this->input = new Input();
        $this->db = new Database();
        $this->format = new Format();
        $this->userId = $this->rcmail->get_user_id();

        parent::__construct();

        if (strpos(get_class($this), "X") === 0) {
            // convert camelcase to underscores, so XNewsFeedTest becomes xnews_feed
            $className = "x" . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr(get_class($this), 1, -4)));
            require_once(__DIR__ . "/../../$className/$className.php");
            $this->class = new $className(\rcube_plugin_api::get_instance());
            $this->class->init();
        }
    }

    protected function assertIncludesArray($fullArray, $partialArray)
    {
        $this->assertTrue(is_array($fullArray));
        $this->assertTrue(is_array($partialArray));
        $this->assertTrue(!empty($fullArray));
        $this->assertTrue(!empty($partialArray));

        foreach ($partialArray as $key => $val) {
            $this->assertTrue(isset($fullArray[$key]));
            $this->assertEquals((string)$fullArray[$key], (string)$val);

        }
    }
}