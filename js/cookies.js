(function () {
    "use strict";

    var container = document.querySelector(".cookies-container");
    if (!container) return;

    function readConsent() {
        if (window.fluxoConsent && typeof window.fluxoConsent.read === "function") {
            return window.fluxoConsent.read();
        }
        return null;
    }

    function writeConsent(preferences) {
        if (window.fluxoConsent && typeof window.fluxoConsent.save === "function") {
            return window.fluxoConsent.save(preferences);
        }
        return null;
    }

    function collectPreferences() {
        var nodes = container.querySelectorAll("[data-function]");
        var pref = { analytics: false, marketing: false };
        nodes.forEach(function (node) {
            if (!node.checked) return;
            var key = node.getAttribute("data-function");
            if (key === "analytics") pref.analytics = true;
            if (key === "marketing") pref.marketing = true;
        });
        return pref;
    }

    function bindSave() {
        var save = container.querySelector(".cookies-save");
        if (!save) return;
        save.addEventListener("click", function () {
            var preferences = collectPreferences();
            writeConsent(preferences);
            container.style.display = "none";
            container.setAttribute("aria-hidden", "true");
        });
    }

    function bindReject() {
        var reject = container.querySelector(".cookies-reject");
        if (!reject) return;
        reject.addEventListener("click", function () {
            writeConsent({ analytics: false, marketing: false });
            container.style.display = "none";
            container.setAttribute("aria-hidden", "true");
        });
    }

    var existing = readConsent();
    if (existing) {
        container.style.display = "none";
        container.setAttribute("aria-hidden", "true");
        return;
    }

    bindSave();
    bindReject();
}());
