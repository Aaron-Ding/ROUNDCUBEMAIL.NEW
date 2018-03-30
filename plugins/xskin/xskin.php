<?php
/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/../xframework/common/Plugin.php");

class xskin extends XFramework\Plugin
{
    protected $enabled = true;
    protected $color = false;
    protected $settings = false;
    protected $appUrl = "?_task=settings&_action=preferences&_section=general";

    public $allowed_prefs = array(
        "xcolor_alpha",
        "xcolor_droid",
        "xcolor_icloud",
        "xcolor_outlook",
        "xcolor_litecube",
        "xcolor_litecube-f",
        "xcolor_w21",
    );

    /**
     * List of plugins that are not fully compatible with the Roundcube skinning functionality.
     * the plugins listed here will be tricked to believe they run under larry.
     */
    private $fixPlugins = array();

    private $disablePluginsOnMobile = array(
        "preview_pane",
        "google_ads",
        "threecol"
    );

    /**
     * Initializes the plugin.
     */
    public function initialize()
    {
        // if not bought and no promos, set the watermark so we avoid errors if the skin is set to something else than
        // larry and use disabledPreferenceList to remove the rc+ skins from the settings selection
        if (!$this->paid && !$this->settingsPromo) {
            $this->rcmail->output->set_env("xwatermark", "../../skins/larry/images/watermark.jpg");
            $this->add_hook("preferences_list", array($this, "disabledPreferencesList"));
            return;
        }

        $this->setSkin();

        $this->fixPlugins = $this->rcmail->config->get("fix_plugins", array());
        $this->disablePluginsOnMobile = array_merge(
            $this->disablePluginsOnMobile,
            $this->rcmail->config->get("disable_plugins_on_mobile", array())
        );

        $this->enabled = array_key_exists($this->rcmail->output->get_env("xskin"), $this->skins);
        $this->addSkinInterfaceMenuItem();

        if ($this->enabled) {
            $this->add_hook("startup", array($this, "startup"));
            $this->add_hook("config_get", array($this, "getConfig"));
            $this->add_hook("render_page", array($this, "renderPage"));
        } else {
            // include xskin so quick skin change works on larry
            $this->includeAsset("assets/scripts/xskin.min.js");
        }

        $this->add_hook("preferences_list", array($this, "preferencesList"));
        $this->add_hook("preferences_save", array($this, "preferencesSave"));

        if ($this->rcmail->task == "settings") {
            $this->includeAsset("assets/styles/xsettings_skin_selector.css");
            $this->includeAsset("assets/scripts/xsettings_skin_selector.min.js");
        }
    }

