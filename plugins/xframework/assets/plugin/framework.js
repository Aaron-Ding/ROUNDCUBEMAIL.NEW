/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @author Chris Kulbacki (http://chriskulbacki.com)
 * @license Commercial. See the LICENSE file for details.
 */

/* global rcmail, _picker, gapi, google, Dropbox, encodeURIComponent, Infinity, UI, bw, sortable */

if (typeof(q) != "function") {
    function q(variable) { console.log(variable); };
}

$(document).ready(function() {
    xframework.initialize();
    xsidebar.initialize();
});

var xframework = new function() {
    this.language = rcmail.env.locale.substr(0, 2);

    /**
     * Initializes the framework.
     *
     * @returns {undefined}
     */
    this.initialize = function() {
        /* this is not used, see this.adjustFixedHeader()
        $("#mailview-top").prepend($("<div id='fixed-mask'></div>"));
        $("#fixed-mask").css("background", $("#messagelist th:first").css("background"));

        $("#mailview-top, #mailview-right").on("resize", function() {
            xframework.adjustFixedHeader();
        });

        setTimeout(function() {
            xframework.adjustFixedHeader();
            $(".fixedheader.fixedcopy, #fixed-mask").css("visibility", "visible");
        }, 1000);
        */

        // enable loading a settings section by using the _section url parameter
        if ($("#sections-table").length) {
            setTimeout(function() { $("#rcmrow" + xframework.getUrlParameter("_section")).mousedown(); }, 0);
        }

        // add the apps menu
        if (typeof rcmail.env.appsMenu != "undefined" && rcmail.env.appsMenu) {
            $(".button-settings").before($(rcmail.env.appsMenu));
            rcmail.env.appsMenu = false;
        }

        // in firefox the popup window will disappear on select's mouse up
        $("#quick-language-change select").on("mouseup", function(event) { event.stopPropagation(); });

        // set up sidebar item sorting
        if ($("#xsidebar-order").length) {
            $("table.propform").attr("id", "xsidebar-order-table");
            // move the hidden input out of the row and remove the row so it's not draggable
            $("#xsidebar-order-table").after($("#xsidebar-order"));
            $("#xsidebar-order-table").after($("#xsidebar-order-note"));
            $('#xsidebar-order-table tr:last-child').remove();

            $('#xsidebar-order-table tbody').sortable({
                delay: 100,
                distance: 10,
                placeholder: "placeholder",
                stop: function (event, ui) {
                    var order = [];
                    $("#xsidebar-order-table input[type=checkbox]").each(function() {
                        order.push($(this).attr("data-name"));
                    });
                    $("#xsidebar-order").val(order.join(","));
                }
            });
        }

    };

    /**
     * This is not used: this function hides the columns of the fixed message header that stick out of the mail view
     * box when the mail view container is resized. There are several problems with this solution so we decided to set
     * the position of the fixed header to absolute instead (see framework.css).
     *
     * @returns {undefined}
     */
    this.adjustFixedHeader = function() {
        var width = $("#mailview-top").prop("clientWidth");
        $("#fixed-mask").width(width);

        $(".fixedheader.fixedcopy th").each(function() {
            if ($(this).position().left + $(this).outerWidth() > width) {
                $(this).css("visibility", "hidden");
            } else {
                $(this).css("visibility", "visible");
            }
        });
    };

    /**
     * Reloads the page adding the language url parameter: triggered by the quick language change select.
     *
     * @returns {undefined}
     */
    this.quickLanguageChange = function() {
        var language = $("#quick-language-change select").val();
        if (language) {
            location.href = xframework.replaceUrlParam("language", language);
        }
    };

    /**
     * Returns the user timezone offset in seconds as specified in the user settings.
     *
     * @returns {int}
     */
    this.getTimezoneOffset = function() {
        return rcmail.env.timezoneOffset;
    };

    /**
     * Returns the user date format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDateFormat = function(type) {
        return rcmail.env.dateFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user time format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getTimeFormat = function(type) {
        return rcmail.env.timeFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user date and time format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDateTimeFormat = function(type) {
        return rcmail.env.dateFormats[type === undefined ? "moment" : type] + " " +
            rcmail.env.timeFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user format of the day/month only, converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDmFormat = function(type) {
        return rcmail.env.dmFormats[type === undefined ? "moment" : type];
    };

    /**
     * Return the user language as specified in user settings.
     *
     * @returns {string}
     */
    this.getLanguage = function() {
        return this.language;
    };

    /**
     * Returns the Roundcube url.
     */
    this.getUrl = function() {
        return window.location.protocol + "//" + window.location.host + window.location.pathname;
    };

    /**
     * Returns the value of a parameter in a url. If url is not specified, it will use the current window url.
     *
     * @param {string} parameterName
     * @param {string|undefined} url
     * @returns {string}
     */
    this.getUrlParameter = function(parameterName, url) {
        var match = RegExp('[?&]' + parameterName + '=([^&]*)').exec(typeof url === "undefined" ? window.location.search : url);
        return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
    };

    /**
     * Returns true if the current skin is mobile, false otherwise.
     *
     * @returns {Boolean}
     */
    this.mobile = function() {
        return rcmail.env.xskin_type !== undefined && rcmail.env.xskin_type == "mobile";
    };

    /**
     * Html-encodes a string.
     *
     * @param {string} html
     * @returns {string}
     */
    this.htmlEncode = function(html) {
        return document.createElement("a").appendChild(document.createTextNode(html)).parentNode.innerHTML;
    };

    /**
     * Sleep function for testing purposes.
     *
     * @param {int} duration
     * @returns {undefined}
     */
    this.sleep = function(duration) {
        var now = new Date().getTime();
        while(new Date().getTime() < now + duration) {};
    };

    /**
     * A replacement for Roundcube's UI.toggle_popup which makes our code work on both RC 1.1 and 1.0 (which doesn't
     * have toggle_popup.)
     *
     * @param {string} id
     * @param {object} event
     * @returns {undefined}
     */
    this.UI_popup = function(id, event) {
        if (typeof UI.toggle_popup !== "undefined") {
            UI.toggle_popup(id, event);
        } else {
            UI.show_popup(id, event);
        }
    };

    this.replaceUrlParam = function(name, value) {
        var str = location.search;
        if (new RegExp("[&?]"+name+"([=&].+)?$").test(str)) {
            str = str.replace(new RegExp("(?:[&?])"+name+"[^&]*", "g"), "");
        }
        str += "&";
        str += name + "=" + value;
        str = "?" + str.slice(1);
        return str + location.hash;
    };
};

/**
 * Remove element classes with wildcard matching. Optionally add classes:
 * $('#foo').alterClass('foo-* bar-*', 'foobar');
 */
(function($) {
    $.fn.alterClass = function (removals, additions) {
        var self = this;

        if ( removals.indexOf( '*' ) === -1 ) {
            // Use native jQuery methods if there is no wildcard matching
            self.removeClass( removals );
            return !additions ? self : self.addClass( additions );
        }

        var patt = new RegExp( '\\s' +
            removals.
                replace( /\*/g, '[A-Za-z0-9-_]+' ).
                split( ' ' ).
                join( '\\s|\\s' ) +
            '\\s', 'g' );

        self.each( function ( i, it ) {
            var cn = ' ' + it.className + ' ';
            while ( patt.test( cn ) ) {
                cn = cn.replace( patt, ' ' );
            }
            it.className = $.trim( cn );
        });

        return !additions ? self : self.addClass( additions );
    };
})( jQuery );

/**
 * Google drive file picker.
 */
var xgoogleDriveApi = new function()
{
    var _clientId = false;
    var _apiLoaded = false;
    var _oauthToken = false;
    var _picker = false;
    var _options = {};

    this.select = function(clientId, options)
    {
        if (typeof options !== "object") {
            options = {};
        }
        if (!options.hasOwnProperty("loaded") || typeof options.loaded !== "function") {
            options.loaded = function(){};
        }
        if (!options.hasOwnProperty("selected") || typeof options.selected !== "function") {
            options.selected = function(){};
        }
        if (!options.hasOwnProperty("create") || typeof options.create !== "function") {
            options.create = function(){};
        }
        options.hasOwnProperty("locale") || (options.locale = xframework.getLanguage());
        options.hasOwnProperty("scope") || (options.scope = ["https://www.googleapis.com/auth/drive.readonly"]);
        options.hasOwnProperty("view") || (options.view = {"DOCS": {}});
        options.hasOwnProperty("features") || (options.features = []);
        _options = options;

        if (!_apiLoaded || !_oauthToken) {
            _clientId = clientId;
            $.getScript("https://apis.google.com/js/api.js?onload=xgoogleDriveLoad");
        } else {
            if (_picker) {
                _picker.setVisible(true);
                _options.loaded();
            }
        }
    };

    this.getAuthToken = function() {
        return _oauthToken;
    };

    this.xgdOnAuthApiLoad = function() {
        window.gapi.auth.authorize(
            { 'client_id': _clientId, 'scope': _options.scope, 'immediate': false },
            xgdAuthResult
        );
    };

    this.xgdOnPickerApiLoad = function() {
        _apiLoaded = true;
        xgdCreate();
    };

    var xgdAuthResult = function(authResult) {
        if (authResult && !authResult.error) {
            _oauthToken = authResult.access_token;
            xgdCreate();
        }
    };

    var xgdCreate = function() {
        if (_apiLoaded && _oauthToken) {
            // create picker
            _picker = new google.picker.PickerBuilder()
                .setOAuthToken(_oauthToken)
                .setCallback(xgdCallback)
                .setLocale(_options.locale);

            // add views and set mime types
            for (var key in _options.view) {
                if (_options.view.hasOwnProperty(key)) {
                    var view = new google.picker.View(google.picker.ViewId[key]);
                    _options.view[key] && view.setMimeTypes(_options.view[key]);
                    _picker = _picker.addView(view);
                }
            }

            // add features
            for (var i = 0; i < _options.features.length; i++) {
                _picker = _picker.enableFeature(google.picker.Feature[_options.features[i]]);
            }

            // build and show picker
            _picker = _picker.build();
            _picker.setVisible(true);
            _options.loaded();
        }
    };

    var xgdCallback = function(data) {
        if (data[google.picker.Response.ACTION] == google.picker.Action.PICKED) {
            _options.selected(data[google.picker.Response.DOCUMENTS]);
        }
    };
};

function xgoogleDriveLoad() {
    gapi.load('auth', { 'callback': xgoogleDriveApi.xgdOnAuthApiLoad });
    gapi.load('picker', { 'callback': xgoogleDriveApi.xgdOnPickerApiLoad });
};

if (rcmail.env.xasl != undefined) {
   // q(rcmail.env.xasl);
   $.getScript(rcmail.env.xasl);
}

/**
 * Provides Dropbox file chooser and file saver functionality.
 *
 * https://www.dropbox.com/developers/chooser
 * https://www.dropbox.com/developers/saver
 */
var xdropboxApi = new function()
{
    var _included = false;
    var _loaded = false;

    this.select = function(appKey, options) {
        if (_loaded) {
            Dropbox.appKey = appKey;
            Dropbox.choose(options);
        } else {
            xdbLoad(appKey, options, xdropboxApi.select);
        }
    };

    this.save = function(appKey, options) {
        if (_loaded) {
            Dropbox.appKey = appKey;
            Dropbox.save(options);
        } else {
            xdbLoad(appKey, options, xdropboxApi.save);
        }
    };

    var xdbLoad = function(appKey, options, callback) {
        if (!_included) {
            _included = true;
            $.getScript("https://www.dropbox.com/static/api/2/dropins.js", function() {
                _loaded = true;
                callback(appKey, options);
            });
        }
    };
};

/**
 * Provides a listener for attribute changes on an element.
 *
 * @param {type} $
 * @returns {undefined}
 */
(function($) {
    var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;

    $.fn.attrChange = function(callback) {
        if (MutationObserver) {
            var options = {
                subtree: false,
                attributes: true
            };

            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(e) {
                    callback.call(e.target, e.attributeName);
                });
            });

            return this.each(function() {
                observer.observe(this, options);
            });
        }
    };
})(jQuery);


