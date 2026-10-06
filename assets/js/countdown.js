/* Diva Junction — dot-matrix "Next deals in" countdown. */
(function () {
    'use strict';

    // 8 × 12 dot glyphs, styled after the station display in the artwork.
    var GLYPHS = {
        '0': ['.######.', '########', '##....##', '##...###', '##..####', '##.##.##', '####..##', '###...##', '##....##', '##....##', '########', '.######.'],
        '1': ['...##...', '..###...', '.####...', '##.##...', '...##...', '...##...', '...##...', '...##...', '...##...', '...##...', '########', '########'],
        '2': ['.######.', '########', '##....##', '......##', '......##', '.#######', '#######.', '##......', '##......', '##......', '########', '########'],
        '3': ['.######.', '########', '##....##', '......##', '......##', '..######', '..######', '......##', '......##', '##....##', '########', '.######.'],
        '4': ['##....##', '##....##', '##....##', '##....##', '##....##', '########', '########', '......##', '......##', '......##', '......##', '......##'],
        '5': ['########', '########', '##......', '##......', '#######.', '########', '......##', '......##', '......##', '##....##', '########', '.######.'],
        '6': ['.######.', '########', '##....##', '##......', '##......', '#######.', '########', '##....##', '##....##', '##....##', '########', '.######.'],
        '7': ['########', '########', '......##', '.....##.', '....##..', '...##...', '..##....', '..##....', '..##....', '..##....', '..##....', '..##....'],
        '8': ['.######.', '########', '##....##', '##....##', '##....##', '.######.', '########', '##....##', '##....##', '##....##', '########', '.######.'],
        '9': ['.######.', '########', '##....##', '##....##', '##....##', '########', '.#######', '......##', '......##', '##....##', '########', '.######.'],
        ':': ['..', '..', '..', '##', '##', '..', '..', '##', '##', '..', '..', '..']
    };
    var DOT = 0.8;   // dot size relative to the 1-unit grid pitch
    var GAP = 1.4;   // space between characters, in grid units
    var NS = 'http://www.w3.org/2000/svg';

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function render(svg, text, colonOn) {
        var frag = document.createDocumentFragment();
        var x = 0;
        for (var i = 0; i < text.length; i++) {
            var rows = GLYPHS[text[i]];
            var lit = text[i] !== ':' || colonOn;
            for (var r = 0; r < rows.length; r++) {
                for (var c = 0; c < rows[r].length; c++) {
                    if (rows[r][c] !== '#') continue;
                    var rect = document.createElementNS(NS, 'rect');
                    rect.setAttribute('x', (x + c + (1 - DOT) / 2).toFixed(2));
                    rect.setAttribute('y', (r + (1 - DOT) / 2).toFixed(2));
                    rect.setAttribute('width', DOT);
                    rect.setAttribute('height', DOT);
                    rect.setAttribute('fill', lit ? 'url(#dm-lit)' : 'transparent');
                    frag.appendChild(rect);
                }
            }
            x += rows[0].length + (i < text.length - 1 ? GAP : 0);
        }
        svg.setAttribute('viewBox', '0 0 ' + x.toFixed(2) + ' 12');
        while (svg.lastChild && svg.lastChild.nodeName !== 'defs') svg.removeChild(svg.lastChild);
        svg.appendChild(frag);
    }

    function init(board) {
        var svg = board.querySelector('.dot-matrix');
        var target = parseInt(board.getAttribute('data-target'), 10) || Date.now();
        var repeat = parseFloat(board.getAttribute('data-repeat')) || 0;

        svg.innerHTML =
            '<defs><linearGradient id="dm-lit" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0" stop-color="#ffec3d"/><stop offset=".7" stop-color="#ffe70d"/>' +
            '<stop offset="1" stop-color="#e9cc0a"/></linearGradient></defs>';

        var last = '';
        function tick() {
            var now = Date.now();
            if (repeat > 0) {
                while (target <= now) target += repeat;
            }
            var left = Math.max(0, Math.floor((target - now) / 1000));
            var h = Math.floor(left / 3600);
            var m = Math.floor((left % 3600) / 60);
            var s = left % 60;
            // HH:MM while more than an hour remains, then MM:SS.
            var text = h > 0 ? pad(Math.min(h, 99)) + ':' + pad(m) : pad(m) + ':' + pad(s);
            var colonOn = left === 0 || now % 1000 < 600;
            var key = text + colonOn;
            if (key !== last) {
                render(svg, text, colonOn);
                board.setAttribute('aria-label', 'Next deals in ' + (h > 0 ? h + ' hours ' + m + ' minutes' : m + ' minutes ' + s + ' seconds'));
                last = key;
            }
        }
        tick();
        setInterval(tick, 200);
    }

    var boards = document.querySelectorAll('.countdown-board');
    for (var i = 0; i < boards.length; i++) init(boards[i]);
})();
