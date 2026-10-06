/* Diva Junction admin — small progressive enhancements. */
(function () {
    'use strict';

    // Two-step delete: first click arms the button, second click submits.
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-confirm]');
        if (!btn) return;
        if (btn.classList.contains('is-confirming')) return; // second click → submit
        event.preventDefault();
        var original = btn.innerHTML;
        btn.classList.add('is-confirming');
        btn.textContent = btn.getAttribute('data-confirm');
        setTimeout(function () {
            btn.classList.remove('is-confirming');
            btn.innerHTML = original;
        }, 3500);
    });

    // Live preview for image inputs.
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input.matches('[data-preview-input]') || !input.files || !input.files[0]) return;
        var box = input.closest('.field-image').querySelector('[data-preview]');
        var img = box.querySelector('img') || document.createElement('img');
        img.src = URL.createObjectURL(input.files[0]);
        box.innerHTML = '';
        box.appendChild(img);
    });

    // Brand form: mirror the station name into the sign preview.
    var nameInput = document.querySelector('input[name="name"]');
    var mirror = document.querySelector('[data-mirror="name"]');
    if (nameInput && mirror) {
        nameInput.addEventListener('input', function () {
            mirror.textContent = nameInput.value || 'Brand';
        });
    }

    // Close the mobile menu after navigating.
    var toggle = document.getElementById('nav-toggle');
    if (toggle) {
        document.querySelectorAll('.sidebar a').forEach(function (a) {
            a.addEventListener('click', function () { toggle.checked = false; });
        });
    }
})();
