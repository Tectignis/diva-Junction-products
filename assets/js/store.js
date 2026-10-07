/* Diva Junction — deals page: previous / next buttons for carousel sections. */
(function () {
    'use strict';

    document.querySelectorAll('[data-carousel]').forEach(function (section) {
        var track = section.querySelector('.tiles');
        var buttons = section.querySelectorAll('.carousel-btn');
        if (!track || !buttons.length) return;

        function update() {
            var max = track.scrollWidth - track.clientWidth - 2;
            buttons[0].disabled = track.scrollLeft <= 2;
            buttons[1].disabled = track.scrollLeft >= max;
            section.classList.toggle('is-static', max <= 0);
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tile = track.querySelector('.tile');
                var step = tile ? tile.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 0) : track.clientWidth;
                var visible = Math.max(1, Math.floor(track.clientWidth / step));
                track.scrollBy({ left: Number(btn.getAttribute('data-dir')) * step * visible, behavior: 'smooth' });
            });
        });

        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    });
})();
