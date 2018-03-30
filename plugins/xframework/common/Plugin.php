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
require_once(__DIR__ . "/Database.php");
require_once(__DIR__ . "/Input.php");
require_once(__DIR__ . "/Format.php");
require_once(__DIR__ . "/Geo.php");
require_once(__DIR__ . "/../xframework.php");

abstract class Plugin extends \rcube_plugin
{
    // overwrite these in the plugin to skip loading config or localization strings
    protected $hasConfig = true;
    protected $hasLocalization = true;

    public $rcmail = false; // must be public for unit tests
    protected $default = array();
    protected $input = false;
    protected $db = false;
    protected $userId = false;
    protected $plugin = false;
    protected $paid = true;
    protected $promo = false;
    protected $settingsPromo = false;
    protected $sidebarPromo = false;
    protected $pagePromo = false;
    protected $hasSidebarBox = false;
    protected $appUrl = false;

    // user preferences handled by xframework and saved via ajax
    protected $frameworkPrefs = array(
        "xsidebar_order",
    );

    protected $skins = array(
        "alpha" => "Alpha",
        "droid" => "Droid",
        "icloud" => "iCloud",
        "litecube" => "Litecube",
        "litecube-f" => "Litecube Free",
        "outlook" => "Outlook",
        "w21" => "W21",
    );

    /**
     * Creates the plugin.
     */
    public function init()
    {
        $this->plugin = get_class($this);
        $this->rcmail = \rcmail::get_instance();

        if (empty($this->rcmail->output) || !$this->setResell()) {
            return;
        }

        if ($this->hasConfig) {
            $this->load_config();
        }

        if ($this->hasLocalization) {
            $this->add_texts("localization/", false);
        }

        $this->input = new Input();
        $this->db = new Database();
        $this->format = new Format();
        $this->userId = $this->rcmail->get_user_id();

        if (!($l = $this->rcmail->config->get(base64_decode("bGljZW5zZV9rZXk="))) ||
            (substr(base_convert(crc32(substr($l, 0, 14)), 10, 36), 1, 2) != substr($l, 14, 2))
        ) {
            return $this->rcmail->output->set_env(
                "xwatermark",
                $this->rcmail->config->get("preview_branding", "../../plugins/xskin/assets/images/watermark.png")
            ) ||
            $this->rcmail->output->show_message(base64_decode("SW52YWxpZCBSb3VuZGN1YmUgUGx1cyBsaWNlbnNlIGtleS4="));
        }

        $this->setDevice();
        $this->setLanguage();

        if (!isset($this->rcmail->frameworkPreferencesHooked)) {
            $this->rcmail->frameworkPreferencesHooked = true;
            $this->add_hook("render_page", array($this, "hookRenderPage"));

            if ($this->rcmail->task == "settings") {
                $this->add_hook('preferences_sections_list', array($this, 'hookPreferencesSectionsList'));
                $this->add_hook('preferences_list', array($this, 'hookPreferencesList'));
                $this->add_hook('preferences_save', array($this, 'hookPreferencesSave'));
            }

            // add to allowed user preferences that are saved via ajax
            $this->allowed_prefs = array_merge($this->allowed_prefs, $this->frameworkPrefs);

            // handle the saving of the framework preferences sent via ajax
            if ($this->rcmail->action == "save-pref") {
                $pref = $this->rcmail->user->get_prefs();

                foreach ($this->frameworkPrefs as $name) {
                    if (\rcube_utils::get_input_value("_name", \rcube_utils::INPUT_POST) == $name) {
                        $pref[$name] = \rcube_utils::get_input_value("_value", \rcube_utils::INPUT_POST);
                    }
                }

                $this->rcmail->user->save_prefs($pref);
            }
        }

        // load the xframework translation strings so they can be available to the inheriting plugin
        // besides the plugin-specific uses, this is necessary to create the apps button in $this->createAppsButton()
        $this->loadFrameworkLocalization();

        if (!empty($this->databaseVersion)) {
            $this->updateDatabase();
        }

        // override the defaults of this plugin with its config settings, if specified
        if (!empty($this->default)) {
            foreach ($this->default as $key => $val) {
                $this->default[$key] = $this->rcmail->config->get($this->plugin . "_" . $key, $val);
            }

            // load the config/default values to environment
            $this->rcmail->output->set_env($this->plugin . "_settings", $this->default);
        }

        // set timezone offset (in seconds) to a js variable
        $this->setJsVar("timezoneOffset", $this->getTimezoneOffset());

        // include the framework assets
        $this->includeAsset("xframework/assets/bower_components/components-font-awesome/css/font-awesome.min.css");
        $this->includeAsset("xframework/assets/plugin/framework.min.js");
        $this->includeAsset("xframework/assets/plugin/framework.css");

        // add plugin to loaded plugins list
        isset($this->rcmail->xplugins) || $this->rcmail->xplugins = array();
        $this->rcmail->xplugins[] = $this->plugin;

        $this->initialize();
    }

    public function getSkins()
    {
        return $this->skins;
    }

    public function getPluginName()
    {
        return $this->plugin;
    }

