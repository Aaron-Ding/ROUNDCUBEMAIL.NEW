/**
 * Created by mac on 2017-02-17.
 */


function display_new_message(data3){

    $("div").find('.msg_content').remove();
    if (data3.length>0){
        for(var itemidx=0;itemidx<data3.length;itemidx++) {
            var item=data3[itemidx];
            var itemcontent='<div class="msg_content"><hr class="content_hr"><h3>'+item.sender+' to '+item.receiver+' on '+item.createtime+'</h3>'+
                '<p>'+item.content+'</p>';
                if (item.confirmstatus==undefined || item.confirmstatus==0){
                    itemcontent+='<button class="remove_button ui-button ui-widget ui-corner-all" data="'+item.msgid+'">I read</button>';
                }
            itemcontent+='</div>';



            $('#tabs-1').append(itemcontent);
        }
        $(".remove_button").click(function(event){
            event.preventDefault();
            $.getJSON( "./?_task=rcp_message&_action=plugin.rcp_message.readmsg&", {msgid:$(this).attr("data")})
                .done(function( data2 ) {
                    display_new_message(data2);
                });
        });
    }else{
        $('#tabs-1').append('<div class="msg_content"><hr class="content_hr"><h2>No new messages!</h2></div>');

    }
}






function display_out_message(data3){

    $("div").find('.out_msg_content').remove();
    if (data3.length>0){
        for(var itemidx=0;itemidx<data3.length;itemidx++) {
            var item=data3[itemidx];
            var itemcontent='<div class="out_msg_content"><hr class="content_hr"><h3>To '+item.receiver+' on '+item.createtime+'</h3>'+
                '<p>'+item.content+'</p>';
                itemcontent+='<button class="out_remove_button ui-button ui-widget ui-corner-all" data="'+item.msgid+'">Delete</button>';
                itemcontent+='</div>';



            $('#tabs-2').append(itemcontent);
        }
        $(".out_remove_button").click(function(event){
            event.preventDefault();
            var ref=this;
            $.getJSON( "./?_task=rcp_message&_action=plugin.rcp_message.removemsg&", {msgid:$(this).attr("data")})
                .done(function( data2 ) {
                    //display_out_message(data2);
                    $(ref).parents('div.out_msg_content').hide();
                });
        });
    }else{
        $('#tabs-2').append('<div class="out_msg_content"><hr class="content_hr"><h2>No messages!</h2></div>');

    }
}
$( document ).ready(function() {

});
var fortnightAway = new Date(+new Date + 12096e5);

