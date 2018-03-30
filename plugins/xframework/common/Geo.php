<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This file provides a base class for the Roundcub Plus plugins.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

class Geo
{
    /**
     * Gets the geolocation data for the currently logged in user based on the user ip.
     *
     * @param string $provider
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     * @return array
     */
    public static function getUserData($provider = "maxmind", $maxMindDatabase = false, $maxMindCity = false)
    {
        $rcmail = \rcmail::get_instance();

       // if (empty($rcmail->user->geoData)) {
            $rcmail->user->geoData = self::getDataFromIp(
                $_SERVER['REMOTE_ADDR'],
                $provider,
                $maxMindDatabase,
                $maxMindCity
            );
       // }

        return $rcmail->user->geoData;
    }

    /**
     * Gets the geolocation data for the specified ip.
     *
     * @param string $ip
     * @param string $provider
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     * @return array
     */
    public static function getDataFromIp($ip, $provider = "maxmind", $maxMindDatabase = false, $maxMindCity = false)
    {
        $data = array(
            "ip" => $ip,
            "country_code" => false,
            "country_name" => false,
            "city" => false,
            "latitude" => false,
            "longitude" => false,
        );

        if ($provider == "geoiploc") {
            self::getGeoIpLocData($ip, $data);
        } else {
            self::getMaxMindData($ip, $data, $maxMindDatabase, $maxMindCity);
        }

        $data["country_name"] = self::getCountryName($data["country_code"]);

        return $data;
    }

    public static function getCountryArray($includeUnknown = true, $language = false)
    {
        // get the user's language code
        if (!$language) {
            $rcmail = \rcmail::get_instance();
            $array = explode("_", $rcmail->user->language);
            $language = $array[0];
        }

        $countries = include(__DIR__ . "/countries/$language.php");

        if (empty($countries) && $language != "en") {
            $countries = include(__DIR__ . "/countries/en.php");
        }

        if (empty($countries)) {
            return array();
        }

        if (!$includeUnknown) {
            unset($countries['ZZ']);
        }

        return $countries;
    }

    public static function getCountryName($code)
    {
        $code = (string)$code;
        $countries = self::getCountryArray(true);

        if (!is_array($countries) || empty($code)) {
            return "Unknown";
        }

        if (!array_key_exists($code, $countries)) {
            $code = "ZZ";
        }

        return array_key_exists($code, $countries) ? $countries[$code] : "Unknown";
    }

    /**
     * Uses the geoiploc database to get the geo data.
     * http://chir.ag/projects/geoiploc/
     *
     * @param string $ip
     * @param array $data
     */
    private static function getGeoIpLocData($ip, &$data)
    {
        require_once(__DIR__ . "/geo/geoiploc.php");
        if ($code = getCountryFromIP($ip, "code")) {
            $data['country_code'] = $code == "ZZ" ? false : $code;
        }
    }

    /**
     * Uses the MaxMind database to get the geo data.
     * http://dev.maxmind.com/geoip/
     *
     * @param string $ip
     * @param array $data
     * @param string $provider
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     */
    private static function getMaxMindData($ip, &$data, $maxMindDatabase = false, $maxMindCity = false)
    {
        try {
            require_once(__DIR__ . "/../vendor/autoload.php");

            // use the local country database unless a different database is specified
            if (!$maxMindDatabase) {
                $maxMindDatabase = __DIR__ . "/geo/GeoLite2-Country.mmdb";
                $maxMindCity = false;
            }

            $reader = new \GeoIp2\Database\Reader($maxMindDatabase);

            if ($maxMindCity) {
                $record = $reader->city($ip);
                $data['city'] = $record->city->name;
                $data['latitude'] = $record->location->latitude;
                $data['longitude'] = $record->location->longitude;
            } else {
                $record = $reader->country($ip);
            }

            if (!empty($record->country->isoCode)) {
                $data["country_code"] = $record->country->isoCode;
            }
        } catch (\Exception $e) {
        }
    }

}