    /**
     * Startup hook. Sets up the plugin functionality.
     *
     * @global array $CONFIG
     */
    public function startup()
    {
        $legacyPlugins = array("nutsmail_theme_selector", "rcs_mobile_options", "rcs_mobile_switch", "rcs_skins");
        $legacyResult = array_intersect($legacyPlugins, $this->rcmail->config->get('plugins'));

        if (!empty($legacyResult)) {
            echo "<!DOCTYPE html>\n<html lang='en'><head>".
                "<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />".
                "<title>Roundcube Webmail</title>".
                "<style type='text/css'>".
                "body { font-family: Arial, Helvetica, sans-serif; font-style: normal; }".
                "</style>".
                "</head><body>".
                "<h3>Roundcube Error</h3>";

            if (count($legacyResult) > 1) {
                echo "<p>The following plugins are obsolete and should be removed from the plugins array of your ".
                    "config file:</p>".
                    "<p>" . implode("<br />", $legacyResult). "</p>" .
                    "<p>The functionality provided by these plugins is now included in the plugin <em>xskins</em>.</p>";
            } else {
                echo "<p>The plugin <em>" . implode("", $legacyResult) . "</em> is obsolete and should be removed from the ".
                    "plugins array of your config file.</p>".
                    "<p>The functionality provided by this plugin is now included in the plugin <em>xskins</em>.</p>";
            }

            exit("</body></html>");
        }

        $skin = $this->rcmail->output->get_env("xskin");
        $skinType = $this->rcmail->output->get_env("xskin_type");

        // load skin settings
        $this->settings = include(INSTALL_PATH . "skins/$skin/settings.php");

        if (empty($this->settings)) {
            $this->enabled = false;
            return;
        }

        // set skin color from config
        $defaultColor = $this->rcmail->config->get("default_color_" . $skin);
        if (!$defaultColor || !in_array($defaultColor, $this->settings['colors'])) {
            $defaultColor = $this->settings['default_color'];
        }

        $this->color = $this->rcmail->config->get("xcolor_" . $skin, $defaultColor);

        if (!in_array($this->color, $this->settings['colors'])) {
            $this->color = $this->settings['default_color'];
        }

        $this->rcmail->output->set_env("xcolor", $this->color);

        // include assets
        $this->includeAsset("assets/scripts/xskin.min.js");

        if ($skinType == "mobile") {
            $this->includeAsset("assets/scripts/hammer.min.js");
            $this->includeAsset("assets/scripts/jquery.hammer.js");
            $this->includeAsset("assets/scripts/xmobile.min.js");
            $this->includeAsset("assets/styles/xmobile.css");
            $this->includeAsset("../../skins/$skin/assets/mobile.css");
        } else {
            $this->includeAsset("assets/scripts/xdesktop.min.js");
            $this->includeAsset("assets/styles/xdesktop.css");
            $this->includeAsset("../../skins/$skin/assets/desktop.css");
        }

        // add labels to env
        $this->rcmail->output->add_label("login", "folders", "search", "attachment", "section", "options");

        // disable composing in html on mobile devices unless config option set to allow
        if ($this->rcmail->output->get_env("xmobile") && !$this->rcmail->config->get("allow_mobile_html_composing")) {
            global $CONFIG;
            $CONFIG['htmleditor'] = false;
        }

        // set the skin logo config value to the one specified in xskin config, or to the default skin logo image
        // if not specified in xskin config
        if ($this->rcmail->action == "print") {
            $logo = $this->rcmail->config->get("print_branding_$skin", "skins/$skin/assets/images/logo_print.png");
        } else {
            $logo = $this->rcmail->config->get("header_branding_$skin", "skins/$skin/assets/images/logo_header.png");
        }

        $configLogo = $this->rcmail->config->get("skin_logo");

        if (is_array($configLogo)) {
            $configLogo["*"] = $logo;
        } else {
            $configLogo = $logo;
        }

        $this->rcmail->config->set("skin_logo", $configLogo);

        // add color boxes to the interface menu
        $this->addColorInterfaceMenuItem();

        // set the preview background logo (loaded using js in [skin]/watermark.html)
        $this->rcmail->output->set_env(
            "xwatermark",
            $this->rcmail->config->get("preview_branding", "../../plugins/xskin/assets/images/watermark.png")
        );

        // add classes to body
        $bodyClasses = array(
            "xskin skin-" . $this->rcmail->output->get_env("xskin"),
            "color-{$this->color}",
            "{$this->rcmail->task}-page",
            "x" . $this->rcmail->output->get_env("xskin_type"),
        );

        if ($this->settings['font_icons_toolbars']) {
            $bodyClasses[] = "font-icons-toolbars";
        }

        $this->addBodyClass(implode(" ", $bodyClasses));
    }

