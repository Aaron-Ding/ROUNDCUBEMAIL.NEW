<?php

/**
  * This plugin lets you impersonate another user using a master login. Only works with dovecot.
  * 
  * http://wiki.dovecot.org/Authentication/MasterUsers
  * 
  * @author Cor Bosman (roundcube@wa.ter.net)
  */

class rcp_public_mailbox extends rcube_plugin {
  //private $public_mailbox = array();
  private $behalf = '';
  private $reply_user_struct=null;
  private $messageout=0;

  private $resources=null;


  public function init() 
  {

      // Register task
    //$this->register_task('plugin.rpc_public_mailbox');

    $this->register_action('plugin.rpc_public_mailbox.switch_mailbox', array($this, 'switch_mailbox'));
    $this->register_action('plugin.rpc_public_mailbox.check_save_alias', array($this, 'alias_save'));

    $this->add_hook('storage_connect', array($this, 'impersonate'));
    $this->add_hook('managesieve_connect', array($this, 'impersonate'));
    $this->add_hook('authenticate', array($this, 'authenticate'));
    $this->add_hook('sieverules_connect', array($this, 'impersonate_sieve'));
    $this->add_hook('startup', array($this, 'startup'));

    $this->add_hook('preferences_list', array($this, 'prefs_list'));
    $this->add_hook('preferences_save', array($this, 'prefs_save'));

    $this->add_hook('template_object_username', array($this, 'add_dropdown_icon'));
    $this->add_hook('message_sent', array($this, 'log_sender'));
    $this->add_hook('message_load', array($this, 'log_load'));
    $this->add_hook('message_headers_output', array($this, 'message_headers'));

    $tmpusername=$_SESSION["username"];

    if (isset($_SESSION["plugin.rcp_public_mailbox.rawuser"])){
        $tmpusername=$_SESSION["plugin.rcp_public_mailbox.rawuser"];
    }
    $tmpdomain=substr(strrchr($tmpusername, "@"), 1);
    $this->resources=array();
    $rc = rcmail::get_instance();
    $tmpresources =$rc->config->get('resources_calendar',array());
    foreach($tmpresources as $row){
        if (in_array($tmpdomain,$row["domain"])){
            $this->resources[$row["email"]]=$row;
        }
    }



  }

  function add_dropdown_icon($p){
      $rc = rcmail::get_instance();

      $public_mailbox=$_SESSION["plugin.rcp_public_mailbox.list"];

      $this->include_script('rcp_public_mailbox.js');
      $rc->output->add_script('hide_about();', 'docready');
      if (isset($public_mailbox) && count($public_mailbox)>0 ){

          $opts=array();

          foreach($public_mailbox as $key=>$row){

              if (isset($row["isresources"])){
                  //this means it is a resources id

                  if(isset($this->resources[$row["username"]])){
                      $opts[$row["md5"]]=$this->resources[$row["username"]]["name"];
                  }
              }else{
                  $opts[$row["md5"]]=$row["username"];
              }



          }

          $sOpt='';
          foreach($opts as $val=>$text){
              if ($val==$_SESSION["plugin.rcp_public_mailbox.current"]){
                  $sOpt .= html::tag(
                      'option',
                      array("value"=>$val,"selected"=>"selected"),
                      rcube::Q($text)
                  );
              }else{
                  $sOpt .= html::tag(
                      'option',
                      array("value"=>$val),
                      rcube::Q($text)
                  );
              }

          }


          $sw = html::tag(
              'select',
              array(
                  'id' => 'plugin-ident_switch-account',
                  'style' => 'padding: 0;',
                  'onchange' => 'plugin_switchIdent_switch(this.value);',
              ),
              $sOpt
          );
          $rc->output->add_footer($sw);

          $this->include_stylesheet('rcp_public_mailbox.css');
          $rc->output->add_script('plugin_load_identity_list();', 'docready');

          if ($_SESSION["username"]!=$_SESSION["plugin.rcp_public_mailbox.rawuser"]){
              if(isset($this->resources[$_SESSION["username"]])){
                  $rc->output->add_script('showCalendarOnly();', 'docready');
              }
              else{
                  $rc->output->add_script('hide_setting();', 'docready');
              }
          }else{
              $rc->output->add_script('show_setting();', 'docready');
          }
      }
      return $p;
  }
  function switch_mailbox($data){
      $rc = rcmail::get_instance();
      $tomail=urldecode(rcube_utils::get_input_value('tomail', rcube_utils::INPUT_POST));

      $_SESSION["plugin.rcp_public_mailbox.current"]=$tomail;
      $curruser=$_SESSION["plugin.rcp_public_mailbox.list"][$_SESSION["plugin.rcp_public_mailbox.current"]];


      $_SESSION["username"]=$curruser['username'];

      $_SESSION["user_id"]=$curruser['userid'];
      $newuser=new rcube_user($_SESSION["user_id"]);
      $rc->user->ID=$_SESSION["user_id"];
      unset($rc->user->prefs);
      $rc->user->get_prefs();

      if (isset($this->resources[$curruser['username']])){
          $rc->output->redirect(
              array(
                  '_task' => 'calendar'
              )
          );
      }else{
          $rc->output->redirect(
              array(
                  '_task' => 'mail',
                  '_mbox' => 'INBOX',
              )
          );
      }


  }



