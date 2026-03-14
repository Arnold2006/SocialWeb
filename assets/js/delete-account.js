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
 * delete-account.js — Delete-account modal wizard for the profile settings page.
 *
 * Three-step flow:
 *   Step 1 – Warning / consequences list
 *   Step 2 – User must type "DELETE" (all caps)
 *   Step 3 – Password verification + final form submit
 */

'use strict';

(function () {
    var modal  = document.getElementById('delete-account-modal');
    var step1  = document.getElementById('delete-step-1');
    var step2  = document.getElementById('delete-step-2');
    var step3  = document.getElementById('delete-step-3');

    var openBtn = document.getElementById('open-delete-modal');

    // Modal may not be present on this page (non-own-profile view).
    if (!modal || !step1 || !step2 || !step3 || !openBtn) { return; }

    var isOpen = false;

    function showStep(s) {
        [step1, step2, step3].forEach(function (el) { el.style.display = 'none'; });
        s.style.display = 'block';
    }

    function openModal() {
        showStep(step1);
        document.getElementById('delete-confirm-text').value = '';
        document.getElementById('delete-confirm-error').style.display = 'none';
        document.getElementById('delete-password').value = '';
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        isOpen = true;
    }

    function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        isOpen = false;
    }

    openBtn.addEventListener('click', openModal);
    document.querySelector('.delete-modal-close').addEventListener('click', closeModal);
    document.getElementById('delete-cancel-1').addEventListener('click', closeModal);

    // Step 1 → Step 2
    document.getElementById('delete-next-1').addEventListener('click', function () {
        showStep(step2);
        document.getElementById('delete-confirm-text').focus();
    });

    // Step 2 → Step 1
    document.getElementById('delete-back-2').addEventListener('click', function () {
        showStep(step1);
    });

    // Step 2 → Step 3
    document.getElementById('delete-next-2').addEventListener('click', function () {
        var val = document.getElementById('delete-confirm-text').value.trim();
        if (val !== 'DELETE') {
            document.getElementById('delete-confirm-error').style.display = 'block';
            return;
        }
        document.getElementById('delete-confirm-error').style.display = 'none';
        document.getElementById('delete-confirm-hidden').value = val;
        showStep(step3);
        document.getElementById('delete-password').focus();
    });

    // Step 3 → Step 2
    document.getElementById('delete-back-3').addEventListener('click', function () {
        showStep(step2);
    });

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) { closeModal(); }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) { closeModal(); }
    });
}());