    /**
     * Sets the current skin and color and fills in the correct properties for the desktop, tablet and phone skin.
     */
    public function setSkin()
    {
        // check if already set
        if ($this->rcmail->output->get_env("xskin")) {
            return;
        }

        $noOverride = $this->rcmail->config->get('dont_override', array());
        is_array($noOverride) || $noOverride = array();
        $pref = $this->rcmail->user->get_prefs();
        $loggedIn = !empty($pref);

        // if skin set as a url parameter, use the skin - this is used by the quick skin change select option in the
        // popup where the color change is.
        if (!in_array("skin", $noOverride) &&
            ($urlSkin = \rcube_utils::get_input_value('skin', \rcube_utils::INPUT_GET))
        ) {
            if (empty($pref)) {
                $pref = array();
            }

            $pref['skin'] = $urlSkin;

            if ($this->rcmail->output->get_env("xphone")) {
                $pref['phone_skin'] = $urlSkin;
            } else if ($this->rcmail->output->get_env("xtablet")) {
                $pref['tablet_skin'] = $urlSkin;
            } else {
                $pref['desktop_skin'] = $urlSkin;
            }

            if ($loggedIn) {
                $this->rcmail->user->save_prefs($pref);
            }
        }

        $skin = isset($pref['skin']) ? $pref['skin'] : $this->rcmail->config->get('skin');
        $skin || $skin = "larry";

        $phoneSkin = isset($pref['phone_skin']) ? $pref['phone_skin'] : $this->rcmail->config->get('phone_skin', $skin);
        $tabletSkin = isset($pref['tablet_skin']) ? $pref['tablet_skin'] : $this->rcmail->config->get('tablet_skin', $skin);
        $desktopSkin = isset($pref['desktop_skin']) ? $pref['desktop_skin'] : $this->rcmail->config->get('desktop_skin', $skin);

        $phoneSkin || $phoneSkin = $skin;
        $tabletSkin || $tabletSkin = $skin;
        $desktopSkin || $desktopSkin = $skin;

        if ($this->rcmail->output->get_env("xphone")) {
            $skin = $phoneSkin;
            $skinType = "mobile";
        } else if ($this->rcmail->output->get_env("xtablet")) {
            $skin = $tabletSkin;
            $skinType = "mobile";
        } else {
            $skin = $desktopSkin;
            $skinType = "desktop";
        }

        if ($this->resell &&
            $this->rcmail->task != "login" &&
            !$this->resell->getSkinPaymentStatus($this->getUserInfo(), $skin) &&
            empty($_SESSION['xskin_settings_saved'])
        ) {
            $skin = "larry";
            $phoneSkin = "larry";
            $tabletSkin = "larry";
            $desktopSkin = "larry";
            $skinType = "desktop";
            $this->setDevice(true);
        }

        // litecube-f doesn't support mobile, set the device to desktop to avoid errors
        if ($skin == "litecube-f") {
            $this->setDevice(true);
            $skinType = "desktop";
        }

        // change the skin in the environment
        if (method_exists($GLOBALS['OUTPUT'], "set_skin")) {
            $GLOBALS['OUTPUT']->set_skin($skin);
        }

        // if running a mobile skin, remove the apps menu before it gets added using js
        if ($skinType != "desktop") {
            $this->setJsVar("appsMenu", "");
        }

        // sent environment variables
        $this->rcmail->output->set_env("xskin", $skin);
        $this->rcmail->output->set_env("xphone_skin", $phoneSkin);
        $this->rcmail->output->set_env("xtablet_skin", $tabletSkin);
        $this->rcmail->output->set_env("xdesktop_skin", $desktopSkin);
        $this->rcmail->output->set_env("xskin_type", $skinType);
        $this->rcmail->output->set_env("rcp_skin", array_key_exists($skin, $this->skins));
    }

    public function addSkinInterfaceMenuItem()
    {
        // add the skin selection item to interface menu
        $dontOverride = $this->rcmail->config->get('dont_override', array());

        if ($this->paid && !in_array("skin", $dontOverride) && !$this->rcmail->config->get("disable_menu_skins")) {
            if (count($this->getInstalledSkins()) > 1) {
                $select = new \html_select(array("onchange" => "xskin.quickSkinChange()"));
                $added = 0;

                foreach ($this->getInstalledSkins() as $installedSkin) {
                    if (array_key_exists($installedSkin, $this->skins)) {
                        $select->add($this->skins[$installedSkin], $installedSkin);
                        $added++;
                    } else if ($installedSkin == "larry") {
                        $select->add("Larry", $installedSkin);
                        $added++;
                    }
                }

                if ($added > 1) {
                    $this->addToInterfaceMenu(
                        "quick-skin-change",
                        \html::div(
                            array("id" => "quick-skin-change", "class" => "section"),
                            \html::div(
                                array("class" => "section-title"),
                                \rcube_utils::rep_specialchars_output($this->gettext("skin"))
                            ) .
                            $select->show($this->rcmail->output->get_env("xskin"))
                        )
                    );
                }
            }
        }

        if (!in_array("language", $dontOverride) && !$this->rcmail->config->get("disable_menu_languages")) {
            $languages = $this->rcmail->list_languages();
            asort($languages);

            $select = new \html_select(array("onchange" => "xframework.quickLanguageChange()"));
            $select->add(array_values($languages), array_keys($languages));

            $this->addToInterfaceMenu(
                "quick-language-change",
                \html::div(
                    array("id" => "quick-language-change", "class" => "section"),
                    \html::div(array("class" => "section-title"), $this->gettext("language")) .
                    $select->show($this->rcmail->user->language)
                )
            );
        }
    }

