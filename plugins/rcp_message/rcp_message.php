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

class rcp_message extends rcube_plugin
{
    // all task excluding 'login' and 'logout'
    public $task = '?(?!login|logout).*|utils';



    public $rc;
    public $home;
    private $engine;


    public function init()
    {
        // Register task
        //$this->register_task('rcp_message');

        $this->register_action('plugin.rcp_message.sendmsg', array($this, 'send_message'));
        $this->register_action('plugin.rcp_message.newmsg', array($this, 'get_new_message'));
        $this->register_action('plugin.rcp_message.readmsg', array($this, 'read_message'));
        $this->register_action('plugin.rcp_message.querymsg', array($this, 'query_message'));
        $this->register_action('plugin.rcp_message.quersendymsg', array($this, 'query_send_message'));

        $this->register_action('plugin.rcp_message.removemsg', array($this, 'remove_message'));
        $this->register_action('plugin.rcp_message.searchmailbox', array($this, 'search_mailbox'));

        $this->add_hook('template_object_username', array($this, 'show_message_icon'));
        $this->add_hook('send_sinorama_notices', array($this, 'send_message'));
    }

    function show_message_icon($p){
        $rc = rcmail::get_instance();



        $msg_dia=file_get_contents("./plugins/rcp_message/hj_msg_dialog.html");
        $this->include_stylesheet("./hj_msg_dialog.css");
        $this->include_script("./rcp_message.js");

        $userlevel=0;


        $this->load_config();

        $cfg = rcmail::get_instance()->config->get('rcp_user_level', array());
        if (isset($cfg) && isset($cfg[$_SESSION["username"]])){
            $userlevel=$cfg[$_SESSION["username"]];
        }

        $foreshow=0;
        if (!isset($_SESSION["plugin.rcp_message.forceshow"])){
            $foreshow=1;
            $_SESSION["plugin.rcp_message.forceshow"]="yes";
        }
        $rc->output->add_script('load_hj_message('.$userlevel.',"'.substr(strrchr($_SESSION["username"], "@"), 1).'",'.$foreshow.');', 'docready');
        $rc->output->add_footer($msg_dia);

        return $p;
    }

    function send_message($args){
        $rcmail = rcmail::get_instance();
        $msg_content = rcube_utils::get_input_value('msg_content', rcube_utils::INPUT_GPC);
        $confirm_type = rcube_utils::get_input_value('confirm_type', rcube_utils::INPUT_GPC);
        $msg_receiver = rcube_utils::get_input_value('msg_receiver', rcube_utils::INPUT_GPC);
        $send_to_user_type = rcube_utils::get_input_value('send_to_user_type', rcube_utils::INPUT_GPC);
        $out_expiredate = rcube_utils::get_input_value('out_expiredate', rcube_utils::INPUT_GPC);

        if(isset($msg_content) && strlen($msg_content) >0 && $msg_content!=""){
            //do nothing
        }elseif(isset($args["msg_content"])  && isset($args["msg_receiver"])){
            $msg_content=$args["msg_content"];
            $msg_receiver=$args["msg_receiver"];

            if (isset($args["confirm_type"])){
                $confirm_type=$args["confirm_type"];
            }else{
                $confirm_type="1";
            }

            $send_to_user_type="USERS";

            if (isset($args["out_expiredate"])){
                $out_expiredate=$args["out_expiredate"];
            }else{
                $out_expiredate=date('Y-m-d', strtotime('+2 months'));
            }

        }else{
            //go back
            return ;
        }

        $tmpdate = new DateTime($out_expiredate);
        $out_expiredate= $tmpdate->format('Y-m-d 23:59:59');

        $sql="insert into rcp_message_list values(null,? ,1,?,?,?,?,now())";
        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $msg_content,$_SESSION["username"],$send_to_user_type,$out_expiredate,$confirm_type);
        $msgid=$db->insert_id();

        $receiverlist=explode(",",$msg_receiver);

        foreach($receiverlist as $item){
            $item=trim(preg_replace('/\s\s+/', ' ', $item));
            if  (!isset($item) ||  trim($item)=="") continue;
            $sql2="insert into rcp_message_confirm_list values(?,?,1,0,now(),now())";

            $sql_result = $db->query($sql2,$item,$msgid);
        }

