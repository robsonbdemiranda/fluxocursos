(function () {
    "use strict";

    var settings = window.fluxoTracking || {};
    var CONSENT_KEY = "fluxo:consent";
    var UTM_KEY = "fluxo:utm";

    function readStored(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function writeStored(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // ignore quota or privacy mode errors
        }
    }

    function clearStored(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (error) {
            // ignore
        }
    }

    function pushConsent(preferences) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: "fluxo_consent_update",
            consent_analytics: !!preferences.analytics,
            consent_marketing: !!preferences.marketing
        });
    }

    function loadScript(id, source) {
        if (document.getElementById(id)) return;
        var script = document.createElement("script");
        script.id = id;
        script.async = true;
        script.src = source;
        document.head.appendChild(script);
    }

    function activateGtm() {
        if (!settings.gtmId) return;
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
        loadScript(
            "gtm-script",
            "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(settings.gtmId)
        );
    }

    function activateHotjar() {
        if (!settings.hotjarId) return;
        window.hj = window.hj || function () {
            (window.hj.q = window.hj.q || []).push(arguments);
        };
        window._hjSettings = { hjid: settings.hotjarId, hjsv: 6 };
        loadScript(
            "hotjar-script",
            "https://static.hotjar.com/c/hotjar-" + settings.hotjarId + ".js?sv=6"
        );
    }

    function activateMautic() {
        if (!settings.mauticUrl) return;
        window.fluxoMautic = window.fluxoMautic || [];
        if (!window.fluxoMautic.length) {
            window.fluxoMautic.push(["init", { "pageUrl": window.location.href }]);
            loadScript("mautic-script", settings.mauticUrl);
        }
    }

    window.activateAnalytics = function () {
        activateGtm();
        activateHotjar();
    };

    window.activateMarketing = function () {
        activateMautic();
    };

    function readConsent() {
        var raw = readStored(CONSENT_KEY);
        if (!raw) return null;
        try {
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === "object" && parsed.version === 1) {
                return parsed;
            }
        } catch (error) {
            // ignore parse errors
        }
        clearStored(CONSENT_KEY);
        return null;
    }

    function writeConsent(consent) {
        var payload = {
            version: 1,
            analytics: !!consent.analytics,
            marketing: !!consent.marketing,
            updatedAt: new Date().toISOString()
        };
        writeStored(CONSENT_KEY, JSON.stringify(payload));
        pushConsent(payload);
        return payload;
    }

    function applyConsent(consent) {
        if (consent.analytics) window.activateAnalytics();
        if (consent.marketing) window.activateMarketing();
    }

    window.fluxoConsent = {
        read: readConsent,
        save: function (preferences) {
            var payload = writeConsent(preferences);
            applyConsent(payload);
            return payload;
        },
        clear: function () {
            clearStored(CONSENT_KEY);
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: "fluxo_consent_clear",
                consent_analytics: false,
                consent_marketing: false
            });
        }
    };

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

    function cacheUtms(utm) {
        if (!utm || !Object.keys(utm).length) return;
        try {
            window.sessionStorage.setItem(UTM_KEY, JSON.stringify(utm));
        } catch (error) {
            // ignore
        }
    }

    window.fluxoUtm = (function () {
        try {
            var stored = window.sessionStorage.getItem(UTM_KEY);
            if (stored) {
                return JSON.parse(stored) || {};
            }
        } catch (error) {
            // ignore
        }
        var utm = readUtmParams();
        cacheUtms(utm);
        return utm;
    })();

    window.fluxoFormContext = {
        consent: function () {
            return readConsent() || { analytics: false, marketing: false };
        },
        utm: function () {
            return window.fluxoUtm || {};
        }
    };

    var existingConsent = readConsent();
    if (existingConsent) {
        applyConsent(existingConsent);
    }
}());
