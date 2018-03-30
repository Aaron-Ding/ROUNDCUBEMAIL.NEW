/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

/* global Hammer, rcmail, bw, skinRunOnReadyAfter, skinRunOnReadyBefore, CONTROL_KEY, UI */

$(document).ready(function() {
    xmobile.afterReady();
});

var xmobile = new function()
{
    this.filterActive = false;
    this.searchActive = false;
    this.panDirection = false;
    this.panActionBoxes = false;
    this.actionRowOpen = false;
    this.clearSelectionOnPopupHide = false;
    this.panRefreshAllowed = false;
    this.contentElements = false;
    this.legacy = false;

    /**
     * This function is executed after the document is ready.
     *
     * @returns {undefined}
     */
    this.afterReady = function()
    {
        // run the skin-specific function if it exists (the function can be defined in colors.js)
        if (typeof skinRunOnReadyBefore == "function") {
            skinRunOnReadyBefore();
        }

        // turn off the RC mobile flag since it causes problems with message lists and other stuff
        bw.mobile = false;
        bw.iphone = false;
        bw.ipad = false;
        bw.touch = false;

        // set compatibility mode (rc 1.0)
        if ($("div.minwidth").length) {
            this.legacy = true;
        }

        // the noscroll class added to message view by roundcube prevents scrolling the message on some devices
        $("body").removeClass("noscroll");

        // ad classes to content containers to mark the containers that should be moved when showing popup boxes
        // div.minwidth is for rc 1.0.x
        xmobile.contentElements =
            $("#main-menu, #mainscreen, #planner_controls_container, #planner_items, #filter_bar, #notes, div.minwidth");

        // setup gestures
        this.setupGestureMessageView();
        this.setupGestureRefresh();

        // setup plugins
        this.setupXCloud();
        this.setupXSidebar();
        this.setupXCalendar();

        // setup all other popup boxes and finalize the existing popup boxes
        this.setupPopupBoxes();

        // font icons applied here
        $("a.firstpage, a.prevpage,"+
          "a.nextpage, a.lastpage,"+
          "a.listbutton.add span.inner,"+
          "a.listbutton.delete span.inner,"+
          "a.listbutton.removegroup span.inner").text("");

        $("#interface-options a").on("click", function(ev) {
            xmobile.popupHide(ev);
        });

        $("#attachment-list .delete").html("");

        // add a current-folder div above the message list

        if ($("#messagelistcontainer").length) {
            $("#messagelistcontainer").before("<div id='current-folder'><span></span></div>");
            xmobile.updateCurrentFolder();
        }

        // set up a click event for folder selection buttons

        $("#mailboxlist li a").click(function(ev) {
            xmobile.updateCurrentFolder();
            xmobile.popupHide(ev);
        });

        // check if a filter is applied and update the funnel button (search is always reset on page refresh, so we
        // don't need to check for search)

        if ($("#rcmlistfilter").length) {
            this.filterActive = $("#rcmlistfilter").val() != "ALL";
            this.updateFunnel();
        }

        // when ajax-loaded contents change, we need to process the message list (js events assigned, etc.)
        if ($("#messagelistcontainer").length)
        {
            // the listupdate event listener is supposed to execute after the message list is loaded (it could be reloaded without refreshing the page)
            // but it's unreliable: on iPhone it doesn't execute, on android it executes sometimes, it seems to execute on windows phone
            // so we still attach it, but we also run a timer that executes fixMessageList every second
            // fixMessageList adds a class to the first row to indicate that it's been processed, so it doesn't get processed more than once per load

            //rcmail.addEventListener('listupdate', function(evt) { fixMessageList(); });

            xmobile.setMailTimeout();
        }

        if ($("#messagecontent .rightcol").length) {
            $("#messagecontent .leftcol").after($("#messagecontent .rightcol"));
        }

        // The folder-selector popup is added to the html dynamically when the move or copy buttons are pressed
        // (when viewing a single message.) We hook to the event and add the popup header.
        rcmail.addEventListener('aftermove', function(ev) { xmobile.addFolderSelectorHeader(); });
        rcmail.addEventListener('aftercopy', function(ev) { xmobile.addFolderSelectorHeader(); });

        // load the user interface settings page

        if ($(".btn-sectionslist").length) {
            setTimeout(function() { rcmail.section_select(new settingsLoader("general")); }, 500);
        }

        // compose page address popup: hide the popup when adding an address to cc, bcc, etc.
        if ($("#compose-contacts .boxfooter a").length) {
            $("#compose-contacts .boxfooter a").on("click", function(ev) { xmobile.popupHide(ev); });
        }

        // if myroundcube identities_imap is enabled, a combo box replaces the currently logged in e-mail address
        // the combo box is in the header of a popup, need to stop the popup from closing when the combo is clicked
        if ($("#header h5 select.deco").length) {
            $("#header h5 select.deco").on("click", function(ev) { ev.stopPropagation(); });
        }

        // myroundcube identities_imap shows a permanent note at the top of the screen that we're browsing a remote
        // account - fade it out after a few seconds
        if ($(".remotehint")) {
            setTimeout(function() { $(".remotehint").fadeOut(); }, 3000);
        }

        // myroundcube calendar (old): show the calendar box after changing calendar display type (day, week, month)
        $(".calendar-page #sectionslist .boxtitle.ui-widget-header a").on("click", function() {
            xmobile.showCalendarOld("calendar");
        });

        // remove the address book group options link text (little gear character) because it doesn't render properly
        // on mobile browsers. we replace it with a gear font icon
        if ($("#groupoptionslink span.inner").length) {
            $("#groupoptionslink span.inner").html("");
        }

        if ($("#keyoptionslink span.inner").length) {
            $("#keyoptionslink span.inner").html("");
        }

        // run the skin-specific function if it exists (the function can be defined in colors.js)
        if (typeof skinRunOnReadyAfter == "function") {
            skinRunOnReadyAfter();
        }

        // remove the option to switch to html editor on the compose page
        if ($("#composeoptions").length) {
            $("#composeoptions select[name=editorSelector]").parents("span.composeoption").hide();
        }

        // set things up for legacy rc 1.0
        if (this.legacy) {
            this.setupLegacy();
        }
    };

    this.addFolderSelectorHeader = function() {
        if (!$("#folder-selector h5").length) {
            xmobile.addPopupTitle($("#folder-selector"), rcmail.gettext("folders"));
            xmobile.addPopupCloseButtonBar($("#folder-selector"));

            /* need to make sure this popup closes when a folder is clicked, can't use "clicked" because
               it overwrites the attached event and the messages don't get moved */
            $("#folder-selector").on("mouseup", function(ev) {
                xmobile.popupHide(ev);
            });
        }
    };

    /**
     * Creates all popup boxes.
     */
    this.setupPopupBoxes = function() {

        // create popup boxes out of existing divs
        this.makePopup($("#header"), false);
        this.makePopup($("#mailview-left"), false);
        this.makePopup($("#quicksearchbar"), false);
        this.makePopup($("#mailtoolbar"), false);
        this.makePopup($("#messagetoolbar"));
        this.makePopup($("#settings-sections"), false);
        this.makePopup($("#addressbooktoolbar"));
        this.makePopup($("#composeview-left"), false);
        this.makePopup($("#groupoptions"), false);
        this.makePopup($("#mailboxmenu"), false);
        this.makePopup($("#listselectmenu"), false);

        // managesieve filters
        this.makePopup($("#filtersetmenu-menu"));
        this.makePopup($("#filtermenu-menu"));
        $("#filtersetmenulink").attr("onclick", "xmobile.popup(event, 'filtersetmenu-menu')");
        $("#filtermenulink").attr("onclick", "xmobile.popup(event, 'filtermenu-menu')");
        $("#filtersetslistbox a.add").text($("#filtersetslistbox a.add").attr("title")).appendTo("#filtersetmenu-menu");
        $("#filterslistbox a.add").text($("#filterslistbox a.add").attr("title")).appendTo("#filtermenu-menu");

        // add titles to popup boxes and links

        // pull all existing elements that will become popup boxes to the body level so we can hide
        // mainscreen when showing the popups
        $("#quicksearchbar").appendTo("body");
        $("#mailview-left").appendTo("body");
        $("#messagetoolbar").appendTo("body");

        if ($("#mailboxmenulink").length) {
            this.addPopupTitle($("#mailboxmenu"), $("#mailboxmenulink").attr("title").replace("...", ""));
        }
        this.addPopupTitle($("#listselectmenu"), $("#listselectmenulink").text());
        this.addPopupTitle($("#markmessagemenu"), $("#markmessagemenulink").text());
        this.addPopupTitle($("#messagemenu"), $("#messagemenulink").text());
        this.addPopupTitle($("#replyallmenu"), $(".button.reply").text());
        this.addPopupTitle($("#forwardmenu"), $(".button.forward").text());
        this.addPopupTitle($("#mailview-left"), rcmail.gettext("folders"));
        this.addPopupTitle($("#header"), $("#topline .username").html());
        this.addPopupTitle($("#quicksearchbar"), rcmail.gettext("search"));
        this.addPopupTitle($("#attachmentmenu"), rcmail.gettext("attachment"));
        this.addPopupTitle($("#settings-sections"), rcmail.gettext("section"));
        this.addPopupTitle($("#mailtoolbar"), rcmail.gettext("options"));
        this.addPopupTitle($("#composeview-left"), $("#composeview-left h2.boxtitle").text());
        this.addPopupTitle($("#responsesmenu"), $("#responsesmenu li.separator:first label").text());
        this.addPopupTitle($("#addressbooktoolbar"), rcmail.gettext("options"));
        this.addPopupTitle($("#groupoptions"), $("#aria-label-groupoptions").text());
        this.addPopupTitle($("#keyoptions"), $("#keyoptionslink").attr("title"));
        this.addPopupTitle($("#enigmamenu"), $(".button.enigma").text());

        if ($("a#spellmenulink").length) { // RC 1.1
            this.addPopupTitle($("#spellmenu"), $("a#spellmenulink").text());
        } else {
            this.addPopupTitle($("#spellmenu"), $("#mailtoolbar a.spellcheck").attr("title"));
        }

        if ($(".calendar-page").length) {
            this.addPopupTitle($("#messagetoolbar"), rcmail.gettext("options"));
        } else {
            this.addPopupTitle($("#messagetoolbar"), $("#taskbar .button-mail .button-inner").text());
        }

        // mark all popup menus (original roundcube class) as popup-box (our class)
        $(".popupmenu").addClass("popup-box");

        // fix button actions
        if ($("#groupoptionslink").length) {
            $("#groupoptionslink").attr("onclick", "xmobile.popup(event, 'groupoptions')");
        }

        if ($("#keyoptionslink").length) {
            $("#keyoptionslink").attr("onclick", "xmobile.popup(event, 'keyoptions')");
        }

        // mail list options
        $("#popup-funnel").append(
            "<a id='button-selectmenu' href='javascript:void(0)' onclick='xmobile.popup(event, \"listselectmenu\")'>" +
                "<span>" + $("#listselectmenu h5").text() + "</span>" +
            "</a>" +
            "<a id='button-mailboxmenu' href='javascript:void(0)' onclick='xmobile.popup(event, \"mailboxmenu\")'>" +
                "<span>" + $("#mailboxmenu h5").text() + "</span>" +
            "</a>" +
            "<a id='button-listoptions' href='javascript:void(0)' onclick='return rcmail.command(\"menu-open\", \"messagelistmenu\", this, event);'>" +
                "<span>" + $("#listoptions h2").text() +"</span>" +
            "</a>"
        );

        // add all the menu items that display another menu to the list below:
        // it'll display show the child menu without moving the main screen
        $(".popup-box a").each(function() {

            var element = $(this);
            var onclick = element.attr("onclick");

            if (typeof onclick != 'undefined' &&
                (onclick.indexOf("toggle_popup") != -1 ||
                onclick.indexOf("show_popup") != -1 ||
                onclick.indexOf("command('move'") != -1 ||
                onclick.indexOf("command('copy'") != -1 ||
                onclick.indexOf("enigmamenu") != -1)
            ) {
                element.on("click", function(ev) {
                    xmobile.popupDisappear(ev);

                    setTimeout(function() {
                        $(".popupmenu").addClass("popup-box");

                        /* these popups doen't restore the main screen even though they're click-close and all - fix it */
                        if (!$("#spellmenu").hasClass("processed")) {
                            $("#spellmenu").addClass("processed");
                            $("#spellmenu a").on("click", function(ev) { xmobile.popupHide(ev); });
                        }
                    }, 0);
                });
            }
        });

        $(".button-interface-options").on("click", function(ev) { xmobile.popupDisappear(ev); });

        // add popup close toolbar to the top of all popup boxes and popup menus
        this.addPopupCloseButtonBar($(".popup-box"));

        // popups on the login page
        if ($(".login-page #header").length) {
            $("#login-form").after(
                "<a id='login-menu-button' href='javascript:void(0)' onclick='xmobile.popup(event, \"header\")'></a>"
            );
        }

        // popups on the compose page
        if ($("#composebody").length) {

            // add the toolbar popup - doing it dynamically here because in older versions of rc the id is mailtoolbar
            // and in the newer versions it's messagetoolbar
            var toolbarName = $("#mailtoolbar").length ? "mailtoolbar" : "messagetoolbar";

            $("#main-menu").append(
                "<a id='button-toolbar' href='javascript:void(0)' onclick='xmobile.popup(event, \"" + toolbarName +
                "\")'><span></span></a>"
            );

            // create compose cc, bcc, etc. popup - change the onclick events on the links to our own function so we
            // can handle both showing and hiding the cc rows by the same links
            $("#popup-compose-settings").append($("#composeheaders .formlinks").html());
            $("#composeheaders .formlinks").html("");

            $("#popup-compose-settings a").each(function() {
                $(this).attr("onclick", $(this).attr("onclick").replace("UI.show_header_row", "xmobile.toggleCC"));
            });

            // move the edit identitites buttons to the more actions popup
            $("#popup-compose-settings").append($(".compose-headers a.iconlink.edit"));
        }

        // create filter popup (take options from the select element and make a's out of them)
        var filterSelect = false;

        if ($("#rcmlistfilter").length) {
            filterSelect = "#rcmlistfilter";
        } else if ($("#searchfilter").length) { // rc 1.1
            filterSelect = "#searchfilter";
        }

        if (filterSelect) {
            $(filterSelect + " option").each(function() {
                $("#popup-filter").append(
                    "<a href='javascript:void(0)' onclick='xmobile.applyFilter(event, \"" + $(this).val() + "\")'>" +
                        $(this).text() +
                    "</a>"
                );
            });
        }

        // create search popup
        if ($("#quicksearchbar").length) {
            $("#quicksearchbar form").after($("#searchmenu ul"));
            $("#quicksearchbar ul").after(
                "<a class='search-apply' href='javascript:void(0)' onclick='xmobile.applySearch(event, this)'>" +
                    rcmail.gettext("search") +
                "</a>" +
                "<a class='search-reset' href='javascript:void(0)' onclick='xmobile.resetSearch(event, this)'>" +
                    $("#searchreset").attr("title") +
                "</a>"
            );
            $("#quicksearchbar form").attr("onsubmit", "xmobile.applySearch(event); return false;");
        }

        // set up all popupmenus to be click-close so they properly restore the main screen, not only hide themselves
        $(".popupmenu").addClass("click-close");

        // if popup boxes have class clickClose, register them to be closed on click
        $(".popup-box.click-close").on("click", function(ev) { xmobile.popupHide(ev); });

        // make selects usable on click-close popup boxes
        $(".popup-box select, .popup-box input[type='checkbox']").on("click", function(ev) { ev.stopPropagation(); });

        // fix the skin quick select so it doesn't hide the popup
        $("#interface-options select").on("mouseup", function(ev) { ev.stopPropagation(); });

        if ($("#summarytable").length) {
            $("#summarytable").html($("#summarytable").html().replace(/&nbsp;/g, ''));
        }

        // enigma: move the import / export buttons to the popup
        if ($("#enigmakeyslist").length) {
            $("#keyoptions ul.toolbarmenu").append($("<li>").append($("#keystoolbar a.import")));
            $("#keyoptions ul.toolbarmenu").append($("#exportmenu-menu li"));
        }

        // message navigation

        if ($("#countcontrols").length) {
            $("#pagejumper").appendTo("#countcontrols");
        }
    };

    this.setupLegacy = function() {
        // rc 1.0 has an extra div right inside body that contains some popup boxes, pull them out
        $("div.minwidth .popup-box").each(function() {
            $("body").append($(this));
        });

        // this popup doesn't restore the main screen on hide, attaching popupHide to its links to fix it
        $("#markmessagemenu a").each(function() {
            $(this).on("click", function() { xmobile.popupHide(); });
        });

    };

    /**
     * Sets up xcalendar to work on mobile skins.
     *
     * @returns {undefined}
     */
    this.setupXCalendar = function() {
        xmobile.makePopup($("#xcalendar-toolbar"));
        xmobile.makePopup($("#add-calendar-menu"));
        xmobile.makePopup($("#event-options"));
        xmobile.makePopup($("#remove-options"));

        $("#calendar-list-container .boxtitle button").attr("onclick", "xmobile.popup(event, 'add-calendar-menu')");
        $("#event-preview .button-options").attr("onclick", "xmobile.popup(event, 'event-options')");
        $("#event-preview .button-remove-repeated").attr("onclick", "xmobile.popup(event, 'remove-options')");

        xmobile.addPopupTitle($("#xcalendar-toolbar"), rcmail.gettext("options"));
        xmobile.addPopupTitle($("#add-calendar-menu"), rcmail.gettext("options"));
        xmobile.addPopupTitle($("#event-options"), rcmail.gettext("options"));
        xmobile.addPopupTitle($("#remove-options"), rcmail.gettext("options"));
    };

    /**
     * Sets up the cloud buttons to work on mobile skins.
     *
     * @returns {undefined}
     */
    this.setupXCloud = function() {
        $(".xcloud-compose-button input").each(function() {
            var menu = $(this).parent().attr("id").replace("button", "menu");
            xmobile.makePopup($("#" + menu), false);
            xmobile.addPopupTitle($("#" + menu), $(this).attr("value"));
            $(this).attr("onclick", "xmobile.popup(event, '" + menu + "')");
        });
    };

    /**
     * Sets up the sidebar used by xframework to work on mobile skins.
     *
     * @returns {undefined}
     */
    this.setupXSidebar = function() {
        $("#xsidebar .box-wrap").each(function() {
            xmobile.makePopup($(this), false);
            $(this).addClass("sidebar-popup overlay-popup");
            $("<a>")
                .attr("href", "javascript:void(0)")
                .attr("onclick", "xmobile.popup(event, '" + $(this).attr("id") + "')")
                .text($(this).find(".boxtitle").text())
                .insertBefore(".button-logout");
        });
    };


    /**
     * Makes a popup box out of an existing element.
     *
     * @param {jQuery} element
     * @param {bool} clickClose
     * @param {string} popupId
     * @returns {undefined}
     */
    this.makePopup = function(element, clickClose, popupId) {
        if (!element.length) {
            return;
        }

        if (typeof clickClose === "undefined") {
            clickClose = true;
        }

        element.removeClass().addClass("popup-box");
        element.removeClass("toolbar");

        if (clickClose) {
            element.addClass("click-close");
        }

        if (typeof popupId != "undefined") {
            element.attr("id", popupId);
        }

        element.appendTo("body");
    };

    /**
     * Adds the popup toolbar to the top of a popup box
     *
     * @param {jQuery} element
     * @returns {undefined}
     */
    this.addPopupCloseButtonBar = function(element) {
        if (element.length) {
            element.prepend(
                "<div class='popup-close'>" +
                    "<a href='javascript:void(0)' onclick='xmobile.popupHide(event, true, this)'></a>" +
                "</div>"
            );
        }
    };

    /**
     * Adds a title to the top of a popup box
     *
     * @param {jQuery} element
     * @param {string} title
     * @returns {undefined}
     */
    this.addPopupTitle = function(element, title) {
        if (!element.length) {
            return;
        }

        var code = "<h5>" + title + "</h5>";

        if (element.find(".popup-close").length) {
            element.find(".popup-close").after(code);
        } else {
            element.prepend(code);
        }
    };

    /**
     * Show popup. There's no way to stretch the popup to the entire screen and make it scrollable on Android,
     * since the layer under the popup scrolls, so we just hide the mainscreen element to fix it.
     * We use timeout to make sure all the popup hide actions execute first, otherwise it's messed up.
     *
     * @param {object} ev
     * @param {string} id
     * @param {function} onready
     * @returns {undefined}
     */
    this.popup = function(ev, id, onready) {
        // if this popup is already visible, hide it and return
        if ($("#" + id + ":visible").length) {
            this.popupHide(ev, true);
            return;
        }

        // make adjustments to the popup items
        if (id == "messagetoolbar") {
            if ($("#markmessagemenu-menu li a:first").attr("aria-disabled") == "true") {
                $("#markmessagemenulink, #messagemenulink").addClass("disabled");
            } else {
                $("#markmessagemenulink, #messagemenulink").removeClass("disabled");
            }

        }

        var box = $("#" + id);

        // if some other popup is visible, hide and show the new popup without moving the main screen
        if ($(".popup-box:visible").length) {
            if (!box.hasClass("overlay-popup")) {
                $(".popup-box").fadeOut();
            }
            box.fadeIn();
            return;
        }

        $(".popup-box").hide();

        // show the popup
        setTimeout(function() {
            box.show();
            $("body").addClass("popup-visible");

            var partialWidth = 105;
            var width = xmobile.contentElements.outerWidth();

            xmobile.contentElements.addClass("moved-container").animate(
                {"left": width - partialWidth, "right": (width - partialWidth) * -1},
                300,
                "swing",
                onready /* careful, this fires twice for some reason! */
            );
        }, 0);
    };

    /**
     * Closes a popup box.
     *
     * @param {object} ev
     * @param {bool} slide
     * @param {string} closeButton
     * @returns {undefined}
     */
    this.popupHide = function(ev, slide, closeButton) {
        slide = typeof slide !== "undefined" ? slide : false;

        // if clicked on the close button, prevent the event from going to the parent and executing this function again
        // (if the popup is set to click-close)
        if (typeof ev !== "undefined") {
            ev.stopPropagation();
        }

        // if a popup menu is shown on top of popup-box, fade out the menu and show the underlying popup-box
        // without closing it
        var popup = typeof closeButton !== "undefined" ? $(closeButton).parents(".popup-box") : false;

        if ($(".popup-box:visible").length > 1 && popup) {
            popup.hide();
            return;
        }

        // close the popup
        $("body").removeClass("popup-visible");

        if (slide) {
            xmobile.contentElements
                .removeClass("moved-container")
                .animate({"left": 0, "right": 0}, 300, "swing", function() { $(".popup-box").hide(); });

        } else {
            xmobile.contentElements.removeClass("moved-container").css("left", 0).css("right", 0);
            $(".popup-box").hide();
        }
    };

    /**
     * Assigned to onclick of buttons on popup boxes that display popup menus. We hide and original popup box,
     * and stop propagation, so if the original popup box is click-close it won't move the main screen,
     * then the new popup menu or box will show in its place using the inline onlick function of the button.
     *
     * @param {object} ev
     * @returns {undefined}
     */
    this.popupDisappear = function(ev) {
        $(ev.target).parents(".popup-box").hide();
        ev.stopPropagation();
    };

    /**
     * Creates a timeout for for reformatting the message list on the mail page.
     *
     * @returns {undefined}
     */
    this.setMailTimeout = function() {
        xmobile.mailTimer = setTimeout("xmobile.mailTimout()", 1000);
    };

    /**
     * The function that executes on mail timeout to reformat the message list on the mail page.
     * The function rcmail.addEventListener('listupdate', function(evt) { someFunction(); }); that's supposed to execute
     * after the mail list is loaded is unreliable: it doesn't execute on iPhone and executes sometimes on android, so
     * we also run a timer that runs fixMessageList every second on the mail page to check if the message list was
     * re-loaded (it can be reloaded without refreshing the whole page). Once the list has been modified, we add the
     * "processed" class to the first row of the table so we know it's already been processed.
     *
     * @returns {undefined}
     */
    this.mailTimout = function() {
        xmobile.fixMessageList();
        xmobile.setMailTimeout();
    };

    /**
     * Reformats the message list on the mail page to make it display properly on mobile.
     *
     * @returns {undefined}
     */
    this.fixMessageList = function() {
        // check if list has already been processed
        var firstRow = $("#messagelist tr.message:first");
        if (!firstRow.length || firstRow.hasClass("processed")) {
            return;
        }

        // add the processed class to the first row to indicate that the list was processed
        firstRow.addClass("processed");

        // iterate each row, remove mouseup and mousedown events since they are made typically for desktop use and add
        // our own onclick event that acts as if the row was clicked while holding the ctrl key, which allows us to
        // select multiple rows on iphone
        var newListFormat = $("#aria-label-messagelist").length; // new list format since rc 1.1

        $("#messagelist tr.message").each(function()
        {
            if (newListFormat) {
                var rowId = $(this).attr("id");
                var uid = rcmail.message_list.list.rows[rowId].uid;
                rcmail.message_list.list.rows[rowId].onmousedown = null;
                rcmail.message_list.list.rows[rowId].onmouseup = null;
                $(this).removeAttr("onmouseup").removeAttr("onmousedown").unbind();
                rcmail.message_list.list.rows[rowId].onclick = function(e) {
                    rcmail.message_list.select_row(uid, CONTROL_KEY, true);
                };

                // set up gestures
                xmobile.setupGestureMessageActions(rowId);
            } else {
                var uid = $(this).attr("id").substr(6);

                $(this).removeAttr("onmouseup").removeAttr("onmousedown").unbind().click(
                    function() {
                        rcmail.message_list.select_row(uid, CONTROL_KEY, true);
                    }
                );
            }

            // on ie the subject is not a link - we add the link here
            if (!$(this).find(".subject a").length) {
                var subject = $(this).find(".subject");
                subject.hide();
                subject.html(
                    subject.html().replace(
                        "</span>",
                        "</span><a href='./?_task=mail&_action=show&_mbox=" + rcmail.env.mailbox + "&_uid=" + uid + "'>"
                    ) + "</a>"
                );
                subject.show();
            }

            // since Roundcube 0.8.1 there is fromto, from and to, but we can only display one, so remove the other two
            if ($(this).find(".fromto").length) {
                $(this).find(".from").remove();
                $(this).find(".to").remove();
            } else if ($(this).find(".from").length) {
                $(this).find(".to").remove();
            }
        });

        // remove the onclick event on the subject link so it can execute its href (href is disabled because on desktop
        // viewing messages is accomplished by dblclick)
        $("#messagelist .subject a").removeAttr("onclick");
        $("#messagelist .subject a").removeAttr("onmouseover");

        // add an onclick event on the subject for two reasons:
        // 1. so we stop event propagation and when the user clicks the link, the event won't go down to the undrlying
        //    table row and select the row
        // 2. when the user drags the page to refresh it (and #pageDrag is dragged down showing the refresh bar on top),
        //    we don't execute the href on the message link and don't open the message (return false from onclick won't
        //    execute href)
        $("#messagelist .subject a").on("click", function(e) {
            e.stopPropagation();
        });

        // check search check boxes
        var search_mods = rcmail.env.search_mods[rcmail.env.mailbox] ?
            rcmail.env.search_mods[rcmail.env.mailbox] :
            rcmail.env.search_mods['*'];

        for (var n in search_mods) {
            $('#s_mod_' + n).attr('checked', true);
        }

        // move the paging controls to the end of the message list
        $("#messagelistcontainer").append($("#countcontrols"));
    };

    /**
     * Updates the funnel menu button - adds or removes the active class.
     */
    this.updateFunnel = function() {
        $("#button-funnel").toggleClass("active", this.searchActive || this.filterActive);
        $(".funnel-filter").toggleClass("active", this.filterActive);
        $(".funnel-search").toggleClass("active", this.searchActive);

        if (this.searchActive || this.filterActive) {
            this.blink($("#button-funnel span"));
        }
    };

    /**
     * Applies quick search.
     *
     * @param {object} ev
     * @param {type} element
     * @returns {undefined}
     */
    this.applySearch = function(ev, element) {
        this.searchActive = true;
        rcmail.command("search", "", element);
        xmobile.updateFunnel(true);
        xmobile.popupHide(ev);
    };

    /**
     * Resets quick search.
     *
     * @param {object} ev
     * @param {type} element
     * @returns {undefined}
     */
    this.resetSearch = function(ev, element) {
        this.searchActive = false;
        rcmail.command("reset-search", "", element, ev);
        xmobile.updateFunnel();
        xmobile.popupHide(ev);
    };

    /**
     * Applies a message filter.
     *
     * @param {object} ev
     * @param {string} value
     * @returns {undefined}
     */
    this.applyFilter = function(ev, value) {
        this.filterActive = value != "ALL";
        rcmail.filter_mailbox(value);
        xmobile.updateFunnel();
        xmobile.popupHide(ev);
    };

    /**
     * Blinks an element by fading it in and out twice.
     *
     * @param {jQuery} element
     * @returns {undefined}
     */
    this.blink = function(element) {
        element.fadeOut("slow", function() {
            element.fadeIn("slow", function() {
                element.fadeOut("slow", function() {
                    element.fadeIn("slow");
                });
            });
        });
    };

    /**
     * Update the box that displays the current mail folder.
     *
     * @returns {undefined}
     */
    this.updateCurrentFolder = function() {
        $("#current-folder span").html($("#mailboxcontainer li.selected a").html());
    };

    /* Add or remove the CC rows on the compose page. The original has two links, one to add and one to remove
     * the rows, but we use the same buttons in the popup to handle both.
     *
     * @param {string} rowType
     * @returns {undefined}
     */
    this.toggleCC = function(rowType) {
        // checking for style instead of is(":visible") because the latter doesn't work properly in Chrome if the
        // element is not a block - and this is a tr
        var style = $("#compose-" + rowType).attr("style");

        if (typeof style == "undefined" || style == "display: none;") {
            UI.show_header_row(rowType);
        } else {
            UI.hide_header_row(rowType);
        }
    };

    /**
     * Initializes the drag-down-to-refresh gesture.
     *
     * @returns {undefined}
     */
    this.setupGestureRefresh = function() {
        if (!$("#messagelist").length || !$("#mainscreencontent").length) {
            return;
        }

        var limit = 50;
        var target = $("#mainscreencontent");
        var screen = $("#mainscreen");
        var scroll = $("#messagelistcontainer");

        var hm = new Hammer(document.getElementById('mainscreencontent'));

        // can't use standard event handling because it'll prevent the page from scrolling the messages
        // so we're using the low-level input events
        hm.on("hammer.input", function(ev) {

           if (ev.direction != Hammer.DIRECTION_NONE &&
               ev.direction != Hammer.DIRECTION_UP &&
               ev.direction != Hammer.DIRECTION_DOWN
           ) {
               return;
           }

            var delta = ev.deltaY;

            if (delta > limit) {
                delta = limit;
            }

            if (delta < 0) {
                delta = 0;
            }

           if (ev.isFirst) {
               // if the messages are scrolled to top, set the flag that the refresh dragging process is started
               if (!scroll.scrollTop()) {
                   xmobile.panRefreshAllowed = true;
               }
               return;
           }

           if (ev.isFinal) {
               if (!xmobile.panRefreshAllowed) {
                   return;
               }

               var restore = function() {
                   target.css("top", 0).removeClass("gesture-refresh");
                   screen.removeClass("refresh").removeClass("ready");
                   scroll.css("overflow-y", "auto");
                   $(window).unbind("touchmove");
                   xmobile.panRefreshAllowed = false;
               };

               // if dragged till the end, refresh
               if (delta >= limit) {
                    rcmail.command("checkmail", "", this, ev);
                    // reset the window to the original state, delay a bit so the user knows what's happening
                    setTimeout(restore, 400);
                } else {
                    setTimeout(restore, 0);
                }

               return;
           }

           if (ev.direction == Hammer.DIRECTION_DOWN) {
                if (!xmobile.panRefreshAllowed) {
                     return;
                }

                // prevent ios from pulling the entire page down, prevent chrome from refreshing the entire page
                $(window).bind("touchmove", function(e) { e.preventDefault(); });

                // drag the target element down, disable scrolling on the message list
                screen.addClass("refresh");
                if (delta == limit) {
                    screen.addClass("ready");
                }

                scroll.css("overflow-y", "hidden");
                target.addClass("gesture-refresh");
                target.css("top", delta);
                return;
           }

           if (ev.direction == Hammer.DIRECTION_UP) {
                if (!xmobile.panRefreshAllowed) {
                     return;
                }

                // drag the target element up
                target.css("top", delta);
                if (delta < limit) {
                    screen.removeClass("ready");
                }
                return;
           }
        });
    };

    /**
     * Sets up the message view gestures.
     *
     * @returns {undefined}
     */
    this.setupGestureMessageView = function() {
        if (!$("#messagecontent").length) {
            return;
        }

        var readyThreshold = 70;
        var content = $("#mainscreencontent");
        var target = $("#messageheader");
        var background = $("#mailview-right");
        var screen = $("#mainscreen");

        target.hammer(
            {
                direction: Hammer.DIRECTION_HORIZONTAL,
                threshold: 10
            }
        ).bind("pan", function(ev) {
            // get panning direction
            if (ev.gesture.offsetDirection == Hammer.DIRECTION_LEFT) {
                var direction = "prev";
            } else if (ev.gesture.offsetDirection == Hammer.DIRECTION_RIGHT) {
                var direction = "next";
            } else {
                // ignore all other directions
                return;
            }

            // check if next / prev are disabled because there are no more messages
            var disabled = (direction == "prev" && $(".prevpage").hasClass("disabled")) ||
                           (direction == "next" && $(".nextpage").hasClass("disabled"));

            // add the generic panning class to the container
            background.addClass("gesture-pan");

            // limit delta
            var delta = ev.gesture.deltaX;
            if (delta > readyThreshold) {
                delta = readyThreshold;
            }

            if (delta < readyThreshold * -1) {
                delta = readyThreshold * -1;
            }

            // panning completed
            if (ev.gesture.isFinal) {
                // threshold that must be exceeded to trigger the action
                if (!disabled && Math.abs(delta) == readyThreshold) {
                    content.fadeOut(200, function() {
                        screen.addClass("progress");
                        setTimeout(function() {
                            rcmail.command(direction == "prev" ? "previousmessage" :  "nextmessage", "", this, "click");
                        }, 100);
                    });
                    return;
                }

                // the threshold was not reached, reset everything
                target.css("left", 0);
                target.css("right", 0);
                background.removeClass("gesture-pan");
                return;
            }

            // panning in progress, add the correct classes to display background images and move the target
            if (direction == "prev") {
                background.removeClass("next");
            } else {
                background.removeClass("prev");
            }
            background.addClass(direction);

            if (disabled) {
                background.addClass("disabled");
            } else {
                background.removeClass("disabled");

                if (Math.abs(delta) == readyThreshold) {
                    background.addClass("ready");
                } else {
                    background.removeClass("ready");
                }
            }

            target.css("left", delta);
            target.css("right", delta * -1);
        });
    };

    /**
     * Selects a single message in the message list.
     *
     * @param {object} ev
     * @param {string} rowId
     * @returns {undefined}
     */
    this.selectMessage = function(ev, rowId) {
        rcmail.message_list.select_row(rcmail.message_list.list.rows[rowId].uid, '', true);
    };

    /**
     * Deletes a single message from the message list.
     *
     * @param {object} ev
     * @param {string} rowId
     * @returns {undefined}
     */
    this.deleteMessage = function(ev, rowId) {
        xmobile.selectMessage(ev, rowId);
        rcmail.command('delete', '', this, ev);
        rcmail.message_list.clear_selection();
        $("#mal-" + rowId).hide();
    };

    /**
     * Flags a single message in the message list.
     *
     * @param {object} ev
     * @param {string} rowId
     * @returns {undefined}
     */
    this.flagMessage = function(ev, rowId) {
        this.selectMessage(ev, rowId);
        rcmail.command('mark', 'flagged', this, ev);
        rcmail.message_list.clear_selection();
        $("#mal-" + rowId).addClass("flagged");
        this.hideMessageActions(rowId);
    };

    /**
     * Unflags a single message in the message list.
     *
     * @param {object} ev
     * @param {string} rowId
     * @returns {undefined}
     */
    this.unflagMessage = function(ev, rowId) {
        this.selectMessage(ev, rowId);
        rcmail.command('mark', 'unflagged', this, ev);
        rcmail.message_list.clear_selection();
        $("#mal-" + rowId).removeClass("flagged");
        this.hideMessageActions(rowId);
    };

    this.markMessageAsRead = function(ev, rowId) {
        this.selectMessage(ev, rowId);
        rcmail.command('mark', 'read', this, ev);
        rcmail.message_list.clear_selection();
    };

    this.markMessageAsUnread = function(ev, rowId) {
        this.selectMessage(ev, rowId);
        rcmail.command('mark', 'unread', this, ev);
        rcmail.message_list.clear_selection();
    };

    /**
     * Opens a popup that shows more actions for a selected message.
     *
     * @param {object} ev
     * @param {string} rowId
     * @returns {undefined}
     */
    this.showMoreOnMessage = function(ev, rowId) {
        this.selectMessage(ev, rowId);
        this.clearSelectionOnPopupHide = true;
        this.hideMessageActions(rowId);
        xmobile.popup(ev, "messagetoolbar");
    };

    /**
     * Closes the inline message menu opened by the swipe-left gesture.
     *
     * @param {string} rowId
     * @returns {undefined}
     */
    this.hideMessageActions = function(rowId) {
        $("#" + rowId).animate({ width: $("#messagelist").width() }, 300);
    };

    /**
     * Adds the gesture html to each message row and sets up the actions.
     *
     * @param {string} rowId
     * @returns {undefined}
     */
    this.setupGestureMessageActions = function(rowId) {

        // get row
        var row = $("#" + rowId);

        // add the left/right action boxes to the row

        var leftBox = $("<div />").addClass("message-action-read").attr("id", "mar-" + rowId);

        var rightBox = $(
            "<div class='message-action-links'>" +
                "<a class='mal-more' href='javascript:void(0)' onclick='xmobile.showMoreOnMessage(event, \"" + rowId + "\")'></a>" +
                "<a class='mal-flag' href='javascript:void(0)' onclick='xmobile.flagMessage(event, \"" + rowId + "\")'></a>" +
                "<a class='mal-unflag' href='javascript:void(0)' onclick='xmobile.unflagMessage(event, \"" + rowId + "\")'></a>" +
                "<a class='mal-delete' href='javascript:void(0)' onclick='xmobile.deleteMessage(event, \"" + rowId + "\")'></a>" +
            "</div>"
        ).attr("id", "mal-" + rowId);

        // pass on the relevant row classes to the boxes so they display the proper icons
        if (row.hasClass("flagged")) {
            rightBox.addClass("flagged");
        }

        if (row.hasClass("unread")) {
            leftBox.addClass("unread");
        }

        row.before(rightBox);
        row.before(leftBox);

        // register left/right drag
        row.hammer({
            direction: Hammer.DIRECTION_HORIZONTAL,
            threshold: 30
        }).bind("pan", function(ev) {

            var fullWidth = $("#messagelist").width();

            // get panning direction
            if (ev.gesture.offsetDirection == Hammer.DIRECTION_LEFT) {
                var direction = "right";
            } else if (ev.gesture.offsetDirection == Hammer.DIRECTION_RIGHT) {
                var direction = "left";
            } else {
                return;
            }

            // starting the pan, set the direction and which action boxes the pan is applicable to: left or right
            if (!xmobile.panDirection) {
                xmobile.panDirection = direction;
                if (fullWidth == row.width() && xmobile.panDirection == "right") {
                    xmobile.panActionBoxes = "left";
                    $("#mar-" + rowId).show();
                } else {
                    xmobile.panActionBoxes = "right";
                    $("#mal-" + rowId).show();
                }
            }

            var delta = ev.gesture.deltaX;
            var boxWidth = xmobile.panActionBoxes == "left" ? 60 : 180;

            if (xmobile.panDirection == "left" && delta < boxWidth * -1) {
                delta = boxWidth * -1;
            }

            if (xmobile.panDirection == "right" && delta > boxWidth) {
                delta = boxWidth;
            }

            if ((xmobile.panDirection == "left" && delta > 0) ||
                (xmobile.panDirection == "right" && delta < 0)) {
                delta = 0;
            }

            // panning completed, either snap back or open fully
            if (ev.gesture.isFinal) {
                if (xmobile.panActionBoxes == "left") {
                    if (delta > 50) {
                        // unread box fully open (50 instead of 60 to give it 10px of margin grace)
                        if ($("#mar-" + rowId).hasClass("unread")) {
                            xmobile.markMessageAsRead(ev, rowId);
                            row.animate({ "margin-left": 0 }, 100, "swing", function() {
                                $("#mar-" + rowId).removeClass("unread");
                            });
                        } else {
                            xmobile.markMessageAsUnread(ev, rowId);
                            row.animate({ "margin-left": 0 }, 100, "swing", function() {
                                $("#mar-" + rowId).addClass("unread");
                            });
                        }
                    } else {
                        // didn't open all the way, just close it
                        row.animate({ "margin-left": 0 }, 100);
                    }
                } else {
                    if (direction == "left") {
                        if (row.width() < fullWidth - boxWidth * 0.3) {
                            if (xmobile.actionRowOpen && xmobile.actionRowOpen != rowId) {
                                xmobile.hideMessageActions(xmobile.actionRowOpen);
                            }
                            xmobile.actionRowOpen = rowId;
                            row.animate({ width: fullWidth - boxWidth }, 100);

                        } else {
                            row.animate({ width: fullWidth }, 100, "swing", function() { row.css("width", "100%"); });
                            $("#mal-" + rowId).hide();
                        }
                    } else {
                        if (row.width() > fullWidth - boxWidth * 0.7) {
                            row.animate({ width: fullWidth }, 100, "swing", function() { row.css("width", "100%"); });
                            xmobile.actionRowOpen = false;
                            $("#mal-" + rowId).hide();
                        } else {
                            row.animate({ width: fullWidth - boxWidth }, 100);
                        }
                    }
                }

                xmobile.panDirection = false;
                xmobile.panActionBoxes = false;
                return;
            }

            // panning in progress. all the action boxes are hidden to start with, otherwise they get shown for a split
            // second when the list is scrolled, we need to show them

            if (xmobile.panActionBoxes == "left") {
                row.css("margin-left", delta + "px");
            } else {
                if (xmobile.panDirection == "left") {
                    if (row.width() > fullWidth - boxWidth) {
                        row.width(fullWidth + delta);
                    }
                } else {
                    if (row.width() < fullWidth) {
                        row.width(fullWidth - boxWidth + delta);
                    }
                }
            }
        });
    };
};

/**
 * This object can be passed to rcmail.section_select to manually load a settings page.
 *
 * @param {string} value
 * @returns {settingsLoader}
 */
function settingsLoader(value)
{
    this.value = value;
    this.get_single_selection = function() {
        return this.value;
    };
};