    function addColorInterfaceMenuItem()
    {
        // create the color selection boxes
        if (!empty($this->settings['colors']) && !$this->rcmail->config->get("disable_menu_colors")) {
            $colorPopup = "";
            foreach ($this->settings['colors'] as $color) {
                $colorPopup .= \html::a(
                    array(
                        "class" => "color-box",
                        "onclick" => "xskin.changeColor('$color')",
                        "style" => "background:#$color !important",
                    ),
                    " "
                );
            }

            if ($colorPopup) {
                $this->addToInterfaceMenu(
                    "skin-color-select",
                    \html::div(
                        array("id" => "skin-color-select", "class" => "section"),
                        $colorPopup
                    )
                );
            }
        }
    }

    /**
     * Hook retrieving config options (including user settings).
     */
    function getConfig($arg)
    {
        if (!$this->enabled) {
            return $arg;
        }

        // disable unwanted plugins on mobile devices
        if ($this->rcmail->output->get_env("xskin_type") == "mobile") {
            foreach ($this->disablePluginsOnMobile as $val) {
                if (strpos($arg['name'], $val) !== false) {
                    $arg['result'] = false;
                    return $arg;
                }
            }
        }

        // Substitute the skin name retrieved from the config file with "larry" for the plugins that treat larry-based
        // skins as "classic."
        if ($arg['name'] != "skin" || !array_key_exists($arg['result'], $this->skins)) {
            return $arg;
        }

        // check php version to use the right parameters
        if (version_compare(phpversion(), "5.3.6", "<")) {
            $options = false;
        } else {
            $options = DEBUG_BACKTRACE_IGNORE_ARGS;
        }

        // when passing 4 as the second parameter in php < 5.4, debug_backtrace will return null
        if (version_compare(phpversion(), "5.4.0", "<")) {
            $trace = debug_backtrace($options);
        } else {
            $trace = debug_backtrace($options, 4);
        }

        // check if the calling file is in the list of plugins to fix or it's a unit test and set the skin to larry
        if (!empty($trace[3]['file']) &&
            (in_array(basename(dirname($trace[3]['file'])), $this->fixPlugins) || basename($trace[3]['file']) == "TestCase.php")
        ) {
            $arg['result'] = "larry";
        }

        return $arg;
    }

    /**
     * The render page hook. Adds classes to the body element and performs other html manipulation.
     *
     * @param type $arg
     * @return type
     */
    public function renderPage($arg)
    {
        if (!$this->enabled) {
            return $arg;
        }

        // modify page html
        if ($this->rcmail->task == "login" || $this->rcmail->task == "logout") {
            $this->modifyLoginHtml($arg);
        } else {
            $this->modifyPageHtml($arg);
        }

        return $arg;
    }

    /**
     * Modifies the login page html, adds branding, product name, etc.
     * Unit tested via renderPage()
     *
     * @param array $arg
     */
    protected function modifyLoginHtml(&$arg)
    {
        if (!$this->enabled) {
            return $arg;
        }

        $this->replace(
            '<form name',
            '<div id="company-name">' . $this->rcmail->config->get("product_name") . '</div><form name',
            $arg['content'],
            4773
        );

        // if login logo is specified, add it, if not, add an h1 that says "Login"
       $logo = $this->rcmail->config->get("login_branding_" . $this->rcmail->output->get_env("xskin"));

        if ($logo) {
            $html = \html::img(array("id" => "login-branding", "src" => $logo));
        } else {
            $html = \html::tag("h1", array(), \html::tag("span", array(), $this->rcmail->gettext("login")));
        }

        $this->replace("<form", $html . "<form", $arg['content'], 4774);

        // roundcube plus logo
        if (!$this->rcmail->config->get("remove_vendor_branding")) {
            $this->replace(
                "</body>",
                \html::a(
                    array(
                        "id" => "vendor-branding",
                        "href" => "http://roundcubeplus.com",
                        "target" => "_blank",
                        "title" => "More Roundcube skins and plugins at roundcubeplus.com",
                    ),
                    \html::span(array("id" => "vendor-icon-2"), " ").
                    \html::span(array("id" => "vendor-icon-1"), " ")
                ).
                "</body>",
                $arg['content'],
                4775
            );
        }
    }

