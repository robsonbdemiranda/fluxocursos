/* Carrega ferramentas de medição somente depois do consentimento explícito. */
(function () {
    "use strict";

    var settings = window.fluxoTracking || {};

    function loadScript(id, source) {
        if (document.getElementById(id)) return;

        var script = document.createElement("script");
        script.id = id;
        script.async = true;
        script.src = source;
        document.head.appendChild(script);
    }

    window.activateAnalytics = function () {
        if (settings.gtmId) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
            loadScript("gtm-script", "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(settings.gtmId));
        }

        if (settings.hotjarId) {
            window.hj = window.hj || function () { (window.hj.q = window.hj.q || []).push(arguments); };
            window._hjSettings = { hjid: settings.hotjarId, hjsv: 6 };
            loadScript("hotjar-script", "https://static.hotjar.com/c/hotjar-" + settings.hotjarId + ".js?sv=6");
        }
    };

    try {
        var preferences = JSON.parse(window.localStorage.getItem("cookies-pref"));
        if (Array.isArray(preferences) && preferences.indexOf("analytics") !== -1) {
            window.activateAnalytics();
        }
    } catch (error) {
        window.localStorage.removeItem("cookies-pref");
    }

    window.fluxoUtm = readUtmParams();
    if (window.fluxoUtm && Object.keys(window.fluxoUtm).length) {
        try {
            window.sessionStorage.setItem("fluxo:utm", JSON.stringify(window.fluxoUtm));
        } catch (error) {
            // ignore
        }
    }

    function readUtmParams() {
        try {
            var search = new URLSearchParams(window.location.search);
            var keys = ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content"];
            var data = {};
            keys.forEach(function (key) {
                var value = search.get(key);
                if (value) data[key] = value;
            });
            return data;
        } catch (error) {
            return {};
        }
    }
}());
