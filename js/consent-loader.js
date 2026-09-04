(function () {
    "use strict";

    function loadStylesheet() {
        if (document.getElementById("fluxo-consent-styles")) return;

        var stylesheet = document.createElement("link");
        stylesheet.id = "fluxo-consent-styles";
        stylesheet.rel = "stylesheet";
        stylesheet.href = "/css/cookies.css";
        document.head.appendChild(stylesheet);
    }

    function loadScript(id, source) {
        return new Promise(function (resolve, reject) {
            var existing = document.getElementById(id);
            if (existing) {
                resolve();
                return;
            }

            var script = document.createElement("script");
            script.id = id;
            script.src = source;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function bootstrap() {
        var placeholder = document.getElementById("fluxo-consent-placeholder");
        if (!placeholder) return;

        window.fluxoTracking = window.fluxoTracking || {
            gtmId: "GTM-KW5DHT9",
            hotjarId: 2658836
        };
        loadStylesheet();

        var bannerReady = fetch("/md/partials/consent-snippet.html", {
            credentials: "same-origin",
            cache: "no-cache"
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("HTTP " + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                placeholder.outerHTML = html;
                return true;
            })
            .catch(function () {
                placeholder.outerHTML = "";
                return false;
            });

        var trackingReady = loadScript("fluxo-tracking-script", "/js/tracking.js")
            .then(function () { return true; })
            .catch(function () { return false; });

        Promise.all([bannerReady, trackingReady]).then(function (results) {
            if (!results[0] || !results[1]) return;
            loadScript("fluxo-cookies-script", "/js/cookies.js").catch(function () {
                // Keep external trackers disabled when the consent UI cannot initialize.
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bootstrap);
    } else {
        bootstrap();
    }
}());