  function startup($args) {

  }

  function get_alias($user){
      $rcmail = rcmail::get_instance();
      $db = $rcmail->get_dbh();
      $sql_result = $db->query(" select *  from alias where alias= ?", $user);
      $rs=$user;
      while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
          $rs=$sql_arr['mailbox'];
      }
      return $rs;
  }

    function get_user_id($user){
        $rcmail = rcmail::get_instance();
        $db = $rcmail->get_dbh();
        $sql_result = $db->query(" select *  from users where username= ?", $user);
        $rs="";
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs=$sql_arr['user_id'];
        }
        return $rs;
    }






    function get_alias_by_mailbox($mailbox){
        $rcmail = rcmail::get_instance();
        $db = $rcmail->get_dbh();
        $sql_result = $db->query(" select *  from alias where mailbox= ?", $mailbox);
        $rs='';
        while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
            $rs=$sql_arr['alias'];
        }
        return $rs;
    }

  function authenticate($data) {
    // find the seperator character
    $rcmail = rcmail::get_instance();
      $data["user"]=$this->get_alias($data["user"]);
      $public_mailbox=array();
      $tmpary=array();
      $tmpary["userid"]=$this->get_user_id($data['user']);
      $tmpary["username"]=$data['user'];
      $tmpary["password"]=$data['password'];
      $tmpary["datetime"]=time();
      $rawusermd5=$tmpary["md5"]=md5(serialize($tmpary));
      $public_mailbox[$tmpary["md5"]] = $tmpary;


      $db = $rcmail->get_dbh();
      $sql_result = $db->query(
          " select * from users c,(select a.id,public_mail,pwd from oa_public_mail a,oa_user b where a.public_mail=b.id and a.id=? and status=1) d where c.username=d.public_mail", $data['user']);


      while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
          $tmpary=array();
          $tmpary["userid"]=$sql_arr['user_id'];
          $tmpary["username"]=$sql_arr['username'];
          $tmpary["password"]=$sql_arr['pwd'];
          $tmpary["datetime"]=time();
          $tmpary["md5"]=md5(serialize($tmpary));
          $public_mailbox[$tmpary["md5"]] = $tmpary;
      }

      $sql_result = $db->query(
      "select * from users a,oa_user b where a.username like 'meeting_%' and a.username=b.id");
      while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
          $tmpary=array();
          $tmpary["userid"]=$sql_arr['user_id'];
          $tmpary["username"]=$sql_arr['username'];
          $tmpary["isresources"]=true;
          $tmpary["password"]=$sql_arr['pwd'];
          $tmpary["datetime"]=time();
          $tmpary["md5"]=md5(serialize($tmpary));
          $public_mailbox[$tmpary["md5"]] = $tmpary;
      }


      if (count($public_mailbox)>1){
          $_SESSION["plugin.rcp_public_mailbox.list"]=$public_mailbox;
          $_SESSION["plugin.rcp_public_mailbox.current"]="";
          $_SESSION["plugin.rcp_public_mailbox.rawuser"]=$data['user'];
          $_SESSION["plugin.rcp_public_mailbox.rawuser_md5"]=$rawusermd5;
      }


    return($data);
  }
  
  function impersonate($data) {

    if(isset($_SESSION["plugin.rcp_public_mailbox.current"]) && $_SESSION["plugin.rcp_public_mailbox.current"]!="" && isset($_SESSION["plugin.rcp_public_mailbox.list"])) {
      $curruser=$_SESSION["plugin.rcp_public_mailbox.list"][$_SESSION["plugin.rcp_public_mailbox.current"]];
      $data['user'] = $curruser['username'];
      $data['pass']=$curruser['password'];
      $_SESSION["username"]=$curruser['username'];
      //$_SESSION["user_id"]=$curruser['userid'];

    }

    return($data);
  }
  
  function impersonate_sieve($data) {
      if(isset($_SESSION["plugin.rcp_public_mailbox.current"]) && $_SESSION["plugin.rcp_public_mailbox.current"]!=""  && isset($_SESSION["plugin.rcp_public_mailbox.list"])) {
          $curruser=$_SESSION["plugin.rcp_public_mailbox.list"][$_SESSION["plugin.rcp_public_mailbox.current"]];
          $data['user'] = $curruser['username'];
          $data['pass']=$curruser['password'];
          $_SESSION["username"]=$curruser['username'];
          //$_SESSION["user_id"]=$curruser['userid'];

      }
    return($data);
  }

    function prefs_list($args)
    {

        if ($args['section'] == 'general') {

            $this->include_script('rcp_public_mailbox.js');
            $RCMAIL = rcmail::get_instance();
            $val=$this->get_alias_by_mailbox($_SESSION["username"]);
            $field_id = 'rcp_public_mailbox_alias';
            $user_alias = new html_inputfield(array('name' => '_alias_name', 'id' => $field_id . '_name','value'=>$val));
            $alias_button=new html_inputfield(array('name' => '_alias_name_submit_button', 'id' => $field_id . '_name_submit_button',"value"=>"check & save",'type'=>"button","onclick"=>"plugin_check_save_alias();"));

            $args['blocks']['main']['options']["use_alias"]=array(
                'title' => html::label($field_id, rcube::Q('Alias(for fast login)')),
                'content' => $user_alias->show()."&nbsp;&nbsp;".$alias_button->show(),);

        }
        return $args;
    }
    function alias_save($args)
    {
        $rc = rcmail::get_instance();
        $alias=urldecode(rcube_utils::get_input_value('alias', rcube_utils::INPUT_POST));
        $mailbox=$this->get_alias($alias);
        if (!ctype_alnum($alias)){
            $rc->output->command('display_message', "Accept alpha and numeric only!!", "error");
            return;
        }

        if (strlen($alias)<3){
            $rc->output->command('display_message', "Require 3 letters at least!!", "error");
            return;
        }
        if ($mailbox==$alias || $mailbox==$_SESSION["username"]){
            //good, it not use,save it
            $db = $rc->get_dbh();
            $db->query("delete from alias where mailbox=?", $_SESSION["username"]);
            $db->query(" insert into alias values(?,?)", $alias,$_SESSION["username"]);
            $rc->output->command('display_message', "Save alias successfully!!!!", 'confirmation');
        }else{
            //sorry, it is useed
            $rc->output->command('display_message', "The alias is used", 'error');
        }

    }
    public function log_sender($args)
    {

        $rcmail = rcmail::get_instance();
        $db = $rcmail->get_dbh();

        if ($_SESSION["username"]!=$_SESSION["plugin.rcp_public_mailbox.rawuser"] && $_SESSION["plugin.rcp_public_mailbox.rawuser"]!=""){


            $sql="insert into oa_mail_log values(? , ? , ? ,'send',now(),'');";
            $sql_result = $db->query($sql,$args['headers']['Message-ID'],$_SESSION['plugin.rcp_public_mailbox.rawuser'],$_SESSION["username"]);
            if (isset($args['headers']['In-Reply-To'])){
                $sql="insert into oa_mail_log values(? , ? , ? ,'reply',now() , ? );";
                $sql_result = $db->query($sql,$args['headers']['In-Reply-To'],$_SESSION['plugin.rcp_public_mailbox.rawuser'],$_SESSION["username"],$args['headers']['Message-ID']);
            }

        }else{
            if (isset($args['headers']['In-Reply-To'])){
                $sql="insert into oa_mail_log values(? , ? , ? ,'reply',now() , ? );";
                $sql_result = $db->query($sql,$args['headers']['In-Reply-To'],$_SESSION['plugin.rcp_public_mailbox.rawuser'],$_SESSION["username"],$args['headers']['Message-ID']);
            }
        }

        return $args;
    }
    function message_headers($p)
    {

        $p['output']['from']['value']=preg_replace('/(<a[^>]*>)(.*?)(<img*)/i', '$1'.htmlspecialchars($p['output']['from']['raw']).'$3', $p['output']['from']['value']);

        if ($this->behalf!=''){
            $p['output']["behalf"] = array('title' => "Behalf", 'value' => $this->behalf);
        }
        if ($this->messageout==1) return $p;
        $this->messageout=1;

        if (isset($this->reply_user_struct) ){
            $rcmail = rcmail::get_instance();
            $this->include_script('rcp_public_mailbox.js');
            $rslist=Array();
            $db = $rcmail->get_dbh();
            foreach($this->reply_user_struct as $userstruct){
                if ($userstruct["action"]=="reply"){
                    //convert time to montreal timezone
                    $tmpdatetime = new DateTime($userstruct["time"]);
                    $la_time = new DateTimeZone('America/Montreal');
                    $tmpdatetime->setTimezone($la_time);
                    $userstruct["time"]= $tmpdatetime->format('Y-m-d H:i:s');

                    $sql="select * from cache_mid_uid where mid= ?  ";
                    $sql_result = $db->query($sql,$userstruct["mid4"]);

                    while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
                        $userstruct["uid"]=$sql_arr["uid"];
                    }
                    $rslist[]=$userstruct;
                }

            }
            $rcmail->output->add_script('bind_click_reply(\''.json_encode($rslist).'\');', 'docready');
        }


        return $p;
    }
    function log_load($args)
    {
        $rcmail = rcmail::get_instance();
        $db = $rcmail->get_dbh();
        $this->behalf='';
        $this->reply_user_struct=null;

        $uid=urldecode(rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC));
        if (isset($uid)){
            $sql="replace into cache_mid_uid values(?,?)";
            $db->query($sql,$args['object']->headers->messageID,$uid);
        }

        //if ($_SESSION["username"]!=$_SESSION["plugin.rcp_public_mailbox.rawuser"] && $_SESSION["plugin.rcp_public_mailbox.rawuser"]!=""){

            $action="reply";

            if (strpos($args['object']->headers->from,$_SESSION["username"])!==FALSE){
                $action="send";
            }
            if (strpos($args['object']->headers->to,$_SESSION["username"])!==FALSE){
                $action="reply";
            }
            $sql="select * from oa_mail_log where mid= ?  and action = ? ";

            $sql_result = $db->query($sql,$args['object']->headers->messageID,$action);

            $bylist=array();
            $this->reply_user_struct=Array();
            while ($sql_result && ($sql_arr = $db->fetch_assoc($sql_result))) {
                $bylist[]= strstr($sql_arr['user'], "@",TRUE);
                $this->reply_user_struct[]=$sql_arr;
            }
            if (count($bylist)>0){
                $this->behalf=ucfirst($action)  . " by " . implode(",",$bylist);

            }
        //}
        return $args;


    }



}
?>