    /**
     * Modifies the html of the non-login Roundcube pages.
    * Unit tested via renderPage()
     *
     * @param array $arg
     */
    protected function modifyPageHtml(&$arg)
    {
        $skinType = $this->rcmail->output->get_env("xskin_type");
        $menuItems = array();

        // create the 'use mobile skin' button (added only if user switched to desktop skin on mobile)
        if ($skinType == "desktop" && isset($_COOKIE['rcs_disable_mobile_skin'])) {
            $menuItems[] =
                \html::div(
                    array("id" => "switch-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.enableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.enable_mobile_skin")) . "' />"
                );
        } else if ($skinType != "desktop") {
            $menuItems[] =
                \html::div(
                    array("id" => "switch-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.disableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.disable_mobile_skin")) . "' />"
                );
        }



        // if using a desktop skin on mobile devices after clicked "use desktop skin" show a link to revert to
        // mobile skin in the top bar
        if (isset($_COOKIE['rcs_disable_mobile_skin'])) {
            $this->replace(
                '<div class="topleft">',
                '<div class="topleft">'.
                \html::a(
                    array(
                        "class" => "enable-mobile-skin",
                        "href" => "javascript:void(0)",
                        "onclick" => "xskin.enableMobileSkin()",
                    ),
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.enable_mobile_skin"))
                ),
                $arg['content']
            );
        }

        // add the toolbar-bg element that is used by alpha
        $this->replace(
            '<div id="mainscreencontent',
            '<div id="toolbar-bg"></div><div id="mainscreencontent',
            $arg['content']
        );
    }

    /**
     * Performs string replacement with error checking. If the string to search for cannot be found it exists with an
     * error message.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @param string $errorNumber
     */
    protected function replace($search, $replace, &$subject, $errorNumber = false)
    {
        $count = 0;
        $subject = str_replace($search, $replace, $subject, $count);

        if ($errorNumber && !$count) {
            exit(
                "<p>ERROR $errorNumber: The Roundcube Plus skin cannot find a needed html element.</p>".
                "<p>This could mean that your Roundcube is not running properly or it is not compatible with the skin. ".
                "Disable the xskin plugin in config.inc.php and refresh this page to check if there are any errors.</p>"
            );
        }
    }

    /**
     * A shortcut for htmlspecialchars()
     *
     * @param string $string
     * @return string
     */
    protected function encode($string)
    {
        return htmlspecialchars($string, ENT_QUOTES);
    }

    /**
     * Creates a skin item selection box for the preferences page. The hidden inputs are needed for the
     * myroundcube settings plugin that displays skin previews.
     *
     * @param string $type
     * @param string $skin
     * @param string $skinname
     * @param string $thumbnail
     * @param string $author
     * @param string $license
     * @param string $selected
     * @return string
     */
    private function skinItem($type, $skin, $skinname, $thumbnail, $author, $license, $selected)
    {
        return
            html::div(array('class'=>"skinselection" . ($selected ? " selected" : "")),
                html::a(array('href'=>'javascript:void(0)', 'onclick'=>"settingsSkinSelector.dialog('$type', '$skin', this)"),
                    html::span(
                        'skinitem',
                        "<input type='hidden' value='$skin' />".
                        html::img(array('src'=>$thumbnail, 'class'=>'skinthumbnail', 'alt'=>$skin, 'width'=>64, 'height'=>64))
                    ) .
                    html::span(
                        'skinitem',
                        "<input type='hidden' value='$skin' />".
                        html::span('skinname', $this->encode($skinname)
                        ) .
                        html::br() .
                        html::span('skinauthor', $author ? 'by ' . $author : '') .
                        html::br() .
                        html::span('skinlicense', $license ? $this->gettext('license').':&nbsp;' . $license : ''))
                ));
    }

    public function disabledPreferencesList($arg)
    {
        if ($arg['section'] != 'general' || !isset($arg['blocks']['skin'])) {
            return $arg;
        }

        foreach ($this->skins as $skin => $name) {
            unset($arg['blocks']['skin']['options'][$skin]);
        }

        return $arg;
    }

    /**
     * Replaces the preference skin selection with a dialog-based selection that allows specifying separate desktop
     * table and phone skins.
     *
     * @global type $RCMAIL
     * @param array $arg
     * @return array
     */
    public function preferencesList($arg)
    {
        // split the skin selection to desktop, tablet and phone
        if ($arg['section'] != 'general' || !isset($arg['blocks']['skin'])) {
            return $arg;
        }

        // if skins set in config's dont_overwrite, don't do anything
        $no_override = array_flip($this->rcmail->config->get('dont_override', array()));

        if (isset($no_override['skin'])) {
            return $arg;
        }

        if (count($this->getInstalledSkins()) <= 1) {
            return $arg;
        }

        $phone = $this->rcmail->output->get_env("xphone");
        $tablet = $this->rcmail->output->get_env("xtablet");
        $desktop = $this->rcmail->output->get_env("xdesktop");
        $phoneSkin= $this->rcmail->output->get_env("xphone_skin");
        $tabletSkin= $this->rcmail->output->get_env("xtablet_skin");
        $desktopSkin= $this->rcmail->output->get_env("xdesktop_skin");

        // remove the interface skin block created by Roundcube
        unset($arg['blocks']['skin']);

        // add the current browser type to the "Browser Options" section
        if ($phone) {
            $browser = $this->gettext("phone");
        } else if ($tablet) {
            $browser = $this->gettext("tablet");
        } else {
            $browser = $this->gettext("desktop");
        }

        $arg['blocks']['browser']['options']['currentbrowser'] = array(
            'title' => $this->gettext("current_device"),
            'content' => $browser
        );

        // create skin selection hidden blocks that will be shown in dialogs, if mobile, create the selects
        // since we don't use dialogs in mobile
        if ($desktop) {
            $desktopList = "";
            $tabletList = "";
            $phoneList = "";
        } else {
            $desktopSelect = new html_select(array("name"=>"_skin", "id"=>"rcmfd_skin"));
            $tabletSelect = new html_select(array("name"=>"_tablet_skin", "id"=>"rcmfd_tablet_skin"));
            $phoneSelect = new html_select(array("name"=>"_phone_skin", "id"=>"rcmfd_phone_skin"));
        }

        foreach ($this->getInstalledSkins() as $skin) {

            $thumbnail = "./skins/$skin/thumbnail.png";

            if (!is_file($thumbnail)) {
                $thumbnail = './program/resources/blank.gif';
            }

            $skinname = ucfirst($skin);
            $author = "";
            $license = "";
            $meta = @json_decode(@file_get_contents("./skins/$skin/meta.json"), true);

            if (is_array($meta) && $meta['name']) {
                $skinname = $meta['name'];
                $author  = $this->encode($meta['author']); // we don't use links since the entire item is a link already
                $license = $this->encode($meta['license']);
            }

            if ($desktop) {

                // create the skin display boxes, add them to the appropriate lists for selection and set the
                // selected item

                $selected = $skin == $desktopSkin;
                $item = $this->skinItem("desktop", $skin, $skinname, $thumbnail, $author, $license, $selected);
                $desktopList .= $item;

                if ($selected) {
                    $desktopSelect = $item;
                }

                $selected = $skin == $tabletSkin;
                $item = $this->skinItem("tablet", $skin, $skinname, $thumbnail, $author, $license, $selected);
                $tabletList .= $item;

                if ($selected) {
                    $tabletSelect = $item;
                }

                $selected = $skin == $phoneSkin;
                $item = $this->skinItem("phone", $skin, $skinname, $thumbnail, $author, $license, $selected);
                $phoneList .= $item;

                if ($selected) {
                    $phoneSelect = $item;
                }
            } else {
                $desktopSelect->add($skinname, $skin);
                $tabletSelect->add($skinname, $skin);
                $phoneSelect->add($skinname, $skin);
            }
        }

        if ($desktop) {

            if (!$desktopSelect) {
                $desktopSelect = "<a href='javascript:void(0)' onclick='settingsSkinSelector.dialog(\"desktop\", \"\", this)'>" .
                    $this->encode($this->gettext("select")) . "</a>";
            }

            if (!$tabletSelect) {
                $tabletSelect = "<a href='javascript:void(0)' onclick='settingsSkinSelector.dialog(\"tablet\", \"\", this)'>" .
                    $this->encode($this->gettext("select")) . "</a>";
            }

            if (!$phoneSelect) {
                $phoneSelect = "<a href='javascript:void(0)' onclick='settingsSkinSelector.dialog(\"phone\", \"\", this)'>" .
                    $this->encode($this->gettext("select")) . "</a>";
            }

            $desktopSelect = "<div class='skin-select' id='desktop-skin-select'>$desktopSelect</div>".
                "<div class='skin-list' id='desktop-skin-list' title='" . $this->encode($this->gettext("select_desktop_skin")) . "'>".
                $desktopList.
                "</div>";

            $tabletSelect = "<div class='skin-select' id='tablet-skin-select'>$tabletSelect</div>".
                "<div class='skin-list' id='tablet-skin-list' title='" . $this->encode($this->gettext("select_tablet_skin")) . "'>".
                $tabletList.
                "</div>";

            $phoneSelect = "<div class='skin-select' id='phone-skin-select'>$phoneSelect</div>".
                "<div class='skin-list' id='phone-skin-list' title='" . $this->encode($this->gettext("select_phone_skin")) . "'>".
                $phoneList.
                "</div>".

                "<div id='skinPost'>".
                "<input id='desktop-skin-post' type='hidden' name='_skin' value='{$desktopSkin}' />".
                "<input id='tablet-skin-post' type='hidden' name='_tablet_skin' value='{$tabletSkin}' />".
                "<input id='phone-skin-post' type='hidden' name='_phone_skin' value='{$phoneSkin}' />".
                "</div>";
        } else {
            $desktopSelect = $desktopSelect->show($desktopSkin);
            $tabletSelect = $tabletSelect->show($tabletSkin);
            $phoneSelect = $phoneSelect->show($phoneSkin);
        }

        if (!$this->paid && $this->settingsPromo) {
            $arg['blocks']['skin']['options']['resell'] = array(
                'title' => null,
                'content' => $this->settingsPromo,
            );
        }

        $arg['blocks']['skin']['name'] = $this->encode($this->gettext('skin'));

        $arg['blocks']['skin']['options']['desktop_skin'] =
            array('title'=>$this->gettext("desktop_skin"), 'content'=>$desktopSelect);

        $arg['blocks']['skin']['options']['tablet_skin'] =
            array('title'=>$this->gettext("tablet_skin"), 'content'=>$tabletSelect);

        $arg['blocks']['skin']['options']['phone_skin'] =
            array('title'=>$this->gettext("phone_skin"), 'content'=>$phoneSelect);

        return $arg;
    }

    /**
     * Saves the skin selection preferences.
     *
     * @param array $arg
     * @return array
     */
    public function preferencesSave($arg)
    {
        if ($arg['section'] == 'general') {
            $arg['prefs']['desktop_skin'] = \rcube_utils::get_input_value('_skin', rcube_utils::INPUT_POST);
            $arg['prefs']['tablet_skin'] = \rcube_utils::get_input_value('_tablet_skin', rcube_utils::INPUT_POST);
            $arg['prefs']['phone_skin'] = \rcube_utils::get_input_value('_phone_skin', rcube_utils::INPUT_POST);

            if ($this->rcmail->output->get_env("xphone")) {
                $arg['prefs']['skin'] = $arg['prefs']['phone_skin'];
            } else if ($this->rcmail->output->get_env("xtablet")) {
                $arg['prefs']['skin'] = $arg['prefs']['tablet_skin'];
            } else {
                $arg['prefs']['skin'] = $arg['prefs']['desktop_skin'];
            }

            // used to load skins only for the session after the settings were saved
            $_SESSION['xskin_settings_saved'] = true;
        }

        return $arg;
    }
}