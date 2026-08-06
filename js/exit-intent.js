(function () {
    'use strict';

    var STORAGE_KEY = 'fluxo:exit-intent-shown';
    var SHOW_DELAY_MS = 1500;
    var TRIGGER_OFFSET_PX = 60;

    function isEligible() {
        try {
            if (window.sessionStorage.getItem(STORAGE_KEY) === '1') {
                return false;
            }
        } catch (error) {
            return true;
        }
        return window.matchMedia('(min-width: 768px)').matches;
    }

    function markShown() {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, '1');
        } catch (error) {
            // ignore
        }
    }

    function attach() {
        if (!isEligible()) {
            return;
        }

        var armed = false;
        var timeoutId = setTimeout(function () { armed = true; }, SHOW_DELAY_MS);

        document.addEventListener('mouseout', function (event) {
            if (!armed) {
                return;
            }
            if (event.relatedTarget || event.toElement) {
                return;
            }
            if (event.clientY > TRIGGER_OFFSET_PX) {
                return;
            }
            armed = false;
            markShown();
            window.fluxoExitIntent && window.fluxoExitIntent();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }
}());