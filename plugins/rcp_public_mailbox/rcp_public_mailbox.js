/*
 * This is part of identity_imap plugin
 */
function plugin_load_identity_list() {
    var $truName = $('.topright .username');
    if ($truName.size() > 0) {
        $sw = $('#plugin-ident_switch-account');
        if ($sw.size() > 0) {
            var $topline = $truName.parent('.topright');
            $sw.prependTo($topline);
            $truName.hide();
            $('#plugin-ident_switch-account').show();
        }
    }

    $("INPUT[name='_ident_switch.form.enabled']").change();
    $("SELECT[name='_ident_switch.form.secure']").change();

    plugin_switchIdent_processPreconfig();
}


function plugin_switchIdent_processPreconfig() {
    var disFld = $("INPUT[name='_ident_switch.form.readonly']");
    disFld.parentsUntil("TABLE", "TR").hide();

    var disVal = disFld.val();
    if (disVal > 0) {
        $("INPUT[name='_ident_switch.form.host']").prop("disabled", true);
        $("SELECT[name='_ident_switch.form.secure']").prop("disabled", true);
        $("INPUT[name='_ident_switch.form.port']").prop("disabled", true);
    }
    if (2 == disVal) {
        $("INPUT[name='_ident_switch.form.username']").prop("disabled", true);
    }

}

function plugin_switchIdent_enabled_onChange(e) {
    var $enFld = $("INPUT[name='_ident_switch.form.enabled']");
    $("INPUT[name!='_ident_switch.form.enabled'], SELECT", $enFld.parents("FIELDSET")).prop("disabled", !$enFld.is(":checked"));
    plugin_switchIdent_processPreconfig();
}

function plugin_switchIdent_secure_onChange(e) {
    var $secSel = $("SELECT[name='_ident_switch.form.secure']");
    var $portFld = $("INPUT[name='_ident_switch.form.port']");

    if ('SSL' === $secSel.val().toUpperCase())
        $portFld.attr("placeholder", 993);
    else
        $portFld.attr("placeholder", 143);
}

function plugin_switchIdent_switch(val) {
    rcmail.http_post('plugin.rpc_public_mailbox.switch_mailbox', { 'tomail': val, '_mbox': rcmail.env.mailbox });
}

function  plugin_switchIdent_fixIdent(iid) {
    if (parseInt(iid) > 0)
        $("#_from").val(iid);
}

function plugin_check_save_alias(){
    rcmail.http_post('plugin.rpc_public_mailbox.check_save_alias', { 'alias': $('#rcp_public_mailbox_alias_name').val(), '_setcion': "general"});
}

function hide_setting(){
    $('.button-settings').hide();
}
function show_setting(){
    $('.button-settings').show();
}

function hide_about(){
    $('.about-link').hide();
}

function showCalendarOnly(){
    $('#taskbar').children("a").hide();
    $(".button-calendar").show();
}



function bind_click_reply(replylist)
{
    var replyjson=JSON.parse(replylist);



    $('#messageheader').after('<div id="extra_content_collspae" style="padding-bottom:30px;"></div>');

    $.each(replyjson,function(idx){
        var item=replyjson[idx];

        if (item.action!="reply") return;
        var byuser="";
        if (item.user!=item.user4){
            byuser=" by "+item.user.match(/^([^@]*)@/)[1];
        }
        var desc="This email was replied "+byuser+" on Canada Eastern Time "+item.time;
        $('#extra_content_collspae').append('<h3 id="show_reply_head_'+idx+'" style="background-color:#FFB6C1" dataidx="'+idx+'">'+desc+'</h3>');
        $('#extra_content_collspae').append('<div align="center"   style="padding:0px;!important;"><img id="loading_img_'+idx+'" width="50px;" height="50px;" style="padding-top:50px;"  src="./plugins/rcp_public_mailbox/loader.gif"/><iframe id="show_reply_frame_'+idx+'" src="" width="100%" height="100%" frameborder="0"></iframe></div>');
        idx++;
    })

    $( "#extra_content_collspae" ).accordion({
        collapsible: true,
        active:false,
        activate: function( event, ui ) {
            if (ui.newHeader.attr("id")!=undefined && ui.newHeader.attr("id").substring(0,15) =="show_reply_head"){
                var dataidx=ui.newHeader.attr("dataidx");
                if ($('#show_reply_frame_'+dataidx).attr('src')==""){

                    function set_frame_src(uid){
                        $('#show_reply_frame_'+dataidx).attr('src', rcmail.url('preview', '&_uid=' +uid + "&_mbox=Sent&"));
                        $('#loading_img_'+dataidx).hide();
                    }

                    var replyitem=replyjson[dataidx];

                    if (replyitem.uid==undefined || replyitem.uid=="") {

                        $.ajax({
                            type: 'GET',
                            url: 'https://mail.sinoramagroup.com/cgi/s_msg_id.cgi?filettime='+replyitem.time+'&mailbox='+replyitem.user4+'&msgid='+replyitem.mid4.substring(1,replyitem.mid4.length-2)+'&',
                            data: {},
                            dataType: 'text',
                            success: function (response) {
                                console.log(response);
                                set_frame_src(response);

                            },
                            error: function (o, status, err) {
                                console.log(err);
                                $('#img_loading').hide();
                            },
                            cache: false,
                        });
                    }else{
                        set_frame_src(replyitem.uid);
                    }


                }
            }

        }
    });

}