function load_hj_message(rcp_message_lev,rcp_message_domain,foreshow)
{
    var $truName = $('.topright .username');
    $truName.after('<a class="button-notice" id="hj_msg_dialog_opener" href="javascript:void(0);" >Notices</a>');



    $( function() {
        $( "#hj_msg_dialog" ).dialog({
            autoOpen: false,
            resizable: false,
            modal: true,
            width: 800,
            height:600,

        });

        $( "#hj_msg_dialog_opener" ).click( function() {

            $( function() {
                function split( val ) {
                    return val.split( /,\s*/ );
                }
                function extractLast( term ) {
                    return split( term ).pop();
                }

                $( "#msg_receiver" )
                // don't navigate away from the field on tab when selecting an item
                    .on( "keydown", function( event ) {
                        if ( event.keyCode === $.ui.keyCode.TAB &&
                            $( this ).autocomplete( "instance" ).menu.active ) {
                            event.preventDefault();
                        }
                    })
                    .autocomplete({
                        source: function( request, response ) {
                            $.getJSON( "./?_task=rcp_message&_action=plugin.rcp_message.searchmailbox&", {
                                term: extractLast( request.term )
                            }, response );
                        },
                        search: function() {
                            // custom minLength
                            var term = extractLast( this.value );
                            if ( term.length < 2 ) {
                                return false;
                            }
                        },
                        focus: function() {
                            // prevent value inserted on focus
                            return false;
                        },
                        select: function( event, ui ) {
                            var terms = split( this.value );
                            // remove the current input
                            terms.pop();
                            // add the selected item
                            terms.push( ui.item.value );
                            // add placeholder to get the comma-and-space at the end
                            terms.push( "" );
                            this.value = terms.join( ", " );
                            return false;
                        }
                    });
            } );














            $("div").find('.msg_content').remove();
            $("#hj_msg_dialog").dialog("open");
            $("#tabs").tabs({
                activate: function( event, ui ) {
                    if (ui.newPanel != undefined && ui.newPanel.attr('id') == "tabs-1") {
                        if ($("#startdatepicker").val() != "" && $("#enddatepicker").val() != "") {
                            $("#query_in").trigger('click');
                        }else {

                            $("#read_new_message_button").trigger('click');
                        }
                    }
                }
            });
            $(".ui-button").button();
            $("#startdatepicker").datepicker({
                changeMonth: true,
                changeYear: true
            });
            $("#enddatepicker").datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd'
            });


            $("#out_startdatepicker").datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd'
            });

            $("#out_enddatepicker").datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd'
            });



            $("#out_expiredate").datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd'
            });
            $("#out_expiredate").datepicker('setDate', fortnightAway);

            $("#message_readstatus").selectmenu();


            $("#send_to_user_type").selectmenu({
                change: function( event, ui ) {
                    if ($("#send_to_user_type").val()=="ALL" || $("#send_to_user_type").val()=="DOMAIN"){
                        $('#msg_receiver').hide();
                    }else{
                        $('#msg_receiver').show();
                    }
                }
            });

            if (rcp_message_lev>0){
                $('#send_to_user_type').append($("<option></option>").attr("value", "DOMAIN").text('All users of '+rcp_message_domain));
            }
            if (rcp_message_lev>1){
                $('#send_to_user_type').append($("<option></option>").attr("value", "ALL").text('All users of Sinorama'));
            }



            $("#confirm_type").selectmenu();


            $.getJSON("./?_task=rcp_message&_action=plugin.rcp_message.newmsg&")
                .done(function (data) {
                    display_new_message(data);
                });



            $( "#query_in").unbind( "click" );
            $("#query_in").click(function(event){
                event.preventDefault();
                $("div").find('.msg_content').remove();
                $(".showrequired").hide();
                if ($("#startdatepicker").val()=="" ){
                    $("#in_start_date_required").show();
                    return;
                }
                if ($("#enddatepicker").val()==""){
                    $("#in_end_date_required").show();
                    return;
                }
                var formdata=$('#message_in_query').serialize();
                $.getJSON("./?_task=rcp_message&_action=plugin.rcp_message.querymsg&"+formdata)
                    .done(function (data) {
                        display_new_message(data);
                    });
            });




            $("#message_out_query_button").unbind( "click" );
            $("#message_out_query_button").click(function(event){
                event.preventDefault();
                $("div").find('.out_msg_content').remove();
                $(".showrequired").hide();
                if ($("#out_startdatepicker").val()=="" ){
                    $("#out_start_date_required").show();
                    return;
                }
                if ($("#out_enddatepicker").val()==""){
                    $("#out_end_date_required").show();
                    return;
                }
                var formdata=$('#message_out_query').serialize();
                $.getJSON("./?_task=rcp_message&_action=plugin.rcp_message.quersendymsg&"+formdata)
                    .done(function (data) {
                        display_out_message(data);
                    });
            });

            $( "#read_new_message_button").unbind( "click" );
            $("#read_new_message_button").click(function(event){
                event.preventDefault();
                $("#message_in_query").trigger("reset");
                $("div").find('.out_msg_content').remove();
                $.getJSON("./?_task=rcp_message&_action=plugin.rcp_message.newmsg&")
                    .done(function (data) {
                        display_new_message(data);
                    });

            });




            $( "#send_msg_button").unbind( "click" );
            $("#send_msg_button").click(function(event){
                event.preventDefault();
                $('#msg_content_required').hide();
                $('#msg_receiver_required').hide();
                if ($('#msg_content').val()==""){
                    $('#msg_content_required').show();
                    return;
                }

                if ($('#send_to_user_type').val()=="USERS" && $('#msg_receiver').val()==""){
                    $('#msg_receiver_required').show();
                    return;
                }



                var formdata=$('#new_message_form').serializeArray();
                $.post( "./?_task=rcp_message&_action=plugin.rcp_message.sendmsg&", $('#new_message_form').serializeArray())
                    .done(function( data ) {
                        if (data=="success"){
                            rcmail.display_message('Send success!','notice');
                            $('#new_message_form').trigger("reset");
                            $("#out_expiredate").datepicker('setDate', fortnightAway);
                        }else{
                            rcmail.display_message('Something is wrong! It is not send!','error');
                        }

                });
            });

        });



        $.getJSON("./?_task=rcp_message&_action=plugin.rcp_message.newmsg&")
            .done(function (data) {
                if (data.length>0){
                    for(var itemidx=0;itemidx<data.length;itemidx++) {
                        var item=data[itemidx];
                        if (item.noticetype==1 && foreshow==1){
                            $("#hj_msg_dialog_opener").trigger("click");
                            break;
                        }
                    }
                    $("#hj_msg_dialog_opener").html("New");
                }
            });




    } );
}