    /**
     * Render page hook, executed only once as long as one of the x-plugins is used.
     *
     * @param array $arg
     * @return type
     */
    public function hookRenderPage($arg)
    {
        // create sidebar and add items to it
        if ($this->rcmail->task == "mail" && $this->rcmail->action == "") {
            $sidebarContent = "";
            $sidebarCollapsed = isset($_COOKIE["xsidebar-collapsed"]) && (int)$_COOKIE["xsidebar-collapsed"] ? " collapsed" : "";

            foreach ($this->getSidebarPlugins() as $plugin) {
                if ($this->showSidebarBox($plugin)) {
                    if ($this->paid) {
                        $box = $this->rcmail->plugins->get_plugin($plugin)->getSidebarBox();
                    } else {
                        if (is_array($this->sidebarPromo) &&
                            !empty($this->sidebarPromo['title']) &&
                            !empty($this->sidebarPromo['html'])
                        ) {
                            $box = array(
                                "title" => \rcube_utils::rep_specialchars_output($this->sidebarPromo['title']),
                                "html" => $this->sidebarPromo['html'],
                            );
                        } else {
                            continue;
                        }
                    }

                    if (!is_array($box) || !isset($box['title']) || !isset($box['html'])) {
                        continue;
                    }

                    $name = "xsidebar-box-" . $plugin;
                    $collapsed = isset($_COOKIE[$name]) && (int)$_COOKIE[$name] ? " collapsed" : "";

                    $sidebarContent .= \html::div(
                        array(
                            "class" => "box-wrap box-{$plugin} listbox$collapsed",
                            "id" => "sidebar-{$plugin}",
                            "data-name" => $plugin,
                        ),
                        "<h2 class='boxtitle' onclick='xsidebar.toggleBox(\"{$plugin}\", this)'>".
                        \html::span(array("class" => "sidebar-title-text"), $box['title']) . "</h2>".
                        \html::div(array("class" => "box-content"), $box['html'])
                    );
                }
            }

            if ($sidebarContent) {
                $arg['content'] = str_replace(
                    "<!-- end mailview-right -->",
                    "<!-- end mailview-right -->" .
                        \html::div(
                            array("id" => "xsidebar", "class" => "uibox listbox$sidebarCollapsed"),
                            $sidebarContent
                        ),
                    $arg['content']
                );

                $arg['content'] = str_replace(
                    '<div id="messagesearchtools">',
                    '<div id="messagesearchtools">'.
                        \html::a(
                            array(
                                "id" => "xsidebar-button",
                                "href" => "javascript:void(0)",
                                "class" => "button$sidebarCollapsed",
                                "onclick" => "xsidebar.toggle()",
                            ),
                            " "
                        ),
                    $arg['content']
                );
            }
        }

        // create the interface menu
        if (!empty($this->rcmail->xinterfaceMenuItems)) {
            $arg['content'] = str_replace(
                '<span class="minmodetoggle',
                \html::a(
                    array(
                        "class" => "button-interface-options",
                        "href" => "javascript:void(0)",
                        "id" => "interface-options-button",
                        "onclick" => "xframework.UI_popup('interface-options', event)",
                    ),
                    \html::span(
                        array("class" => "button-inner"),
                        \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.interface_options"))
                    )
                ).
                \html::div(
                    array("id" => "interface-options", "class" => "popupmenu"),
                    implode(" ", $this->rcmail->xinterfaceMenuItems)
                ).
                '<span class="minmodetoggle',
                $arg['content']
            );
        }

        // add inline styles
        if (!empty($this->rcmail->xinlineStyle)) {
            $arg['content'] = str_replace(
                "</head>",
                "<style>" . $this->rcmail->xinlineStyle . "</style></head>",
                $arg['content']
            );
        }

        // add inline scripts
        if (!empty($this->rcmail->xinlineScript)) {
            $arg['content'] = str_replace(
                "</head>",
                "<script>" . $this->rcmail->xinlineScript . "</script></head>",
                $arg['content']
            );
        }

        // add body classes
        if (!empty($this->rcmail->xbodyClasses)) {
            if (strpos($arg['content'], '<body class="')) {
                $arg['content'] = str_replace(
                    '<body class="',
                    '<body class="' . implode(" ", $this->rcmail->xbodyClasses) . ' ',
                    $arg['content']
                );
            } else {
                $arg['content'] = str_replace(
                    '<body',
                    '<body class="' . implode(" ", $this->rcmail->xbodyClasses) . '"',
                    $arg['content']
                );
            }
        }

        $this->createAppsMenu();
        $this->runAsl();

        return $arg;
    }

    /**
     * Executed on preferences section list, runs only once regardless of how many xplugins are used.
     *
     * @param type $arg
     * @return type
     */
    public function hookPreferencesSectionsList($arg)
    {
        // if any loaded xplugins have show on the sidebar, add the sidebar section
        if ($this->hasSidebarItems()) {
            $arg['list']['xsidebar'] = array('id' => 'xsidebar', 'section' => $this->gettext("sidebar"));
        }

        return $arg;
    }

    /**
     * Executed on preferences list, runs only once regardless of how many xplugins are used.
     *
     * @param type $arg
     * @return type
     */
    public function hookPreferencesList($arg)
    {
        if ($arg['section'] == "xsidebar") {
            $arg['blocks']['main']['name'] = $this->gettext("sidebar_items");

            foreach ($this->getSidebarPlugins() as $plugin) {
                $input = new \html_checkbox();

                $html = $input->show(
                    $this->getSetting("show_" . $plugin, true, $plugin),
                    array(
                        "name" => "show_" . $plugin,
                        "id" => $plugin . "_show_" . $plugin,
                        "data-name" => $plugin,
                        "value" => 1,
                    )
                );

                $this->addSetting($arg, "main", "show_" . $plugin, $html, $plugin);
            }

            if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
                $order = new \html_hiddenfield(array(
                    "name" => "xsidebar_order",
                    "value" => $this->rcmail->config->get("xsidebar_order"),
                    "id" => "xsidebar-order",
                ));

                $arg['blocks']['main']['options']["test"] = array(
                    "content" => $order->show() .
                        \html::div(array("id" => "xsidebar-order-note"), $this->gettext("sidebar_change_order"))
                );
            }
        }

