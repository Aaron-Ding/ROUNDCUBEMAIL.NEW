/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

/* global tinyMCE, rcmail, framework */

$(document).ready(function() { xcloud.init(); });

var xcloud = new function()
{
    this.mimeId = false;

    this.init = function() {

        // when the dropdown menu on the attachment is open, save the mime id of the message it belongs to and enable the
        // command it leads to
        rcmail.addEventListener("beforemenu-open", function(p) {
            if (p.menu == "attachmentmenu") {
                xcloud.mimeId = p['id'];
                for (var plugin in rcmail.env.xcloud_plugins) {
                    //rcmail.enable_command(plugin + "-save-attachment", true);
                    insertMenuAttachmentSaveCode(plugin, p['id']);
                }
            }
        });

        // iterate through all the cloud plugins and do the things that need to be done for each one
        for (var plugin in rcmail.env.xcloud_plugins) {
            insertImageAttachmentSaveCode(plugin);
        }

        // adjust the width of the attach buttons on the compose page so they're all the same
        var width = 0;
        $("#compose-attachments input[type=button]").each(function() {
            if ($(this).outerWidth() > width) {
                width = $(this).outerWidth();
            }
        });
        $("#compose-attachments input[type=button]").width(width);
    };



    this.selectSuccess = function(data, linkType, plugin, parameters) {
        if (linkType == "preview") {
            selectPreviewSuccess(data, plugin, parameters);
        } else {
            selectDirectSuccess(data, plugin, parameters);
        }
    };

    /**
     * Inserts save buttons next to the links displayed in the message body.
     */
    var insertImageAttachmentSaveCode = function(plugin) {
        if (!$("#aria-label-messageattachments").length ||
            typeof window[plugin]["insertImageSaveButton"] !== "function"
        ) {
            return;
        }

        $("p.image-attachment").each(function() {
            // we're using one container for the buttons from all the cloud plugins, make sure it's there
            var container = $(this).find(".xcloud-save-button-container");
            if (!container.length) {
                container = $("<div>").addClass("xcloud-save-button-container").appendTo($(this).find("span.attachment-links"));
            }

            // find file name
            var name = $(this).find(".image-filename").text();

            // find download url
            var url = false;
            $(this).find("span.attachment-links > a").each(function() {
                var href = $(this).attr("href");
                if (href.indexOf("_download=1") != -1) {
                    url = href;
                }
            });

            if (!name || !url) {
                return true;
            }

            // call the plugin's insert function
            var button = window[plugin]["insertImageSaveButton"](name, url);

            if (button) {
                $("<div>").addClass(plugin + "-save-button-wrap xcloud-save-button-wrap").append(button).appendTo(container);
            }
        });
    };

    var insertMenuAttachmentSaveCode = function(plugin, id) {
        if (!$("#aria-label-messageattachments").length ||
            typeof window[plugin]["insertMenuSaveButton"] !== "function"
        ) {
            return;
        }

        // add menu item for this plugin if it doesn't exist
        if (!$("#" + plugin + "-attachment-menu").length) {
            $("<li>")
                .addClass("xcloud-attach-menu-container")
                .append(
                    $("<div>").attr("id", plugin + "-attachment-menu").addClass("active")
                ).appendTo($("#attachmentmenu ul"));
        }

        // find download url
        var a = $("#attach" + id + " a").first();
        var url = a.attr("href");

        // find file name
        var copy = a.clone();
        copy.find("span").remove();
        var name = copy.text().trim();

        if (!name || !url) {
            return;
        }

        window[plugin]["insertMenuSaveButton"](id, name, url + "&download=1");;
    };

    /**
     * Inserts the selected file links to the body of the message.
     */
    var selectPreviewSuccess = function(data, plugin, parameters) {
        var html = $("input[name='_is_html']").val() == "1";
        var links = [];

        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                if (html) {
                    links.push("<a class='xcloud-link " + plugin + "-link' href='" + data[key]['url'] + "'>" + data[key]['name'] + "</a>");
                } else {
                    links.push(data[key]['url']);
                }
            }
        }

        if (html) {
            tinyMCE.execCommand("mceInsertContent", false, " " + links.join(", ") + " ");
        } else {
            var element = $("#composebody");
            var value = element.val();
            element.val(
                value.substring(0, element.prop("selectionStart")) +
                "\n\n" + links.join("\n") + "\n\n" +
                value.substring(element.prop("selectionEnd"), 0)
            );
        }
    };

    /**
     * Downloads the selected files from Cloud and attaches them to the message.
     */
    var selectDirectSuccess = function(data, plugin, parameters) {
        var uploadId = new Date().getTime();

        rcmail.add2attachment_list(uploadId, {
            html : "<span>Uploading</span>",
            classname: "uploading",
            complete: false
        });

        // create the default parameters to send
        var send = { files: data, uploadId: uploadId, composeId: rcmail.env.compose_id };

        // add the parameters
        if (typeof parameters === "object") {
            for (var name in parameters) {
                send[name] = parameters[name];
            }
        }

        rcmail.http_post("plugin." + plugin + "_attach", send, rcmail.set_busy(true, 'uploading'));
    };

    this.save = function(plugin, mimeId, done) {
        if (typeof mimeId === "undefined" || !mimeId) {
            mimeId = this.mimeId;
        }

        var busyId = rcmail.set_busy(true, "uploading");
        $.ajax({
            url: rcmail.url(plugin + "SaveAttachment"),
            method: "POST",
            headers: { "x-csrf-token": rcmail.env.request_token },
            dataType: "json",
            data: {
                uid: rcmail.env.uid,
                mbox: rcmail.env.mailbox,
                mimeId: mimeId
            }
        }).done(function(response) {
            rcmail.set_busy(false, false, busyId);
            busyId = false;

            if (response.id) {
                done(response);
            } else {
                rcmail.display_message(rcmail.gettext("errorsaving"), "error");
            }
        });
    };
};
