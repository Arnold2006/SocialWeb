/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Free to use, copy, modify, fork, and distribute.
 *
 * NOT allowed:
 * - Selling this software
 * - Redistributing it for profit
 *
 * Provided "AS IS" without warranty.
 */
/**
 * video.js — JS for the video hub (video.php) and video playback (video_play.php).
 */

'use strict';

(function () {

    // ── Video upload panel toggle (video.php) ─────────────────────────────────
    var uploadBtn   = document.getElementById('video-upload-toggle');
    var uploadPanel = document.getElementById('video-upload-panel');
    if (uploadBtn && uploadPanel) {
        uploadBtn.addEventListener('click', function () {
            var hidden = uploadPanel.classList.toggle('hidden');
            uploadBtn.textContent = hidden ? '\u25b2 Upload Video' : '\u25bc Upload Video';
        });

        var form    = document.getElementById('video-upload-form');
        var spinner = document.getElementById('video-upload-progress');
        var upBtn   = document.getElementById('video-upload-btn');
        if (form) {
            form.addEventListener('submit', function () {
                if (spinner) spinner.style.display = 'inline';
                if (upBtn)   upBtn.disabled = true;
            });
        }
    }

    // ── Edit description toggle (video_play.php) ──────────────────────────────
    var descBtn  = document.getElementById('video-edit-desc-toggle');
    var descForm = document.getElementById('video-edit-desc-form');
    if (descBtn && descForm) {
        descBtn.addEventListener('click', function () {
            var hidden = descForm.classList.toggle('hidden');
            descBtn.textContent = hidden ? 'Edit Description' : 'Cancel';
        });
    }

}());
