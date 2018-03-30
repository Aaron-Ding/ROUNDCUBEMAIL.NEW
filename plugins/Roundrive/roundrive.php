<?php

/**
 * Roundcube Drive using flysystem for filesystem
 *
 * @version @package_version@
 * @author Thomas Payen <thomas.payen@apitech.fr>
 *
 * This plugin is inspired by kolab_files plugin
 * Use flysystem library : https://github.com/thephpleague/flysystem
 * With flysystem WebDAV adapter : https://github.com/thephpleague/flysystem-webdav
 *
 * Copyright (C) 2015 PNE Annuaire et Messagerie MEDDE/MLETR
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class roundrive extends rcube_plugin
{
    // all task excluding 'login' and 'logout'
    public $task = '?(?!login|logout).*|utils';



    public $rc;
    public $home;
    private $engine;


    public function init()
    {
        $this->rc = rcube::get_instance();





        // Register hooks
        $this->add_hook('refresh', array($this, 'refresh'));


        // Plugin actions for other tasks
        $this->register_action('plugin.roundrive', array($this, 'actions'));





        // Register task
        $this->register_task('roundrive');

        // Register plugin task actions
        $this->register_action('index', array($this, 'actions'));
        $this->register_action('prefs', array($this, 'actions'));
        $this->register_action('open',  array($this, 'actions'));
        $this->register_action('file_api', array($this, 'actions'));
        $this->register_action('file_upload', array($this, 'file_upload'));
        $this->register_action('check_attchment_size', array($this, 'check_size'));

        // Load UI from startup hook
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('attachment_upload', array($this, 'check_size'));

        $this->add_hook('message_compose_body', array($this, 'message_compose_body'));



        $this->add_hook('attachment_delete', array($this, 'attachment_delete'));
        $this->add_hook('message_outgoing_headers', array($this, 'message_outgoing_body_real_remove_attachments'));

        $this->add_hook('upload_files_to_cloud', array($this, 'upload_files_to_cloud'));


        $this->add_hook('password_change', array($this, 'password_change'));






        $this->load_config();
    }

    public function message_compose_body($args){
        $COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
        $this->rc->output->add_script('rcmail.check_attchemnts("'.$COMPOSE_ID.'")','docready');
    }



    public function upload_files_to_cloud($args){
        return $this->engine()->action_upload_files_to_cloud($args["dir1"],$args["dir2"],$args["files"]);

    }

    public function file_upload($args){

        if (is_array($_FILES['file']['tmp_name'])) {
            $this->engine()->file_upload($_FILES['file']);
            $result = array(
                'status' => 'OK',
                'req_id' => rcube_utils::get_input_value('req_id', rcube_utils::INPUT_GET),
                'msg'=>'upload ' . $_FILES['file']['name'][0] . " success"
            );
            echo json_encode($result);
            exit;
        }
    }

    public function message_outgoing_body_real_remove_attachments($args){

        $COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
        $file_list=array();
        $total=0;
        $limit = parse_bytes(rcmail::get_instance()->config->get('max_message_size', '10MB'));
        if ($_SESSION['compose_data_' . $COMPOSE_ID] && $_SESSION['compose_data_' . $COMPOSE_ID]['attachments']) {
            foreach ($_SESSION['compose_data_' . $COMPOSE_ID]['attachments'] as $attachment){
                    $total += $attachment['size'];
                    $tmpobj=array("name"=>$attachment['name'],"tmp_name"=>$attachment['path'],"size"=>show_bytes(parse_bytes($attachment['size'])),"link"=>$attachment['link'],"id"=>$attachment['id']);
                    $file_list[]=$tmpobj;
            }
        }
        if ($total > $limit){

            unset($_SESSION['compose_data_' . $COMPOSE_ID]['attachments']);
            foreach($file_list as $fileitem){
                unlink($fileitem["tmp_name"]);
            }
        }
    }

    public function attachment_delete($args){
        $COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
        $file_list=array();
        $total=0;
        $limit = parse_bytes(rcmail::get_instance()->config->get('max_message_size', '10MB'));
        if ($_SESSION['compose_data_' . $COMPOSE_ID] && $_SESSION['compose_data_' . $COMPOSE_ID]['attachments']) {
            foreach ($_SESSION['compose_data_' . $COMPOSE_ID]['attachments'] as $attachment){
                if ($attachment["path"]!=$args["path"]){
                    $total += $attachment['size'];
                    $tmpobj=array("name"=>$attachment['name'],"tmp_name"=>$attachment['path'],"size"=>show_bytes(parse_bytes($attachment['size'])),"link"=>$attachment['link'],"id"=>$attachment['id']);
                    $file_list[]=$tmpobj;
                }
            }
        }

        if ($total < $limit) $file_list=array();

        $this->rc->output->command('add_cloud_attchments_to_mail', json_encode($file_list));

    }


    public function check_size($args)
    {

        $COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
        $return_type = rcube_utils::get_input_value('return_type', rcube_utils::INPUT_GPC);
        if (!$COMPOSE_ID){
            $COMPOSE_ID = rcube_utils::get_input_value('id', rcube_utils::INPUT_POST);
        }

        $limit = parse_bytes(rcmail::get_instance()->config->get('max_message_size', '10MB'));
        $total = $args['size'];
        $tmpobj=array("name"=>$args['name'],"tmp_name"=>$args['path'],"size"=>show_bytes(parse_bytes($args['size'])),"link"=>"","id"=>$args["id"]);

        $file_list=array();
        if ($total && $total>0){
            $file_list[]=$tmpobj;
        }

        if ($_SESSION['compose_data_' . $COMPOSE_ID] && $_SESSION['compose_data_' . $COMPOSE_ID]['attachments']) {
            foreach ($_SESSION['compose_data_' . $COMPOSE_ID]['attachments'] as $attachment){
                if ($attachment['name']=="") continue;
                $total += $attachment['size'];
                $tmpobj=array("name"=>$attachment['name'],"tmp_name"=>$attachment['path'],"size"=>show_bytes(parse_bytes($attachment['size'])),"link"=>$attachment['link'],"id"=>$attachment['id']);
                $file_list[]=$tmpobj;
            }

        }

        if ($total > $limit) {
            //$this->rc->output->reset();
            //$SESSION_KEY = 'compose_data_' . $COMPOSE_ID;
            //$this->rc->session->append($SESSION_KEY.'.attachments', $args["id"], $args);
            //total is up then limit ,so use link attachemnts

            $error=0;
            if ($engine = $this->engine()) {
                $file_list=$engine->action_upload_attachment_to_cloud($file_list);
                foreach($file_list as $fileitem){
                    if ($fileitem["id"]!=""){
                        $_SESSION['compose_data_' . $COMPOSE_ID]['attachments'][$fileitem["id"]]["link"] = $fileitem["link"];
                    }
                    if ($args['path']==$fileitem["tmp_name"]){
                        $args["link"]=$fileitem["link"];
                    }
                    if (!isset($fileitem["link"]) || $fileitem["link"]==""){
                        $error++;
                    }
                }
            }
            $this->add_texts('localization/');

            if ($error==0){
                $msg = sprintf($this->gettext('overallsizeerror'), show_bytes(parse_bytes($limit)),'');
                $this->rc->output->command('display_message', $msg, 'notice');
                $this->rc->output->command('add_cloud_attchments_to_mail', json_encode($file_list));
                if (isset($return_type) && $return_type=="JSON"){
                    $jsonrs=array();
                    $jsonrs["msg"]=$msg;
                    $jsonrs["files"]=json_encode($file_list);
                    echo json_encode($jsonrs);
                    exit;
                }
            }else{
                $args['error'] = "Maybe your account has exception. Please contact the administrator Xiaoqiang Wang!!!!";
                $args['abort'] = true;
                if (isset($return_type) && $return_type=="JSON"){
                    $jsonrs=array();
                    $jsonrs["msg"]=$args['error'];
                    $jsonrs["error"]=true;
                    echo json_encode($jsonrs);
                    exit;
                }
            }
            //$args['status'] = 'OK';
        }
        return $args;
    }

    /**
     * Creates roundrive_engine instance
     */
    private function engine()
    {
        if ($this->engine === null) {
            $this->load_config();


            require_once $this->home . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'roundrive_files_engine.php';

            $this->engine = new roundrive_files_engine($this);
        }

        return $this->engine;
    }

    /**
     * Startup hook handler, initializes/enables Files UI
     */
    public function startup($args)
    {
        // call this from startup to give a chance to set
        $this->ui();
    }

    /**
     * Adds elements of files API user interface
     */
    private function ui()
    {
        if ($this->rc->output->type != 'html') {
            return;
        }

        if ($engine = $this->engine()) {
            $engine->ui();
        }
    }

    /**
     * Refresh hook handler
     */
    public function refresh($args)
    {

        // Here we are refreshing API session, so when we need it
        // the session will be active
        if ($engine = $this->engine()) {
        }

        return $args;
    }

    public function password_change($args)
    {
        $owncloud_public_account_password=$this->rc->config->get('owncloud_public_account_password');
        if ($engine = $this->engine()) {
            if (!isset($owncloud_public_account_password[$_SESSION["username"]])){
                $engine->resetUserPassword($_SESSION["username"],$args["new_pass"]);
            }
        }
    }

    /**
     * Engine actions handler
     */
    public function actions()
    {

        if ($engine = $this->engine()) {
            $engine->actions();
        }
    }
}
