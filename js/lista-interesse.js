(function () {
    'use strict';

    var modal = document.getElementById('lista-interesse-modal');
    var form = document.getElementById('lista-interesse-form');
    if (!modal || !form) return;

    var inputCurso = document.getElementById('lista-interesse-curso');
    var inputNome = document.getElementById('lista-interesse-nome');
    var status = document.getElementById('lista-interesse-status');

    function syncUtmFields() {
        var context = window.fluxoFormContext && typeof window.fluxoFormContext.utm === 'function'
            ? window.fluxoFormContext.utm()
            : (window.fluxoUtm || {});

        Object.keys(context || {}).forEach(function (key) {
            if (key.indexOf('utm_') !== 0) return;
            var field = form.querySelector('input[name="' + key + '"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = key;
                form.appendChild(field);
            }
            field.value = String(context[key]);
        });
    }

    function openModal(curso) {
        form.reset();
        inputCurso.value = curso;
        status.textContent = '';
        syncUtmFields();
        form.querySelector('button[type="submit"]').disabled = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { inputNome.focus(); }, 50);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.bt-list-interesse').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.curso || '');
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        status.textContent = 'Enviando...';
        syncUtmFields();

        try {
            var response = await fetch('lista_interesse.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            });
            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível registrar seu interesse.');
            }
            form.reset();
            inputCurso.value = '';
            status.textContent = data.message || 'Inscrição registrada.';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            submitButton.disabled = false;
        }
    });
}());