        if (isset($args["status"]) && $args["status"]=="sending"){
            $args["status"]="success";
            return $args;
        }else{
            echo "success";
            exit;
        }

    }

    function read_new_message(){
        $rcmail = rcmail::get_instance();

        $username=$_SESSION["username"];
        $domainname=substr(strrchr($username, "@"), 1);

        $sql="select a.* from (select * from rcp_message_list  where status=1 and receiver='ALL' AND expiredate >=now()) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and b.username=? where b.username is NULL".
            " union ".
            "select a.* from (select * from rcp_message_list  where status=1 and receiver='DOMAIN' AND expiredate >=now()) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and a.sender like '%@".$domainname."' and b.username=? where b.username is NULL".
            " union ".
            "select a.* from (select * from rcp_message_list  where status=1 and receiver='USERS' AND expiredate >=now()) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and b.username=? where b.username is not NULL and b.confirmstatus=0";
        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $username,$username,$username);

        $rs=Array();
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs[]=$sql_arr;
        }
        return $rs;
    }

    function get_new_message($args){
        $rs=$this->read_new_message();
        echo json_encode($rs);
        exit;
    }

    function read_message($args){
        $rcmail = rcmail::get_instance();
        $msgid = rcube_utils::get_input_value('msgid', rcube_utils::INPUT_GPC);
        $sql="replace into rcp_message_confirm_list values(? , ? , 1 , 1, now(),now())";
        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $_SESSION["username"],$msgid);
        $rs=$this->read_new_message();
        echo json_encode($rs);
        exit;

    }

    function remove_message($args){
        $rcmail = rcmail::get_instance();
        $msgid = rcube_utils::get_input_value('msgid', rcube_utils::INPUT_GPC);
        $sql="update rcp_message_list set status=0 where msgid= ?";
        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $msgid);
        echo json_encode(array("success"));
        exit;
    }

    function query_send_message($args){
        $rcmail = rcmail::get_instance();
        $startdatepicker = rcube_utils::get_input_value('startdatepicker', rcube_utils::INPUT_GPC);
        $enddatepicker = rcube_utils::get_input_value('enddatepicker', rcube_utils::INPUT_GPC);
        $tmpdate = new DateTime($startdatepicker);
        $startdatepicker= $tmpdate->format('Y-m-d 00:00:00');

        $tmpdate = new DateTime($enddatepicker);
        $enddatepicker= $tmpdate->format('Y-m-d 23:59:59');


        $username=$_SESSION["username"];

        $sql="select * from rcp_message_list where sender = ? and status=1 and createtime BETWEEN ? and ?";

        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $username , $startdatepicker,$enddatepicker);

        $rs=Array();
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs[]=$sql_arr;
        }
        echo json_encode($rs);
        exit;
    }

    function query_message($args){
        $rcmail = rcmail::get_instance();
        $startdatepicker = rcube_utils::get_input_value('startdatepicker', rcube_utils::INPUT_GPC);
        $enddatepicker = rcube_utils::get_input_value('enddatepicker', rcube_utils::INPUT_GPC);
        $message_readstatus = rcube_utils::get_input_value('message_readstatus', rcube_utils::INPUT_GPC);

        $tmpdate = new DateTime($startdatepicker);
        $startdatepicker= $tmpdate->format('Y-m-d 00:00:00');

        $tmpdate = new DateTime($enddatepicker);
        $enddatepicker= $tmpdate->format('Y-m-d 23:59:59');


        $username=$_SESSION["username"];
        $domainname=substr(strrchr($username, "@"), 1);

        $readcondition_all="";
        $readcondition_unread="";
        $readcondition_read="";

        if ($message_readstatus==1){
            $readcondition_all=" where  b.username is NULL ";
            $readcondition_unread=" where b.username is NULL ";
            $readcondition_read=" and b.confirmstatus=0";

        }
        if ($message_readstatus==2){
            $readcondition_all=" where  b.username is not NULL ";
            $readcondition_unread=" where b.username is not NULL ";
            $readcondition_read=" and b.confirmstatus=1";
        }


        $sql="select a.*,b.confirmstatus from (select * from rcp_message_list  where status=1 and receiver='ALL' and createtime BETWEEN ? and ?) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and b.username=? ". $readcondition_all .
            " union ".
            "select a.*,b.confirmstatus from (select * from rcp_message_list  where status=1 and receiver='DOMAIN' and createtime BETWEEN ? and ?) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and a.sender like '%@".$domainname."' and b.username=? ". $readcondition_unread .
            " union ".
            "select a.*,b.confirmstatus from (select * from rcp_message_list  where status=1 and receiver='USERS' and createtime BETWEEN ? and ?) a left join rcp_message_confirm_list b on a.msgid=b.msgid  and b.username=? where b.username is not NULL " .$readcondition_read;






        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql, $startdatepicker,$enddatepicker,$username,$startdatepicker,$enddatepicker,$username,$startdatepicker,$enddatepicker,$username);

        $rs=Array();
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs[]=$sql_arr;
        }
        echo json_encode($rs);
        exit;
    }

    function search_mailbox($args){
        $rcmail = rcmail::get_instance();
        $searchkey = rcube_utils::get_input_value('term', rcube_utils::INPUT_GPC);
        $sql="select distinct username from users where username like '%".$searchkey."%'";
        $db = $rcmail->get_dbh();
        $sql_result = $db->query($sql);


        $rs=Array();
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs[]=$sql_arr["username"];
        }
        echo json_encode($rs);
        exit;
    }

}
