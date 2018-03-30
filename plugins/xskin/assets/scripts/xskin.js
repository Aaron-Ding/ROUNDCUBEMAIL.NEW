/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

/* global rcmail, encodeURIComponent, Infinity, UI, xskin, xframework */

/**
 * Create the console.log() shortcut if it doesn't exist.
 */
if (typeof(q) != "function") {
    function q(variable) { console.log(variable); };
}

$(document).ready(function() {
    xskin.afterReady();
});

var xskin = new function()
{
    /**
     * Executed on document ready.
     */
    this.afterReady = function() {
        // in firefox the popup window will disappear on select's mouse up
        $("#quick-skin-change select").on("mouseup", function(event) { event.stopPropagation(); });

        // don't make any more modification to larry
        if (rcmail.env.xskin == "larry") {
            return;
        }

        // remove text from icon buttons
        if ($("a.iconbutton").length) {
            $("a.iconbutton").html("");
        }

        if ($("body.skin-icloud #login-form").length) {
            // add a submit button
            $("#rcmloginpwd").after($("<button/>").attr("id", "custom-login-submit").attr("type", "submit"));

            // add placeholders to username and password inputs
            $("#rcmloginuser").attr("placeholder", $("label[for=rcmloginuser]").text());
            $("#rcmloginpwd").attr("placeholder", $("label[for=rcmloginpwd]").text());
        }

        if (rcmail.env.xskin == "droid") {
            xskin.enableMaterialDesign();
        } else {
            xskin.enableSwitchboxes();
        }

        if ($("#printmessageframe").length) {
            $("body").addClass("print-message");
        }
    };

    this.enableMaterialDesign = function() {
        // create the wave effect: size is the initial size of the wave span, it could be 1, 2, 3... pixels, depending
        // on the size of the container, then we append the span to the container and transform it using css to 100
        // times its original size, fading it out at the same time, then we remove the span.
        $(".listing td.name, .listing td.section, .listing li.listitem, #directorylist li.addressbook, " +
          ".listing li.mailbox a, .toolbar a.button, .xmobile #taskbar > a")
            .addClass("wave-container")
            .on("click", function(event) {
                var container = $(this);
                var size = Math.ceil(Math.max(container.outerWidth(), container.outerHeight()) * 2 / 100);
                var wave = $("<span/>")
                    .addClass("wave")
                    .css({ height: size, width: size, left: (event.offsetX) + "px", top: (event.offsetY) + "px"});
                container.append(wave);
                setTimeout(function() { wave.remove(); }, 600);
            });

        // checkboxes and radio boxes
        var index = 0;
        $("input[type='checkbox'], input[type='radio']").not(".xformatting input").each(function() {
            var input = $(this);
            var position = input.css("position");
            var id = input.attr("id");

            // if the input doesn't have an id, create one and add it
            if (!id) {
                id = "material-" + index;
                input.attr("id", id);
                index++;
            }

            // create the label element
            var label = $("<label/>")
                .addClass("material " + input.attr("type"))
                .attr("for", id)
                .css("margin", input.css("margin"));

            // if the input is absolute or fixed, position the label the same way
            if (position == "absolute" || position == "fixed") {
                label.css({
                    position: position,
                    left: input.css("left"),
                    right: input.css("right"),
                    top: input.css("top"),
                    bottom: input.css("bottom"),
                    'z-index': input.css("z-index")
                });
            }

            // stop propagation is needed in some cases where there's another action under the label, for example
            // settings / folders
            label.on("click", function(e) {
                var el = $(this);
                el.addClass("flash");
                setTimeout(function() { el.removeClass("flash"); }, 50);
                e.stopPropagation();
            });

            // add the label right after the checkbox
            input.addClass("material-input").after(label);
        });
    };

    /**
     * Changes the skin color.
     *
     * @param {string} color
     * @returns {undefined}
     */
    this.changeColor = function(color) {
        // change body class
        $("body").removeClass(function(index, cls) {
            return (cls.match(/color-\S+/g) || []).join(' ');
        }).addClass("color-" + color);

        // save color in user settings
        rcmail.save_pref({ name: "xcolor_" + rcmail.env.xskin, value: color });
    };

    /**
     * Reloads the page adding the skin url parameter: triggered by the quick skin change select.
     *
     * @returns {undefined}
     */
    this.quickSkinChange = function() {
        var skin = $("#quick-skin-change select").val();
        if (skin) {
            location.href = xframework.replaceUrlParam("skin", skin);
        }
    };

    /**
     * Switches the mobile skin to desktop and sets the cookie to remember this. Sets the cookie to +10 years.
     */
    this.disableMobileSkin = function() {
        var expires = new Date();
        expires.setFullYear(expires.getFullYear() + 10);
        rcmail.set_cookie("rcs_disable_mobile_skin", 1, expires);
        location.reload();
    };

    /**
     * Switches the desktop skin back to mobile and removes the cookie.
     */
    this.enableMobileSkin = function() {
        var expires = new Date();
        expires.setFullYear(expires.getFullYear() - 10);
        rcmail.set_cookie("rcs_disable_mobile_skin", "", expires);
        location.reload();
    };

    /**
     * Replaces all checkbox input with iOS-like switches. We're invoking the checkbox click function manually instead
     * of setting the 'for' attribute on the label because we want the checkbox events to be triggered, and in some
     * cases they don't get triggered with just the for attribute. (See settings / folder list.)
     */
    this.enableSwitchboxes = function() {
        var index = 0;

        $("input[type='checkbox']").each(function() {
            var checkbox = $(this);
            var position = checkbox.css("position");
            var id = checkbox.attr("id");

            // if the checkbox doesn't have an id, create one and add it
            if (!id) {
                id = "switchbox-" + index;
                checkbox.attr("id", id);
                index++;
            }

            // create the switch element
            var switchbox = $("<label/>")
                .addClass("switchbox")
                .attr("for", id)
                .css("margin", checkbox.css("margin"));

            // if the checkbox is absolute or fixed, position the switchbox the same way
            if (position == "absolute" || position == "fixed") {
                switchbox.css({
                    position: position,
                    left: checkbox.css("left"),
                    right: checkbox.css("right"),
                    top: checkbox.css("top"),
                    bottom: checkbox.css("bottom"),
                    'z-index': checkbox.css("z-index")
                });
            }

            // stop propagation is needed in some cases where there's another action under the label, for example
            // settings / folders
            switchbox.on("click", function(e) {
                e.stopPropagation();
            });

            // add the switchbox right after the checkbox
            checkbox.addClass("switchbox-input").after(switchbox);
        });
    };
};
