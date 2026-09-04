(function () {
    "use strict";

    function bootstrap() {
        var placeholder = document.getElementById("fluxo-consent-placeholder");
        if (!placeholder) return;

        fetch("md/partials/consent-snippet.html", { credentials: "same-origin", cache: "no-cache" })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("HTTP " + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                placeholder.outerHTML = html;
            })
            .catch(function () {
                placeholder.outerHTML = "";
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bootstrap);
    } else {
        bootstrap();
    }
}());