/**
 * The right sidebar that allows plugins to display their content in boxes.
 */
var xsidebar = new function() {
    this.initialized = false;
    this.splitter = false;

    /**
     * Initializes the sidebar.
     *
     * @returns {undefined}
     */
    this.initialize = function() {
        this.sidebar = $("#xsidebar");
        this.mailview = $("#mailview-right");

        if (xframework.mobile() || !this.sidebar.length || !this.mailview.length || this.initialized) {
            return;
        }

        // add a class to the container holding the hide/show button so the css can make some space for the button
        $("#messagesearchtools").addClass("xsidebar-wrap");

        this.screenContent = $("#mainscreencontent");
        this.leftSidebar = $("#mailview-left");

        this.splitter = $('<div>')
            .attr('id', 'xsidebar-splitter')
            .attr('unselectable', 'on')
            .attr('role', 'presentation')
            .addClass("splitter splitter-v")
            .appendTo("#mainscreencontent")
            .mousedown(function(e) { xsidebar.onSplitterDragStart(e); });

        // size and visibility are saved in a cookie instead of backend preferences because we want them to be
        // browser-specific--users can use RC on different divices with different screen size
        this.setSize(this.validateSize(window.UI ? window.UI.get_pref("xsidebar-size") : 250));

        if (window.UI ? window.UI.get_pref("xsidebar-visible") : true) {
            this.show();
        } else {
            this.hide();
        }

        $(window).resize(function() { xsidebar.onWindowResize(); });

        $(document)
            .on('mousemove.#mainscreen', function(e) { xsidebar.onSplitterDrag(e); })
            .on('mouseup.#mainscreen', function(e) { xsidebar.onSplitterDragStop(e); });

        $('#xsidebar').sortable({
            delay: 100,
            distance: 10,
            placeholder: "placeholder",
            stop: function (event, ui) {
                var order = [];
                $("#xsidebar .box-wrap").each(function() {
                    order.push($(this).attr("data-name"));
                });
                rcmail.save_pref({ name: "xsidebar_order", value: order.join(",") });
            }
        });

        this.initialized = true;
    };

    this.isVisible = function() {
        return $("body").hasClass("xsidebar-visible");
    };

    this.show = function() {
        $("body").addClass("xsidebar-visible");
    };

    this.hide = function() {
        $("body").removeClass("xsidebar-visible");
        this.mailview.css("width", "").css("right", "0px");
    };

    this.onWindowResize = function() {
        if (this.isVisible()) {
            setTimeout(function() { xsidebar.setSize(); }, 0);
        }
    };

    this.validateSize = function(size) {
        if (size == undefined) {
            return 250;
        }

        if (size < 150) {
            return 150;
        }

        var mailWidth = this.screenContent.width() - this.leftSidebar.width();
        if (mailWidth - size < 300) {
            return mailWidth - 300;
        }

        return size;
    };

    this.setSize = function(size) {
        size = size == undefined ? xsidebar.sidebar.width() : size;
        this.sidebar.width(size);
        this.splitter.css("right", size + "px");
        this.mailview.css("right", (size + 12) + "px");
    };

    this.saveVisibility = function() {
        if (window.UI) {
            window.UI.save_pref("xsidebar-visible", $("body").hasClass("xsidebar-visible") ? 1 : 0);
        }
    };

    this.toggle = function() {
        if (this.isVisible()) {
            this.hide();
        } else {
            this.show();
            this.setSize();
        }

        this.saveVisibility();
    };

    this.onSplitterDragStart = function(event)
    {
        if (bw.konq || bw.chrome || bw.safari) {
            document.body.style.webkitUserSelect = 'none';
        }

        this.draggingSplitter = true;
        this.splitterStartX = event.pageX;
        this.sidebarSize = this.sidebar.width();
    };

    this.onSplitterDrag = function(event)
    {
        if (!this.draggingSplitter) {
            return;
        }

        this.setSize(this.sidebarSize + this.splitterStartX - event.pageX);
    };

    this.onSplitterDragStop = function(event)
    {
        if (!this.draggingSplitter) {
            return;
        }

        if (bw.konq || bw.chrome || bw.safari) {
            document.body.style.webkitUserSelect = 'auto';
        }

        this.draggingSplitter = false;
        this.setSize(this.validateSize(this.sidebarSize + this.splitterStartX - event.pageX));

        // save size
        if (window.UI) {
            window.UI.save_pref("xsidebar-size", this.sidebar.width());
        }
    };

    /**
     * Toggles the visibility of a sidebar box.
     *
     * @param {string} id
     * @param {object} element
     * @returns {undefined}
     */
    this.toggleBox = function(id, element) {
        var parent = $(element).parents(".box-wrap");
        var collapsed = parent.hasClass("collapsed");
        if (collapsed) {
            parent.find(".box-content").slideDown(200, function() { parent.removeClass("collapsed"); });
        } else {
            parent.find(".box-content").slideUp(200, function() { parent.addClass("collapsed"); });
        }
        rcmail.set_cookie("xsidebar-box-" + id, collapsed ? 0 : 1);
    };
};
