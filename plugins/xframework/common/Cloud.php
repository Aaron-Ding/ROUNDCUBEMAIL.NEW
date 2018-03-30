<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This file provides the base class for all the cloud-storage-related plugins.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/Plugin.php");

abstract class Cloud extends Plugin
{
    protected $supportSelect = false;
    protected $supportSave = false;

    abstract protected function enabled();
    abstract protected function downloadFile($file, &$errorMessage);

    /**
     * Initializes the plugin.
     */
    public function initialize()
    {
        if (!$this->paid) {
            return;
        }

        $this->load_config();

        if (!$this->enabled()) {
            return;
        }

        if ($this->supportSelect) {
            $this->register_action($this->plugin . "_attach", array($this, 'attachFiles'));
            if ($this->rcmail->task == "mail" && $this->rcmail->action == "compose") {
                $this->add_hook("render_page", array($this, "renderPage"));
            }
        }

        if ($this->supportSave) {
            // output attachment files requested by the cloud service, notice that the request from the server
            // is not logged in, so we need to serve it outside of the normal roundcube routing
            if (\rcube_utils::get_input_value("xcloud_save", \rcube_utils::INPUT_GET)) {
                $this->deployAttachment();
            }

            if ($this->rcmail->action == $this->plugin . "SaveAttachment") {
                $this->saveAttachment();
            }
        }

        $this->add_hook('startup', array($this, 'startup'));

        $this->includeAsset("xframework/assets/plugin/xcloud.min.js");
        $this->includeAsset("xframework/assets/plugin/xcloud.css");
        $this->includeAsset("assets/plugin/plugin" . ($this->debug ? "" : ".min") . ".js");

        $this->rcmail->output->add_label("errorsaving", "save");

        // create a list of all cloud plugins

        if (empty($this->rcmail->Plugins)) {
            $this->rcmail->Plugins = array();
        }

        $this->rcmail->cloudPlugins[$this->plugin] = array(
            "supportSelect" => $this->supportSelect,
            "supportSave" => $this->supportSave,
        );
    }

    /**
     * Handles the startup hook.
     */
    public function startup()
    {
        // send the list of cloud-related plugins to frontend
        $this->setJsVar("xcloud_plugins", $this->rcmail->cloudPlugins);
    }

    /**
     * Adds the button the to attach area on the compose page.
     *
     * @param array $arg
     * @return array
     */
    public function renderPage($arg)
    {
        if ($i = strpos($arg['content'], "aria-label-composeattachments")) {
            if ($j = strpos($arg['content'], "<input", $i)) {
                if ($k = strpos($arg['content'], ">", $j)) {
                    $view = $this->view(
                        "xframework.compose_button",
                        array(
                            "plugin" => $this->plugin,
                            "labelServiceName" => \rcube_utils::rep_specialchars_output($this->gettext($this->plugin . "_name", $this->plugin)),
                            "labelInsertLink" => \rcube_utils::rep_specialchars_output($this->gettext("insert_link", $this->plugin)),
                            "labelDownloadAndAttach" => \rcube_utils::rep_specialchars_output($this->gettext("download_and_attach", $this->plugin)),
                        )
                    );
                    $arg['content'] = substr_replace($arg['content'], $view, $k + 1, 0);
                }
            }
        }

        return $arg;
    }

    /**
     * Checks the size of the file to download from cloud and attach. The size can't be larger than the php upload size
     * limit and it must fit in the memory.
     *
     * @param type $size
     * @param type $errorMessage
     */
    public function checkAttachFileSize($size, &$errorMessage)
    {
        $allowedSize = parse_bytes(ini_get("upload_max_filesize"));
        if ($size > $allowedSize) {
            $errorMessage = $this->gettext(array(
                "name" => "filesizeerror",
                "vars" => array("size" => $this->rcmail->show_bytes($allowedSize))
            ));
            return false;
        }

        // TODO: check if there's enough memory to download the file (the downloaded files are handled in the memory)

        return true;
    }

