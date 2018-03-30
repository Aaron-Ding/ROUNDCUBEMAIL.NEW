/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

/* global xskin */

$(document).ready(function() {
    xdesktop.afterReady();
});

$(window).resize(function() {
    xdesktop.windowResize();
});

var xdesktop = new function()
{
    /**
     * Executed after the document is ready.
     *
     * @returns {undefined}
     */
    this.afterReady = function()  {
        setTimeout(function() { xdesktop.windowResize(); }, 0);

        if ($(".compact-message-list #listoptions fieldset").length) {
            $("#listoptions fieldset:first").remove();
        }
    };

    /**
     * Executed on window resize and after document ready.
     *
     * @returns {undefined}
     */
    this.windowResize = function() {
        // hide the filter combo and quicksearch bar if they overlay the toolbar
        var toolbar = $(".toolbar");
        if (toolbar.length) {
            var width = $(".toolbar").width() + 5;

            var element = $("#searchfilter");
            if (element.length) {
                element.css("visibility", element.offset().left < width ? "hidden" : "visible");
            }

            element = $("#quicksearchbar");
            if (element.length) {
                element.css("visibility", element.offset().left < width ? "hidden" : "visible");
            }
        }
    };

};