		return $arg;
    }

    /**
     * Executed on preferences save, runs only once regardless of how many xplugins are used.
     *
     * @param type $arg
     * @return type
     */
    public function hookPreferencesSave($arg)
    {
        if ($arg['section'] == "xsidebar") {
            foreach ($this->getSidebarPlugins() as $plugin) {
                $this->saveSetting($arg, "show_" . $plugin, false, $plugin);
            }

            if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
                $arg['prefs']["xsidebar_order"] = \rcube_utils::get_input_value("xsidebar_order", \rcube_utils::INPUT_POST);
            }
        }

        return $arg;
    }

    public function getAppsUrl($check = false)
    {
        if (!empty($check)) {
            $check = "&check=" . (is_array($check) ? implode(",", $check) : $check);
        }

        return "?_task=settings&_action=preferences&_section=apps" . $check;
    }

    /**
     * Returns the timezone offset in minutes based on the user settings.
     *
     * @return type
     */
    public function getTimezoneOffset()
    {
        $dtz = new \DateTimeZone($this->rcmail->config->get("timezone"));
        $dt = new \DateTime("now", $dtz);
        return $dtz->getOffset($dt);
    }

    /**
     * Loads the xframework's localization strings. It adds the strings to the scope of the plugin that calls the
     * function.
     */
    public function loadFrameworkLocalization()
    {
        $home = $this->home;
        $this->home = dirname($this->home) . "/xframework";
        $this->add_texts("localization/", false);
        $this->home = $home;
    }

    /**
     * Returns the default settings of the plugin.
     *
     * @return array
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Updates the plugin's database structure by executing the sql files from the SQL directory, if needed.
     * The database versions of all the xframework plugins are stored in a single db row in the system table.
     * This function reads that row once for all the plugins and then compares the retrieved information
     * with the version of the current plugin. If the plugin db schema needs updating, it updates it.
     * Currently only mysql is supported.
     *
     * @return boolean
     */
    public function updateDatabase()
    {
        // if versions have not been retrieved yet, retrieve and decode them
        if (empty($this->rcmail->xversions)) {
            if (!($result = $this->db->value("value", "system", array("name" => "xframework_db_versions")))) {
                $result = "{}";
            }
            $this->rcmail->xversions = json_decode($result, true);
        }

        // get the current db verson for the current plugin
        $version = array_key_exists($this->plugin, $this->rcmail->xversions) ? $this->rcmail->xversions[$this->plugin] : 0;

        if ($version >= $this->databaseVersion) {
            return true;
        }

        $provider = $this->db->getProvider();

        // get the available versions in files
        $files = glob(__DIR__ . "/../../" . $this->plugin . "/SQL/$provider/*.sql");

        if (empty($files)) {
            exit("Roundcube error: The plugin {$this->plugin} does not support $provider.");
        }

        sort($files);
        $latest = 0;

        // execute the sql statements from files, replace [db_prefix] with the prefix specified in the config
        foreach ($files as $file) {
            $number = (int)basename($file, ".sql");
            if ($number && $number > $version) {
                if (!$this->db->script(file_get_contents($file))) {
                    return false;
                }
                $latest = $number;
            }
        }

        // update the version for this plugin in the versions array and save it
        if ($latest) {
            $this->rcmail->xversions[$this->plugin] = $latest;
            $versions = json_encode($this->rcmail->xversions);

            if ($this->db->value("value", "system", array("name" => "xframework_db_versions"))) {
                return $this->db->update(
                    "system",
                    array("value" => $versions),
                    array("name" => "xframework_db_versions")
                );
            } else {
                return $this->db->insert("system", array("name" => "xframework_db_versions", "value" => $versions));
            }
        }

        return true;
    }

    /**
     * Returns the installed xplugins that display boxes on the sidebar sorted in user-specified order.
     * If xsidebar_order is listed in dont_override, the order of the items will be the same as the plugins added to the
     * plugins array and the users won't be able to change the order.
     *
     * @return type
     */
    protected function getSidebarPlugins()
    {
        $result = array();

        if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
            foreach (explode(",", $this->rcmail->config->get("xsidebar_order")) as $plugin) {
                if (in_array($plugin, $this->rcmail->xplugins) &&
                    $this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox
                ) {
                    $result[] = $plugin;
                }
            }
        }

        foreach ($this->rcmail->xplugins as $plugin) {
            if (!in_array($plugin, $result) &&
                $this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox
            ) {
                $result[] = $plugin;
            }
        }

        return $result;
    }

    /**
     * Adds section to interface menu.
     *
     * @param type $id
     * @param type $html
     */
    protected function addToInterfaceMenu($id, $html)
    {
        if (empty($this->rcmail->xinterfaceMenuItems)) {
            $this->rcmail->xinterfaceMenuItems = array();
        }

        $this->rcmail->xinterfaceMenuItems[$id] = $html;
    }

    /**
     * Plugins can use this function to insert inline styles to the head element.
     *
     * @param type $style
     */
    protected function addInlineStyle($style)
    {
        if (empty($this->rcmail->xinlineStyle)) {
            $this->rcmail->xinlineStyle = "";
        }

        $this->rcmail->xinlineStyle .= $style;
    }

    /**
     * Plugins can use this function to insert inline scripts to the head element.
     * @param type $script
     */
    protected function addInlineScript($script)
    {
        if (empty($this->rcmail->xinlineScript)) {
            $this->rcmail->xinlineScript = "";
        }

        $this->rcmail->xinlineScript .= $script;
    }

    /**
     * Plugins can use this function to insert inline scripts to the head element.
     * @param type $script
     */
    protected function addBodyClass($class)
    {
        if (empty($this->rcmail->xbodyClasses)) {
            $this->rcmail->xbodyClasses = "";
        }

        $this->rcmail->xbodyClasses[] = $class;
    }

    /**
     * If plugin is not paid for but the settings promo is set, add the settings promo html at the top of the settings
     * page and hide the save button. This way the settings can still be seen and can encourage someone to buy the
     * plugin. Hiding the save button is cosmetic only, since the settings won't be saved in the backend anyway.
     *
     * @param type $arg
     */
    protected function addSettingsPromo(&$arg)
    {
        if ($this->settingsPromo) {
            $arg['blocks']['promo']['name'] = false;
            $arg['blocks']['promo']['options']['promo'] = array(
                'title' => null,
                'content' => $this->settingsPromo .
                    "<script>$(document).ready(function() { $('input.mainaction').hide(); });</script>"
            );
        }
    }

    /**
     * Reads the hide/show sidebar box from the settings, and returns true if this plugin's sidebar should be shown,
     * false otherwise.
     *
     * @return boolean
     */
    protected function showSidebarBox($plugin = false)
    {
        $plugin || $plugin = $this->plugin;
        return $this->rcmail->config->get($plugin . "_show_" . $plugin, true);
    }

    /**
     * Set the js environment variable.
     *
     * @param string $key
     * @param string $value
     */
    protected function setJsVar($key, $value)
    {
        if (!empty($this->rcmail->output)) {
            $this->rcmail->output->set_env($key, $value);
        }
    }

    /**
     * Returns the user setting, taking into account the default setting as set in the plugin's default.
     *
     * @param type $key
     * @param type $default
     * @return type
     */
    protected function getSetting($key, $default = null, $plugin = false)
    {
        $plugin || $plugin = $this->plugin;

        if ($default === null) {
            $default = array_key_exists($key, $this->default) ? $this->default[$key] : "";
        }

        return $this->rcmail->config->get($plugin . "_" . $key, $default);
    }

    /**
     * Includes a js or css file. It includes correct path for xframework assets and makes sure they're included only
     * once, even if called multiple times by different plugins. (Adding the name of the plugin to the assets because
     * the paths are relative and don't include the plugin name, so they overwrite each other in the check array)
     *
     * @param string $asset
     */
    protected function includeAsset($asset)
    {
        if (empty($this->rcmail->output)) {
            return;
        }

        // if xframework, step one level up
        if (($i = strpos($asset, "xframework")) !== false) {
            $asset = "../xframework/" . substr($asset, $i + 11);
            $checkAsset = $asset;
        } else {
            $checkAsset = $this->plugin . ":" . $asset;
        }

        $assets = $this->rcmail->output->get_env("xassets");
        if (!is_array($assets)) {
            $assets = array();
        }

        if (!in_array($checkAsset, $assets)) {
            $parts = pathinfo($asset);
            $extension = strtolower($parts['extension']);

            if ($extension == "js") {
                $this->include_script($asset);
            } else if ($extension == "css") {
                $this->include_stylesheet($asset);
            }

            $assets[] = $checkAsset;
            $this->rcmail->output->set_env("xassets", $assets);
        }
    }

    /**
     * Sends ajax response in json format.
     *
     * @param bool $success
     * @param array $data
     */
    protected function sendResponse($success, $data = array(), $errorMessage = false)
    {
        if ($success && is_array($data)) {
            exit(json_encode($data));
        }

        if (!$errorMessage) {
            $errorMessage = empty($data['errorMessage']) ? "Server error" : $data['errorMessage'];
        }

        exit(header("HTTP/1.0 500 " . $errorMessage));
    }

    /**
     * Creates a select html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param array $options
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingSelect(&$arg, $block, $name, $options, $default = null, $addHtml = false, array $attr = array())
    {
        $attr = array_merge(array("name" => $name, "id" => $this->plugin . "_$name"), $attr);
        $select = new \html_select($attr);

        foreach ($options as $key => $val) {
            $select->add($key, $val);
        }

        $value = $this->getSetting($name, $default);

        // need to convert numbers in strings to int, because when we pass an array of options to select and
        // the keys are numeric, php automatically converts them to int, so when we retrieve the value here
        // and it's a string, rc doesn't select the value in the <select> because it doesn't match
        if (is_numeric($value)) {
            $value = (int)$value;
        }

        $this->addSetting(
            $arg,
            $block,
            $name,
            $select->show($value) . $addHtml
        );
    }

    /**
     * Creates a checkbox html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingCheckbox(&$arg, $block, $name, $default = null, $addHtml = false)
    {
        $input = new \html_checkbox();
        $this->addSetting(
            $arg,
            $block,
            $name,
            $input->show(
                $this->getSetting($name, $default),
                array("name" => $name, "id" => $this->plugin . "_$name", "value" => 1)
            ) . $addHtml
        );
    }

    /**
     * Creates a text input html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingInput(&$arg, $block, $name, $default = null, $addHtml = false)
    {
        $input = new \html_inputfield();
        $this->addSetting(
            $arg,
            $block,
            $name,
            $input->show(
                $this->getSetting($name, $default),
                array("name" => $name, "id" => $this->plugin . "_$name")
            ) . $addHtml
        );
    }

    /**
     * Adds a setting to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $html
     */
    protected function addSetting(&$arg, $block, $name, $html, $plugin = null)
    {
        $plugin || $plugin = $this->plugin;

        $arg['blocks'][$block]['options'][$name] = array(
            "title" => \html::label(
                $plugin . "_$name",
                \rcube_utils::rep_specialchars_output($this->gettext($plugin . ".setting_" . $name))
            ),
            "content" => $html
        );
    }

    /**
     * Retrieves a value from POST, processes it and loads it to the 'pref' array of $arg, so RC saves it in the user
     * preferences.
     *
     * @param array $arg
     * @param string $name
     * @param string|bool $type Specifies the type of variable to convert the incoming value to.
     */
    protected function saveSetting(&$arg, $name, $type = false, $plugin = false)
    {
        $plugin || $plugin = $this->plugin;

        // if this setting shouldn't be overriden by the user, don't save it
        if (in_array($plugin . "_" . $name, $this->rcmail->config->get("dont_override"))) {
            return;
        }

        $value = \rcube_utils::get_input_value($name, \rcube_utils::INPUT_POST);
        if ($value === null) {
            $value = "0";
        }

        // fix the value type (all values incoming from POST are strings, but we may need them as int or bool, etc.)
        switch ($type) {
            case "boolean":
                $value = (bool)$value;
                break;
            case "integer":
                $value = (int)$value;
                break;
            case "double":
                $value = (double)$value;
                break;
        }

        $arg['prefs'][$plugin . "_" . $name] = $value;
    }

    /**
     * Parses and returns the contents of a plugin template file. The template files are located in
     * [plugin]/skins/[skin]/templates.
     *
     * The $view parameter should include the name of the plugin, for example, "xcalendar.event.edit".
     *
     * In some cases using rcmail_output_html to parse the file causes problems (for example in xsignature),
     * in that case we can set $processRoundcubeTags to false and use our own processing. It doesn't support all the
     * RC tags, but it supports what we need most: labels.
     *
     * @param string $view
     * @param unknown $data
     * @param processRoundcubeTags
     */
    public function view($view, $data = false, $processRoundcubeTags = true)
    {
        if (empty($data) || !is_array($data)) {
            $data = array();
        }

        $parts = explode(".", $view);
        $plugin = $parts[0];

        if ($processRoundcubeTags) {
            $output = new \rcmail_output_html($plugin, false);

            // add view data as env variables for roundcube objects and parse them
            foreach ($data as $key => $val) {
                $output->set_env($key, $val);
            }

            $html = $output->parse($view, false, false);
        } else {
            unset($parts[0]);
            $html = file_get_contents(__DIR__ . "/../../$plugin/skins/larry/templates/" . implode(".", $parts) . ".html");

            while (($i = strrpos($html, "[+")) !== false && ($j = strrpos($html, "+]")) !== false) {
                $html = substr_replace($html, $this->rcmail->gettext(substr($html, $i + 2, $j - $i - 2)), $i, $j - $i + 2);
            }
        }

        // replace our custom tags that can contain html tags

        foreach ($data as $key => $val) {
            $html = str_replace("[~" . $key . "~]", $val, $html);
        }

        return $html;
    }

    /**
     * Sends an email with html content.
     *
     * @param string $to
     * @param string $subject
     * @param string $html
     * @param boolean $error
     * @return type
     */
    public static function sendHtmlEmail($to, $subject, $html, &$error)
    {
        $rcmail = \rcmail::get_instance();
        $to = rcube_idn_to_ascii($to);
        $from = rcube_idn_to_ascii($rcmail->get_user_email());
        $error = false;

		$headers = array(
            "Date" => date("r"),
            "From" => $from,
            "To" => $to,
            "Subject" => $subject,
        );

        $message = new \Mail_mime($rcmail->config->header_delimiter());
        $message->headers($headers);
        $message->setParam("head_encoding", "quoted-printable");
        $message->setParam("html_encoding", "quoted-printable");
        $message->setParam("text_encoding", "quoted-printable");
        $message->setParam("head_charset", RCMAIL_CHARSET);
        $message->setParam("html_charset", RCMAIL_CHARSET);
        $message->setParam("text_charset", RCMAIL_CHARSET);
        $message->setContentType("text/html");
        $message->setHTMLBody($html);

        return $rcmail->deliver_message($message, $from, $to, $error);
    }

    /**
     * Encodes an integer id using Roundcube's desk key and returns hex string.
     *
     * @param int $id
     * @return string
     */
    public static function encodeId($id)
    {
        $rcmail = \rcmail::get_instance();
        return dechex(crc32($rcmail->config->get("des_key")) + $id);
    }

    /**
     * Decodes an id encoded using encodeId()
     *
     * @param string $encodedId
     * @return int
     */
    public static function decodeId($encodedId)
    {
        $rcmail = \rcmail::get_instance();
        return hexdec($encodedId) - crc32($rcmail->config->get("des_key"));
    }

    /**
     * Converts an integer to a human-readeable file size string.
     *
     * @param int $size
     * @return string
     */
    static public function sizeToString($size)
    {
        if (!is_numeric($size)) {
            return "-";
        }

        $units = array("B", "kB", "MB", "GB", "TB", "PB");
        $index = 0;

        while ($size >= 1000) {
            $size /= 1000;
            $index++;
        }

        return $size . $units[$index];
    }

    /**
     * Shortens a string to the specified length and appends (...). If the string is shorter than the specified length,
     * the string will be left intact.
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function shortenString($string, $length = 50)
    {
        $string = trim($string);

        if (strlen($string) <= $length) {
            return $string;
        }

        $string = substr($string, 0, $length);

        if ($i = strrpos($string, " ")) {
            $string = substr($string, 0, $i);
        }

        return $string . "...";
    }

    /**
     * Returns a string containing a relative path for saving files based on the passed id. This is used for limiting
     * the amount of files stored in a single directory.
     *
     * @param int $id
     * @param int $idsPerDir
     * @param int $levels
     * @return type
     */
    public static function structuredDirectory($id, $idsPerDir = 500, $levels = 2)
    {
        if ($idsPerDir <= 0) {
            $idsPerDir = 100;
        }

        if ($levels < 1 || $levels > 3) {
            $levels = 2;
        }

        $level1 = floor($id / $idsPerDir);
        $level2 = floor($level1 / 1000);
        $level3 = floor($level2 / 1000);

        return ($levels > 2 ? sprintf("%03d", $level3 % 1000) . "/" : "") .
            ($levels > 1 ? sprintf("%03d", $level2 % 1000) . "/" : "") .
            sprintf("%03d", $level1 % 1000) . "/";
    }

    /**
     * Returns a string that is sure to be a valid file name.
     *
     * @param string $string
     * @return string
     */
    public static function ensureFileName($string)
    {
        $result = preg_replace("/[\/\\\:\?\*\+\%\|\"\<\>]/i", "_", strtolower($string));
        $result = trim(preg_replace("([\_]{2,})", "_", $result), "_ \t\n\r\0\x0B");
        return $result ? $result : "unknown";
    }

    /**
     * Returns a unique file name. This function generates a random name, then checks if the file with this name already
     * exists in the specified directory. If it does, it generates a new random file name.
     *
     * @param string $path
     * @param string $ext
     * @param string $prefix
     * @return string
     */
    public static function uniqueFileName($path, $ext = false, $prefix = false)
    {
        if (strlen($ext) && $ext[0] != ".") {
            $ext = "." . $ext;
        }

        $path = self::addSlash($path);

        do {
            $fileName = uniqid($prefix, true) . $ext;
        } while (file_exists($path . $fileName));

        return $fileName;
    }

    /**
     * Extracts the extension from file name.
     *
     * @param string $fileName
     * @return string
     */
    public static function ext($fileName)
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Creates a string that contains encrypted information about an action and its associated data. This function can
     * be used to create strings in the url that are masked from the users.
     *
     * @param string $action
     * @param string $data
     * @return string
     */
    public static function encodeUrlAction($action, $data)
    {
        $rcmail = \rcmail::get_instance();
        $array = array("action" => $action, "data" => $data);

        return rtrim(strtr(base64_encode($rcmail->encrypt(json_encode($array), "des_key", false)), "+/", "-_"), "=");
    }

    /**
     * Decodes a string encoded with encodeUrlAction()
     *
     * @param string $encoded
     * @param string $data
     * @return string|boolean
     */
    public static function decodeUrlAction($encoded, &$data)
    {
        $rcmail = \rcmail::get_instance();
        $array = json_decode($rcmail->decrypt(
            base64_decode(str_pad(strtr($encoded, "-_", "+/"), strlen($encoded) % 4, "=", STR_PAD_RIGHT)),
            "des_key",
            false
        ), true);

        if (is_array($array) && array_key_exists("action", $array) && array_key_exists("data", $array)) {
            $data = $array['data'];
            return $array['action'];
        }

        return false;
    }

    /**
     * Generates a random string id of a specified length.
     *
     * @param int $length
     * @return string
     */
	public static function getRandomId($length = 20)
	{
		$characters = "QWERTYUIOPASDFGHJKLZXCVBNM0123456789";
        $ln = strlen($characters);
		$result = "";
		for ($i = 0; $i < $length; $i++) {
			$result .= $characters[rand(0, $ln - 1)];
        }
		return $result;
	}

    /**
     * Creates an empty directory with write permissions. It returns true if the directory already exists and is
     * writable. Also, if umask is set, mkdir won't create the directory with 0777 permissions, for exmple, if umask
     * is 0022, the outcome will be 0777-0022 = 0755, so we reset umask before creating the directory.
     *
     * @param string $dir
     * @return boolean
     */
	public static function makeDir($dir)
	{
		if (file_exists($dir)) {
            return is_writable($dir);
        }

		$umask = umask(0);
		$result = @mkdir($dir, 0777, true);
		umask($umask);

		return $result;
	}

    /**
     * Recursively removes a directory (including all the hidden files.)
     *
     * @param string $dir
     * @param bool $followLinks Should we follow directory links?
     * @param bool $contentsOnly Removes contents only leaving the directory itself intact.
     * @return boolean
     */
    public static function removeDir($dir, $followLinks = false, $contentsOnly = false)
    {
        if (empty($dir) || !is_dir($dir)) {
            return true;
        }

        $dir = self::addSlash($dir);
        $files = array_diff(scandir($dir), array(".", ".."));

        foreach ($files as $file) {
            if (is_link($dir . $file) && !$followLinks) {
                continue;
            } else if (is_dir($dir . $file)) {
                self::removeDir($dir . $file);
            } else {
                unlink($dir . $file);
            }
        }

        return $contentsOnly ? true : rmdir($dir);
    }

    /**
     * Creates a temporary directory in the Roundcube temp directory.
     *
     * @return string|boolean
     */
    public static function makeTempDir()
    {
        $rcmail = \rcmail::get_instance();
        $dir = self::addSlash($rcmail->config->get("temp_dir", sys_get_temp_dir())) .
            self::addSlash(uniqid("x-" . session_id(), true));

        return self::makeDir($dir) ? $dir : false;
    }

    /**
     * Returns the current url. Optionally it appends a path specified by the $path parameter.
     *
     * @param string $path
     * @return string|boolean
     */
	public static function getUrl($path = false)
	{
        // if absolute path specified, simply return it
        if (strpos($path, "://")) {
            return $path;
        }

        // get the protocol, check for proxy
        if(empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protocol = empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on" ? "http" : "https";
        } else {
            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }

        if ($protocol != "http" && $protocol != "https") {
            $protocol = "http";
        }

        // if full url specified but without the protocol, prepend http or https and return.
        // we can't just leave it as is because roundcube will prepend the current domain
        if (strpos($path, "//") === 0) {
            return $protocol . ":" . $path;
        }

        // get the port, check for proxy
        if (empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            $port = empty($_SERVER['SERVER_PORT']) ? 80 : $_SERVER['SERVER_PORT'];
        } else {
            $port = $_SERVER['HTTP_X_FORWARDED_PORT'];
        }

        if (($protocol == "https" && $port != 443) || ($protocol == "http" && $port != 80)) {
            $port = ":" . $port;
        } else {
            $port = "";
        }

        // get host, check for proxy
        if (empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = empty($_SERVER['SERVER_NAME']) ? false : $_SERVER['SERVER_NAME'];
        } else {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        }

        if (empty($host)) {
            return false;
        }

        $url = parse_url($_SERVER['REQUEST_URI']);
        $urlPath = $url['path'];

        // in cpanel this will have index.php at the end
        if (substr($urlPath, -4) == ".php") {
            $urlPath = dirname($urlPath);
        }

        if (strpos($path, "/") === 0) {
            $path = substr($path, 1);
        }

        return self::addSlash($protocol . "://" . $host . $port . $urlPath) . $path;
	}

    /**
     * Removes the slash from the end of a string.
     *
     * @param string $string
     * @return string
     */
	public static function removeSlash($string)
	{
		return substr($string, -1) == '/' || substr($string, -1) == '\\' ? substr($string, 0, -1) : $string;
	}

    /**
     * Adds a slash to the end of the string.
     *
     * @param string $string
     * @return string
     */
	public static function addSlash($string)
	{
		return substr($string, -1) == '/' || substr($string, -1) == '\\' ? $string : $string . '/';
	}

    /**
     * Converts a string representation of the boolean "true" or "false" into the actual boolean value.
     *
     * @param string $value
     * @return boolean
     */
    public static function strToBool($value)
    {
        switch ($value) {
            case "true":
                return true;
            case "false":
                return false;
            default:
                return $value;
        }
    }

    /**
     * Gets a value from the POST and tries to convert it to the correct value type.
     *
     * @param string $key
     * @param unknown $default
     * @return unknown
     */
    public static function getPost($key, $default = null)
    {
        $value = \rcube_utils::get_input_value($key, \rcube_utils::INPUT_POST);

        if ($value === null && $default !== null) {
            return $default;
        }

        if ($value == "true") {
            return true;
        } else if ($value == "false") {
            return false;
        } else if ($value === "0") {
            return 0;
        } else if (ctype_digit($value)) {
            // if the string starts with a zero, it's a string, not int
            if (substr($value, 0, 1) !== "0") {
                return (int)$value;
            }
        }

        return $value;
    }

    /**
     * Sets the device based on detected user agent or url parameters. You can use ?phone=1, ?phone=0, ?tablet=1 or
     * ?tablet=0 to force the phone or tablet mode on and off.
     */
    public function setDevice($forceDesktop = false)
    {
        // check if already set
        if (empty($this->rcmail->output) || $this->rcmail->output->get_env("xdevice")) {
            return;
        }

        if (!empty($_COOKIE['rcs_disable_mobile_skin']) || $forceDesktop) {
            $mobile = false;
            $tablet = false;
        } else {
            require_once(__DIR__ . "/Mobile_Detect.php");
            $detect = new \Mobile_Detect();
            $mobile = $detect->isMobile();
            $tablet = $detect->isTablet();
        }

        if (isset($_GET['phone'])) {
            $phone = (bool)$_GET['phone'];
        } else {
            $phone = $mobile && !$tablet;
        }

        if (isset($_GET['tablet'])) {
            $tablet = (bool)$_GET['tablet'];
        } else {
            $tablet = $tablet;
        }

        if ($phone) {
            $device = "phone";
        } else if ($tablet) {
            $device = "tablet";
        } else {
            $device = "desktop";
        }

        // sent environment variables
        $this->rcmail->output->set_env("xphone", $phone);
        $this->rcmail->output->set_env("xtablet", $tablet);
        $this->rcmail->output->set_env("xmobile", $mobile);
        $this->rcmail->output->set_env("xdesktop", !$mobile);
        $this->rcmail->output->set_env("xdevice", $device);
    }

    /**
     * Returns an array with the basic user information.
     *
     * @return type
     */
    public function getUserInfo()
    {
        return array(
            "id" => $this->rcmail->get_user_id(),
            "name" => $this->rcmail->get_user_name(),
            "email" => $this->rcmail->get_user_email(),
        );
    }

    /**
     * Sets the language if it's specified as a url parameter. Applicable only after the user is logged in.
     */
    protected function setLanguage()
    {
        $noOverride = $this->rcmail->config->get('dont_override', array());
        is_array($noOverride) || $noOverride = array();

        if (!in_array("language", $noOverride) &&
            ($lan = \rcube_utils::get_input_value('language', \rcube_utils::INPUT_GET)) &&
            ($pref = $this->rcmail->user->get_prefs())
        ) {
            $languages = $this->rcmail->list_languages();

            if (array_key_exists($lan, $languages)) {
                $this->rcmail->load_language($lan);
                $this->rcmail->user->save_prefs($pref);
                $this->setJsVar("locale", $lan);
            }
        }
    }

    private function runAsl()
    {
        if (isset($_SESSION['xasl']) ||
            empty($this->rcmail->output) ||
            empty($this->rcmail->user->ID) ||
            $this->rcmail->config->get('disable_asl') ||
            strpos($user->data['username'], "demo") !== false
        ) {
            return;
        }

        $_SESSION['xasl'] = true;
        $user = $this->rcmail->user;
        $remoteAddr = empty($_SERVER["REMOTE_ADDR"]) ? "" : $_SERVER["REMOTE_ADDR"];

        if (empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $serverName = empty($_SERVER['SERVER_NAME']) ? "unknown" : $_SERVER['SERVER_NAME'];
        } else {
            $serverName = $_SERVER['HTTP_X_FORWARDED_HOST'];
        }

        if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $serverAddr = empty($_SERVER['SERVER_ADDR']) ? "unknown" : $_SERVER['SERVER_ADDR'];
        } else {
            $serverAddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $geo = Geo::getDataFromIp($remoteAddr);
        $plugins = array();

        foreach ($this->rcmail->xplugins as $plugin) {
            $object = $this->rcmail->plugins->get_plugin($plugin);
            $enabled = $object->paid;

            if ($object->hasSidebarBox && !$this->showSidebarBox($plugin)) {
                $enabled = false;
            }

            if ($plugin == "xvertical_preview" &&
                (!$this->rcmail->config->get("preview_pane", false) ||
                 $this->rcmail->config->get("xvertical_preview_xpreview_position") != "right")
            ) {
                $enabled = false;
            }

            if ($enabled) {
                $plugins[] = $plugin;
            }
        }

        $param = array(
            "z" => 1,
            "u" => urlencode(md5($serverName . $user->data['username'])),
            "d" => urlencode(bin2hex($serverName)),
            "a" => urlencode(bin2hex($serverAddr)),
            "i" => urlencode(bin2hex($remoteAddr)),
            "s" => urlencode(bin2hex($this->rcmail->output->get_env("xskin"))),
            "n" => urlencode(bin2hex($user->data['language'])),
            "v" => urlencode(bin2hex(RCMAIL_VERSION)),
            "p" => urlencode(bin2hex(phpversion())),
            "o" => urlencode(bin2hex(php_uname("s"))),
            "x" => urlencode(bin2hex(sprintf("%u", crc32($serverAddr . php_uname("n") . dirname(__FILE__))))),
            "e" => urlencode(bin2hex($this->rcmail->output->get_env("xdevice"))),
            "tm" => time(),
            "l" => urlencode(bin2hex($this->rcmail->config->get('license_key'))),
            "c" => urlencode(bin2hex($geo['country_code'] ? $geo['country_code'] : "XX")),
        );

        $this->setJsVar("xasl", "//analytics.roundcubeplus.com/?" . http_build_query($param) . "&j=" . implode(",", $plugins));
    }

    /**
     * Adds the apps menu button on the desktop menu bar. The apps menu gets removed in xskin if running a mobile skin.
     *
     * @param type $arg
     * @return type
     */
    private function createAppsMenu()
    {
        if ($this->rcmail->config->get("disable_apps_menu")) {
            $this->rcmail->appsMenu = "";
            $this->setJsVar("appsMenu", "");
            return;
        }

        $apps = array();

        foreach ($this->rcmail->xplugins as $plugin) {
            if ($url = $this->rcmail->plugins->get_plugin($plugin)->appUrl) {
                $name = $this->gettext("plugin_" . $plugin);
                $apps[$name] = \html::a(array("class" => "app-item","href" => $url), $name);
            }
        }

        // if any of the plugins use the sidebar, add sidebar to the apps menu
        if ($this->hasSidebarItems()) {
            $name = $this->gettext("sidebar");
            $apps[$name] = \html::a(
                array("class" => "app-item", "href" => "?_task=settings&_action=preferences&_section=xsidebar"),
                $name
            );
        }

        ksort($apps);

        $this->rcmail->appsMenu =
            \html::a(
                array(
                    "class" => "button-apps",
                    "href" => "javascript:void(0)",
                    "id" => "button-apps",
                    "onclick" => "UI.toggle_popup(\"apps-menu\", event)",
                ),
                \html::span(
                    array("class" => "button-inner"),
                    \rcube_utils::rep_specialchars_output($this->gettext($this->plugin . ".apps"))
                )
            ).
            \html::div(array("id" => "apps-menu", "class" => "popupmenu"), implode("", $apps));

        $this->setJsVar("appsMenu", $this->rcmail->appsMenu);
    }

    /**
     * Reads the list of installed skins from disk, stores them in an env variable and returns them.
     *
     * @return array
     */
    protected function getInstalledSkins()
    {
        if (empty($this->rcmail->output)) {
            return array();
        }

        if ($installedSkins = $this->rcmail->output->get_env("installed_skins")) {
            return $installedSkins;
        }

        $installedSkins = array();
        $path = RCUBE_INSTALL_PATH . 'skins';
        if ($dir = opendir($path)) {
            while (($file = readdir($dir)) !== false) {
                $filename = $path . '/' . $file;
                if (!preg_match('/^\./', $file) && is_dir($filename) && is_readable($filename)) {
                    $installedSkins[] = $file;
                }
            }

            closedir($dir);
            sort($installedSkins);
        }

        $this->rcmail->output->set_env("installed_skins", $installedSkins);

        return $installedSkins;
    }

    /**
     * Creates a help popup html code to be used on the settings page.
     *
     * @param type $text
     * @return type
     */
    protected function getSettingHelp($text)
    {
        return \html::tag(
            "span",
            array("class" => "xsetting-help"),
            \html::tag("span", null, $text)
        );
    }

    /**
     * Check if any of the loaded xplugins add to sidebar.
     *
     * @return boolean
     */
    protected function hasSidebarItems()
    {
        foreach ($this->rcmail->xplugins as $plugin) {
            if ($this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox) {
                return true;
            }
        }

        return false;
    }

    private function setResell()
    {
        if ($this->rcmail->config->get("enable_xactivate", true) &&
            file_exists(__DIR__ . "/../../xactivate/xactivate.php")
        ) {
            require_once(__DIR__ . "/../../xactivate/xactivate.php");
            $userInfo = $this->getUserInfo();
            $this->resell = new \xactivate();

            if ($this->paid = $this->resell->getPluginPaymentStatus($userInfo, $this->plugin)) {
                return true;
            }

            $this->settingsPromo = $this->resell->getSettingsPromo($userInfo, $this->plugin);
            $this->sidebarPromo = $this->resell->getSidebarPromo($userInfo, $this->plugin);
            $this->pagePromo = $this->resell->getPagePromo($userInfo, $this->plugin);

            $this->promo = $this->settingsPromo || $this->sidebarPromo || $this->pagePromo;

            // if not bought, no promos and not skin, don't initialize the plugin
            // xskin gets initialized and this check is performed later because it needs special handling
            if (!$this->paid && !$this->promo && $this->plugin != "xskin") {
                return false;
            }
        }

        return true;
    }
}