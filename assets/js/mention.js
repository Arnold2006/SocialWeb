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
 * mention.js — @username autocomplete for all comment inputs
 *
 * Attaches a mention-autocomplete dropdown to any text input or textarea
 * that has the class "mention-input". The dropdown is shared (one global
 * element) and repositioned on demand.
 *
 * Usage:
 *   1. The PHP side renders <input class="mention-input" …> on comment forms.
 *   2. When the user types "@" the dropdown appears and filters as they type.
 *   3. Clicking or pressing Enter/Tab selects a user and completes the mention.
 */

'use strict';

(function () {

    // ── Singleton dropdown ────────────────────────────────────────────────────

    const dropdown = document.createElement('div');
    dropdown.id        = 'mention-dropdown';
    dropdown.className = 'mention-dropdown hidden';
    dropdown.setAttribute('role', 'listbox');
    dropdown.setAttribute('aria-label', 'Mention a user');
    document.body.appendChild(dropdown);

    // ── State ─────────────────────────────────────────────────────────────────

    let activeInput   = null;   // The <input> / <textarea> being edited
    let mentionStart  = -1;     // Index of the '@' character in the input value
    let activeIndex   = -1;     // Keyboard-highlighted item index (-1 = none)
    let fetchTimer    = null;   // Debounce timer
    let lastQuery     = '';     // Last query sent to the server
    let currentUsers  = [];     // Current suggestion list

    const baseUrl = document.querySelector('meta[name="site-url"]')?.content || '';

    // ── Helpers ───────────────────────────────────────────────────────────────

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
        dropdown.innerHTML = '';
        activeIndex   = -1;
        currentUsers  = [];
        mentionStart  = -1;
        activeInput   = null;
        lastQuery     = '';
        clearTimeout(fetchTimer);
    }

    function getCaretCoords(input) {
        // Approximate position of the caret using getBoundingClientRect.
        // For a simple <input type="text"> we can't get exact x/y of the caret,
        // so we position the dropdown below the input instead.
        const rect = input.getBoundingClientRect();
        return {
            x: rect.left + window.scrollX,
            y: rect.bottom + window.scrollY,
        };
    }

    function positionDropdown(input) {
        const coords = getCaretCoords(input);
        dropdown.style.left = coords.x + 'px';
        dropdown.style.top  = coords.y + 4 + 'px';
    }

    function renderDropdown(users) {
        currentUsers = users;
        activeIndex  = -1;
        dropdown.innerHTML = '';

        if (users.length === 0) {
            hideDropdown();
            return;
        }

        users.forEach(function (u, idx) {
            const item = document.createElement('div');
            item.className = 'mention-item';
            item.setAttribute('role', 'option');
            item.setAttribute('data-idx', String(idx));
            item.innerHTML =
                '<img src="' + escHtml(u.avatar) + '" class="mention-avatar" width="24" height="24" alt="">' +
                '<span class="mention-username">@' + escHtml(u.username) + '</span>';
            item.addEventListener('mousedown', function (e) {
                e.preventDefault(); // prevent blur before selection
                selectUser(u);
            });
            dropdown.appendChild(item);
        });

        dropdown.classList.remove('hidden');
        positionDropdown(activeInput);
    }

    function setActiveItem(idx) {
        const items = dropdown.querySelectorAll('.mention-item');
        items.forEach(function (el, i) {
            el.classList.toggle('mention-item--active', i === idx);
        });
        activeIndex = idx;
    }

    function selectUser(user) {
        if (!activeInput) return;
        const val    = activeInput.value;
        const before = val.slice(0, mentionStart);        // text before '@'
        const after  = val.slice(activeInput.selectionStart); // text after cursor

        activeInput.value = before + '@' + user.username + ' ' + after;

        // Move caret after the inserted mention
        const pos = before.length + user.username.length + 2; // '@' + name + ' '
        activeInput.setSelectionRange(pos, pos);
        activeInput.focus();

        const inputEl = activeInput; // save ref before hideDropdown() nulls activeInput
        hideDropdown();
        inputEl.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ── Fetch suggestions (debounced) ─────────────────────────────────────────

    function fetchSuggestions(query) {
        if (query === lastQuery) return;
        lastQuery = query;

        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(function () {
            fetch(baseUrl + '/modules/users/mention_search.php?q=' + encodeURIComponent(query), {
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok && activeInput) {
                    renderDropdown(data.users || []);
                }
            })
            .catch(function () {
                // silently fail — mention lookup is non-critical
            });
        }, 150);
    }

    // ── Determine current @mention token at cursor ────────────────────────────

    function getMentionQuery(input) {
        const val = input.value;
        const pos = input.selectionStart;

        // Walk backwards from the cursor looking for '@'
        let i = pos - 1;
        while (i >= 0) {
            const ch = val[i];
            if (ch === '@') {
                // Make sure there's no whitespace between '@' and cursor
                const token = val.slice(i + 1, pos);
                if (/^\S*$/.test(token)) {
                    return { query: token, start: i };
                }
                return null;
            }
            // Stop if we hit whitespace before finding '@'
            if (/\s/.test(ch)) {
                return null;
            }
            i--;
        }
        return null;
    }

    // ── Input event handler ───────────────────────────────────────────────────

    function onInput(e) {
        const input = e.target;
        const result = getMentionQuery(input);

        if (!result) {
            hideDropdown();
            return;
        }

        activeInput  = input;
        mentionStart = result.start;

        if (result.query.length === 0) {
            // '@' typed but no characters yet — fetch full list
            fetchSuggestions('');
        } else {
            fetchSuggestions(result.query);
        }
    }

    // ── Keyboard navigation ───────────────────────────────────────────────────

    function onKeydown(e) {
        if (dropdown.classList.contains('hidden')) return;

        const items = dropdown.querySelectorAll('.mention-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveItem(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveItem(Math.max(activeIndex - 1, 0));
        } else if ((e.key === 'Enter' || e.key === 'Tab') && activeIndex >= 0) {
            // Tab: complete the mention without submitting the form.
            // Enter: complete the mention AND let the default action (form submit) proceed.
            if (e.key === 'Tab') e.preventDefault();
            selectUser(currentUsers[activeIndex]);
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    }

    // ── Attach to a single input element ─────────────────────────────────────

    function attachMention(input) {
        if (input._mentionAttached) return;
        input._mentionAttached = true;
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);
        input.addEventListener('blur', function () {
            // Small delay lets mousedown-on-item fire before blur hides the list
            setTimeout(function () {
                if (!dropdown.contains(document.activeElement)) {
                    hideDropdown();
                }
            }, 150);
        });
    }

    // ── Observe the DOM for new mention inputs ────────────────────────────────

    function attachAll() {
        document.querySelectorAll('input.mention-input, textarea.mention-input').forEach(attachMention);
    }

    // Attach to inputs that already exist
    attachAll();

    // Re-attach whenever new nodes are added (e.g. dynamically injected comment forms)
    const observer = new MutationObserver(attachAll);
    observer.observe(document.body, { childList: true, subtree: true });

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== activeInput) {
            hideDropdown();
        }
    });

}());
