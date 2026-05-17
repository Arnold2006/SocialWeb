/**
 * snowfall.js — Vanilla JS Snowfall Effect
 *
 * Inspired by jquery.snow (Ivan Lazarevic, MIT licence).
 * Rewritten as pure vanilla JS for SocialWeb (no jQuery dependency).
 *
 * Configurable via window.snowfallOptions:
 *   minSize   {number}  Minimum flake font-size in px  (default: 10)
 *   maxSize   {number}  Maximum flake font-size in px  (default: 20)
 *   newOn     {number}  Interval between new flakes ms (default: 500)
 *   flakeColor {string} CSS colour of each flake        (default: '#FFFFFF')
 */
(function () {
    'use strict';

    var opts = Object.assign(
        { minSize: 10, maxSize: 20, newOn: 500, flakeColor: '#FFFFFF' },
        window.snowfallOptions || {}
    );

    var FLAKE_CHAR = '\u2744'; // ❄

    // Fall-speed multiplier: pixels-per-ms factor applied to the document height
    // so taller pages still produce a natural, linear descent time.
    var FALL_SPEED_MULTIPLIER = 10;

    // Adds up to this many extra milliseconds of randomised drift to each flake
    // so consecutive flakes don't all finish at exactly the same time.
    var DURATION_VARIATION_MS = 5000;

    function spawnFlake() {
        var docW = document.documentElement.scrollWidth;
        var docH = document.documentElement.scrollHeight;

        var size       = opts.minSize + Math.random() * (opts.maxSize - opts.minSize);
        var startLeft  = Math.random() * docW;
        var startOpacity = 0.5 + Math.random() * 0.5;
        var endLeft    = startLeft - 100 + Math.random() * 200;
        var duration   = docH * FALL_SPEED_MULTIPLIER + Math.random() * DURATION_VARIATION_MS;

        var flake = document.createElement('div');
        flake.textContent = FLAKE_CHAR;
        flake.setAttribute('aria-hidden', 'true');

        Object.assign(flake.style, {
            position:     'fixed',
            top:          '-50px',
            left:         startLeft + 'px',
            fontSize:     size + 'px',
            color:        opts.flakeColor,
            opacity:      String(startOpacity),
            pointerEvents: 'none',
            userSelect:   'none',
            zIndex:       '99999',
            transition:   'top ' + duration + 'ms linear, left ' + duration + 'ms linear, opacity ' + duration + 'ms linear',
            willChange:   'top, left, opacity'
        });

        document.body.appendChild(flake);

        // Trigger reflow so the transition fires
        /* jshint expr:true */
        flake.offsetHeight;

        flake.style.top     = (docH - 40) + 'px';
        flake.style.left    = endLeft + 'px';
        flake.style.opacity = '0.1';

        flake.addEventListener('transitionend', function () {
            if (flake.parentNode) {
                flake.parentNode.removeChild(flake);
            }
        }, { once: true });
    }

    function start() {
        var intervalId = setInterval(function () {
            // Pause spawning when the tab is not visible to save CPU.
            if (!document.hidden) {
                spawnFlake();
            }
        }, opts.newOn);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
