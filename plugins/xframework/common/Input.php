<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This class retrieves request data sent by Angular ajax requests. Angular json-encodes the parameters and php doesn't
 * decode them properly into $_POST, so we get the data and decode it manually.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

class Input
{
    private $rcmail;
    private $data;
    private $autoTokenCheck;

    public function __construct($autoTokenCheck = true, $data = null)
    {
        $this->rcmail = \rcmail::get_instance();
        $this->autoTokenCheck = $autoTokenCheck;
        $this->data = $data;
    }

    /**
     * Gets all input data.
     */
    public function getAll()
    {
        $this->init();
        return $this->data;
    }

    /**
     * Get a variable from the post.
     *
     * @param string $key
     * @return unknown
     */

    public function get($key)
    {
        $this->init();
        return array_key_exists($key, $this->data) ? $this->data[$key] : false;
    }

    /**
     * Check whether a variable exists in the post.
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        $this->init();
        return isset($this->data[$key]);
    }

    /**
     * Fills an array with values from the POST. The array should contain a list of keys as values, the return will
     * contain those keys as keys and values from post as values.
     *
     * @param array $fields
     * @return array
     */
    public function fill(array $fields)
    {
        $this->init();
        $result = array();

        foreach ($fields as $key) {
            $result[$key] = array_key_exists($key, $this->data) ? $this->data[$key] : false;
        }

        return $result;
    }

    /**
     * Checks the Roundcube token sent with the request.
     */
    public function checkToken()
    {
        if (empty($_SERVER["HTTP_X_CSRF_TOKEN"]) ||
            $_SERVER["HTTP_X_CSRF_TOKEN"] != $this->rcmail->get_request_token()
        ) {
            http_response_code(403);
            exit();
        }
    }

    /**
     * Fills the data directly from the php input.
     */
    protected function init()
    {
        if (empty($this->data)) {
            $this->data = json_decode(file_get_contents('php://input'), true);
            is_array($this->data) || $this->data = array();

            if ($this->autoTokenCheck) {
                $this->checkToken();
            }
        }
    }
}