    /**
     * Downloads the file from cloud service and attach it to the message. Some of the attachment handling code has been
     * adapted from program/steps/mail/attachments.inc.
     *
     * The classes inheriting from this class must provide the downloadFile() method
     */
    public function attachFiles()
    {
        $uploadId = \rcube_utils::get_input_value("uploadId", \rcube_utils::INPUT_POST);
        $composeId = \rcube_utils::get_input_value("composeId", \rcube_utils::INPUT_POST);
        $files = \rcube_utils::get_input_value("files", \rcube_utils::INPUT_POST);

        $compose = null;

        if ($composeId && $_SESSION['compose_data_' . $composeId]) {
            $sessionKey = 'compose_data_' . $composeId;
            $compose =& $_SESSION[$sessionKey];
        }

        if (!$compose) {
            exit("Invalid session var");
        }

        $this->rcmail->output->reset();

        try {
            if (empty($uploadId) || empty($composeId) || empty($files) || !is_array($files)) {
                throw new \Exception("Invalid upload data");
            }

            foreach ($files as $file) {
                $result = $this->downloadFile($file, $errorMessage);

                if (!is_array($result)) {
                    throw new \Exception($errorMessage);
                }

                // use the filesystem_attachments or the database_attachments plugin to process the file
                $attachment = $this->rcmail->plugins->exec_hook(
                    "attachment_save",
                    array(
                        'path' => false,
                        'data' => $result['data'],
                        'size' => $result['size'],
                        'name' => $result['name'],
                        'mimetype' => $result['mime'],
                        'group' => $composeId,
                    )
                );

                if (!$attachment['status'] || $attachment['abort']) {
                    throw new \Exception("Cannot save attachment");
                }

                unset($attachment['status'], $attachment['abort']);
                $this->rcmail->session->append("$sessionKey.attachments", $attachment['id'], $attachment);

                if (($icon = $compose['deleteicon']) && is_file($icon)) {
                    $button = \html::img(array(
                        'src' => $icon,
                        'alt' => $this->rcmail->gettext("delete")
                    ));
                } else if ($compose['textbuttons']) {
                    $button = \rcube_utils::rep_specialchars_output($this->rcmail->gettext("delete"));
                } else {
                    $button = "";
                }

                $content = \html::a(array(
                    'href'    => "#delete",
                    'onclick' => sprintf(
                        "return %s.command('remove-attachment','rcmfile%s', this)",
                        \rcmail_output::JS_OBJECT_NAME, $attachment['id']
                    ),
                    'title'   => $this->rcmail->gettext('delete'),
                    'class'   => 'delete',
                    'aria-label' => $this->rcmail->gettext('delete') . ' ' . $attachment['name'],
                ), $button);

                $this->rcmail->output->command(
                    "add2attachment_list",
                    "rcmfile" . $attachment['id'],
                    array(
                        "html" => $content . \rcube_utils::rep_specialchars_output($attachment['name']),
                        "name" => $attachment['name'],
                        "mimetype"  => $attachment['mimetype'],
                        "classname" => \rcube_utils::file2class($attachment['mimetype'], $attachment['name']),
                        "complete"  => true
                    ),
                    $uploadId
                );
            }
        } catch (\Exception $e) {
            $message = $e->getMessage() ? $e->getMessage() : $this->gettext("fileuploaderror");
            $this->rcmail->output->command("display_message", $message, "error");
            $this->rcmail->output->command("remove_from_attachment_list", $uploadId);
        }

        $this->rcmail->output->send();
    }

    /**
     * Clears the files from the temporary directory that have never been uploaded to cloud (for example, the user
     * canceled the save dialog.)
     */
    public function removeUnusedAttachments()
    {
        $time = time();
        foreach (glob($this->addSlash($this->rcmail->config->get("temp_dir")) . "xcloud_save_*") as $file) {
            if ($time - filemtime($file) > 3600) {
                unlink($file);
            }
        }
    }

    /**
     * Send the temporary attachment file to the browser. Cloud requests this file directly from its server in order
     * to save it in the user's cloud folder.
     */
    public function deployAttachment()
    {
        try {
            if (!($id = \rcube_utils::get_input_value("xcloud_save", \rcube_utils::INPUT_GET))) {
                throw new \Exception();
            }

            $file = $this->addSlash($this->rcmail->config->get("temp_dir")) . "xcloud_save_" . $id;

            if (!file_exists($file) || !($size = filesize($file))) {
                throw new \Exception();
            }

            header("Content-Length: $size");
            header("Content-type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"cloud_attachment\"");
            header("Content-Transfer-Encoding: binary");

            @ob_clean();
            $fp = fopen($file, 'rb');

            while(!feof($fp)) {
                set_time_limit(30);
                echo fread($fp, 8192);
                flush();
                @ob_flush();
            }
            fclose($fp);
            unlink($file);
            $this->removeUnusedAttachments();
            exit();
        } catch (\Exception $e) {
            $this->sendResponse(false);
        }
    }

    /**
     * Saves the specified attachment to file in the temporary directory and returns its filename and id, so we can
     * construct the url from which cloud will fetch the attachment and save it.
     *
     * Cloud savers save files by connecting to a server and fetching files from the given urls. We can't simply
     * pass it the attachment download url, because those urls only work with the user is logged in, while Cloud
     * won't be logged in when it requests the file. So we save the file in the temporary directory and pass the
     * direct access url to the file via the url (?xcloud_save=[id]). After Cloud fetches the file,
     * we remove it from the temporary directory.
     */
    public function saveAttachment()
    {
        $handle = false;

        try {
            $uid = \rcube_utils::get_input_value("uid", \rcube_utils::INPUT_POST);
            $mbox = \rcube_utils::get_input_value("mbox", \rcube_utils::INPUT_POST);
            $mimeId = \rcube_utils::get_input_value("mimeId", \rcube_utils::INPUT_POST);

            if (!$uid || !$mbox || !$mimeId) {
                throw new \Exception();
            }

            if (!($message = new \rcube_message($uid, $mbox))) {
                throw new \Exception();
            }

            if (empty($message->mime_parts[$mimeId]) || (!$part = $message->mime_parts[$mimeId])) {
                throw new \Exception();
            }

            $file = $this->uniqueFileName($this->rcmail->config->get("temp_dir"), false, "xcloud_save_");
            $handle = fopen($file, "w");

            if ($handle === false) {
                throw new \Exception();
            }

            if ($message->get_part_body($mimeId, false, 0, $handle) === false) {
                throw new \Exception();
            }

            fclose($handle);
            $this->sendResponse(
                true,
                array(
                    "id" => substr(basename($file), 12),
                    "name" => $part->filename,
                    "message" => $this->gettext("successfullysaved")
                )
            );

        } catch (\Exception $e) {
            $handle && fclose($handle);
            $this->sendResponse(false, null, "Cannot save attachment");
        }
    }
}