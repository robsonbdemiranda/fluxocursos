(function () {
    'use strict';

    var modal = document.getElementById('download-modal');
    var inputMaterial = document.getElementById('download-material');
    var inputNome = document.getElementById('download-nome');
    var status = document.getElementById('download-status');
    var form = document.getElementById('download-form');

    if (!modal || !form) {
        return;
    }

    function openModal(material) {
        inputMaterial.value = material;
        status.textContent = '';
        form.reset();
        inputMaterial.value = material;
        form.querySelector('button[type="submit"]').disabled = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { inputNome.focus(); }, 50);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.bt-download').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.material || '');
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
            if (data.downloadUrl) {
                var downloadLink = document.createElement('a');
                downloadLink.href = data.downloadUrl;
                downloadLink.download = data.downloadName || '';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                downloadLink.remove();
            }
        } catch (error) {
            status.textContent = error.message;
            submitButton.disabled = false;
        }
    });
}());
