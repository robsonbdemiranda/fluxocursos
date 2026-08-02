function cookies(functions) {
    const container = document.querySelector('.cookies-container');
    const save = document.querySelector('.cookies-save');
    if (!container || !save) return null;

    let localPref = null;
    try {
        localPref = JSON.parse(window.localStorage.getItem('cookies-pref'));
    } catch (error) {
        window.localStorage.removeItem('cookies-pref');
    }
    if (localPref) activateFunctions(localPref);

    function getFormPref() {
        return [...document.querySelectorAll('[data-function]')]
            .filter((el) => el.checked)
            .map((el) => el.getAttribute('data-function'));
    }

    function activateFunctions(pref) {
        pref.forEach((f) => {
            if (typeof functions[f] === 'function') functions[f]();
        });
        container.style.display = 'none';
        window.localStorage.setItem('cookies-pref', JSON.stringify(pref));
    }

    function handleSave() {
        const pref = getFormPref();
        activateFunctions(pref);
    }

    save.addEventListener('click', handleSave);
}

function marketing() {
    // Reservado para a ativação de scripts de marketing após o consentimento.
}

function analytics() {
    if (typeof window.activateAnalytics === 'function') window.activateAnalytics();
}

cookies({
    marketing,
    analytics,
});
