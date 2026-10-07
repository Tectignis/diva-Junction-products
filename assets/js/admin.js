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

    // Site content: switch tabs in place, mark tabs with unsaved edits.
    var settings = document.querySelector('[data-settings]');
    if (settings) {
        var nav = settings.querySelector('.settings-nav');
        var tabField = settings.querySelector('input[name="tab"]');
        var savebar = settings.querySelector('[data-savebar]');
        var status = savebar.querySelector('[data-save-status]');
        var idleText = status.textContent;
        var submitting = false;

        var linkFor = function (id) { return nav.querySelector('[data-tab="' + id + '"]'); };

        var centerTab = function (id, behavior) {
            var link = linkFor(id);
            if (nav.scrollWidth > nav.clientWidth) {
                nav.scrollTo({ left: link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2, behavior: behavior });
            }
        };

        var showTab = function (id) {
            settings.querySelectorAll('[data-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-panel') !== id;
            });
            nav.querySelectorAll('[data-tab]').forEach(function (a) {
                if (a.getAttribute('data-tab') === id) a.setAttribute('aria-current', 'true');
                else a.removeAttribute('aria-current');
            });
            tabField.value = id;
            history.replaceState(null, '', '?tab=' + encodeURIComponent(id));
            centerTab(id, 'smooth');
        };
        centerTab(tabField.value, 'instant');

        nav.addEventListener('click', function (event) {
            var link = event.target.closest('[data-tab]');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
            event.preventDefault();
            showTab(link.getAttribute('data-tab'));
            // scrolled past the top of the form → bring the new tab's start into view
            var top = settings.getBoundingClientRect().top + window.scrollY - 90;
            if (window.scrollY > top) window.scrollTo({ top: top, behavior: 'smooth' });
        });

        var changed = function (el) {
            if (!el.name || el.type === 'hidden' || el.type === 'submit') return false;
            if (el.type === 'file') return el.files.length > 0;
            if (el.type === 'checkbox' || el.type === 'radio') return el.checked !== el.defaultChecked;
            return el.value !== el.defaultValue;
        };

        var refreshDirty = function () {
            var names = [];
            settings.querySelectorAll('[data-panel]').forEach(function (panel) {
                var dirty = Array.prototype.some.call(panel.querySelectorAll('input, textarea, select'), changed);
                var id = panel.getAttribute('data-panel');
                linkFor(id).classList.toggle('is-dirty', dirty);
                if (dirty) names.push(linkFor(id).textContent.trim());
            });
            savebar.classList.toggle('is-dirty', names.length > 0);
            status.textContent = names.length ? 'Unsaved changes: ' + names.join(', ') : idleText;
            return names.length > 0;
        };

        settings.addEventListener('input', refreshDirty);
        settings.addEventListener('change', refreshDirty);
        settings.addEventListener('submit', function () { submitting = true; });
        window.addEventListener('beforeunload', function (event) {
            if (!submitting && refreshDirty()) event.preventDefault();
        });

        // A field the browser rejects on save may sit in a hidden tab: open that tab first.
        var revealing = false;
        settings.addEventListener('invalid', function (event) {
            if (revealing) return;
            revealing = true;
            setTimeout(function () { revealing = false; });
            var panel = event.target.closest('[data-panel]');
            if (panel && panel.hidden) showTab(panel.getAttribute('data-panel'));
        }, true);
    }

    // Close the mobile menu after navigating.
    var toggle = document.getElementById('nav-toggle');
    if (toggle) {
        document.querySelectorAll('.sidebar a').forEach(function (a) {
            a.addEventListener('click', function () { toggle.checked = false; });
        });
    }
})();
