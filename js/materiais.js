(function () {
    'use strict';

    var modal = document.getElementById('download-modal');
    var inputMaterial = document.getElementById('download-material');
    var inputNome = document.getElementById('download-nome');
    var status = document.getElementById('download-status');
    var form = document.getElementById('download-form');
    var pendingFile = null;

    if (!modal || !form) {
        return;
    }

    function openModal(material, file) {
        inputMaterial.value = material;
        pendingFile = file;
        status.textContent = '';
        form.reset();
        inputMaterial.value = material;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { inputNome.focus(); }, 50);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        pendingFile = null;
    }

    document.querySelectorAll('.bt-download').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.material || '', button.dataset.file || '');
        });
    });

    document.querySelectorAll('.materiais-hero .bt-list-interesse').forEach(function () {});

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        var submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        status.textContent = 'Liberando download...';
        try {
            var response = await fetch('material_download.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            });
            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível liberar o download.');
            }
            status.textContent = data.message || 'Download liberado.';
            if (pendingFile) {
                window.location.href = pendingFile;
            }
        } catch (error) {
            status.textContent = error.message;
            submitButton.disabled = false;
        }
    });
}());