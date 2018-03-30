<?php
/**
 * Roundcube Plus Tests
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");

class XSkinTest extends XFramework\Test
{
    public function __construct()
    {
        parent::__construct();

        // run startup so we load the skin settings from file
        $this->class->rcmail->output->set_env("xskin", "outlook");
        $this->class->rcmail->output->get_env("xskin_type", "desktop");
        $this->class->startup();
    }

    public function testTranslations()
    {
        $this->assertEquals($this->class->gettext("xskin.desktop"), "Desktop");
    }

    public function testAssets()
    {
        $this->assertTrue(file_exists(__DIR__ . "/../assets/styles/xsettings_skin_selector.css"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xsettings_skin_selector.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xskin.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/hammer.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/jquery.hammer.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xmobile.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xdesktop.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/styles/xdesktop.css"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xdesktop.min.js"));
        $this->assertTrue(file_exists(__DIR__ . "/../assets/scripts/xdesktop.min.js"));

        $skins = $this->class->getSkins();
        unset($skins['litecube-f']);

        foreach ($skins as $skin => $name) {
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/meta.json"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/settings.php"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/thumbnail.png"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/watermark.html"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/assets/desktop.css"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/assets/mobile.css"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/includes/header.html"));
            $this->assertTrue(file_exists(__DIR__ . "/../../../skins/$skin/includes/links.html"));
        }
    }

    public function testSetSkin()
    {
        // unset these variables, they were set in __construct()
        $this->class->rcmail->output->set_env("xskin", false);
        $this->class->rcmail->output->get_env("xskin_type", false);

        $pref = $this->class->rcmail->user->get_prefs();
        $pref['skin'] = "outlook";
        $this->class->rcmail->user->save_prefs($pref);

        $this->class->setSkin();

        $this->assertEquals($this->class->rcmail->output->get_env("xskin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xphone_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xtablet_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xdesktop_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xskin_type"), "desktop");
    }


    public function testAddSkinInterfaceMenuItem()
    {
        $this->class->addSkinInterfaceMenuItem();

        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['quick-skin-change'], "Alpha") !== false);
        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['quick-language-change'], "Albanian") !== false);
    }

    public function testAddColorInterfaceMenuItem()
    {
        $this->class->addColorInterfaceMenuItem();

        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['skin-color-select'], "skin-color-select") !== false);
    }

    public function testGetConfig()
    {
        $arg = $this->class->getConfig(array("name" => "skin", "result" => "outlook"));

        $this->assertEquals($arg['result'], "larry");
    }

    public function testRenderPage()
    {
        // check rendering of the mail page
        $arg = $this->class->renderPage(array("content" => '<div id="mainscreencontent'));

        $this->assertTrue(strpos($arg['content'], "toolbar-bg") !== false);

        // check rendering of the login page
        $this->class->rcmail->task = "login";
        $arg = $this->class->renderPage(array("content" => '<form name></body>'));

        $this->assertTrue(strpos($arg['content'], "company-name") !== false);
        $this->assertTrue(strpos($arg['content'], "<h1>") !== false);
        $this->assertTrue(strpos($arg['content'], "vendor-branding") !== false);

        // check 'login_branding_*' config option
        $this->rcmail->config->set("login_branding_outlook", "skin-login-branding-image");
        $arg = $this->class->renderPage(array("content" => '<form name></body>'));

        $this->assertTrue(strpos($arg['content'], "skin-login-branding-image") !== false);

        // check 'remove_vendor_branding' config option
        $this->rcmail->config->set("remove_vendor_branding", true);
        $arg = $this->class->renderPage(array("content" => '<form name></body>'));

        $this->assertTrue(strpos($arg['content'], "vendor-branding") === false);
    }

    public function testDisabledPreferencesList()
    {
        $arg = $this->class->disabledPreferencesList(
            array("section" => "general", "blocks" => array("skin" => array("options" => array("outlook" => "outlook"))))
        );

        $this->assertTrue(!isset($arg['blocks']['skin']['options']['outlook']));
    }

    public function testPreferencesList()
    {
        $arg = $this->class->preferencesList(
            array("section" => "general", "blocks" => array("skin" => array("options" => array("outlook" => "outlook"))))
        );

        $this->assertTrue(isset($arg['blocks']['browser']['options']['currentbrowser']['title']));
        $this->assertTrue(
            strpos(
                $arg['blocks']['browser']['options']['currentbrowser']['title'],
                $this->class->gettext("current_device")
            ) !== false
        );

        $this->assertEquals($arg['blocks']['skin']['name'], "Interface skin");

        $this->assertTrue(strpos($arg['blocks']['skin']['options']['desktop_skin']['content'], "Alpha") !== false);
        $this->assertTrue(strpos($arg['blocks']['skin']['options']['tablet_skin']['content'], "Alpha") !== false);
        $this->assertTrue(strpos($arg['blocks']['skin']['options']['phone_skin']['content'], "Alpha") !== false);

    }

    public function testPreferencesSave()
    {
        $_POST = array(
            "_skin" => "_skin",
            "_tablet_skin" => "_tablet_skin",
            "_phone_skin" => "_phone_skin",
        );

        $arg = $this->class->preferencesSave(array('section' => "general"));

        $this->assertTrue(isset($arg['prefs']["desktop_skin"]) && $arg['prefs']["desktop_skin"] == "_skin");
        $this->assertTrue(isset($arg['prefs']["tablet_skin"]) && $arg['prefs']["tablet_skin"] == "_tablet_skin");
        $this->assertTrue(isset($arg['prefs']["phone_skin"]) && $arg['prefs']["phone_skin"] == "_phone_skin");
    }
}
