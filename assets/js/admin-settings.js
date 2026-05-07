(function () {
    'use strict';

    // ── Navigation Menu Editor ────────────────────────────────────────────
    var tbody   = document.getElementById('nav-menu-rows');
    var form    = document.getElementById('nav-menu-form');
    var addBtn  = document.getElementById('nav-add-row');
    var hidden  = document.getElementById('nav-items-input');

    if (!tbody || !form || !addBtn || !hidden) { return; }

    function makeRow(label, url, newTab) {
        var tr = document.createElement('tr');
        tr.className = 'nav-menu-row';
        tr.innerHTML =
            '<td style="padding:.4rem .6rem;border-bottom:1px solid var(--color-border)">' +
                '<input type="text" class="form-control nav-item-label" value="" placeholder="Link label" maxlength="100" style="width:100%">' +
            '</td>' +
            '<td style="padding:.4rem .6rem;border-bottom:1px solid var(--color-border)">' +
                '<input type="url" class="form-control nav-item-url" value="" placeholder="https://example.com" maxlength="500" style="width:100%">' +
            '</td>' +
            '<td style="padding:.4rem .6rem;border-bottom:1px solid var(--color-border);text-align:center">' +
                '<input type="checkbox" class="nav-item-newtab" style="width:1.1rem;height:1.1rem;cursor:pointer;accent-color:var(--color-accent)">' +
            '</td>' +
            '<td style="padding:.4rem .6rem;border-bottom:1px solid var(--color-border);text-align:right">' +
                '<button type="button" class="btn btn-danger btn-sm nav-item-remove">Remove</button>' +
            '</td>';
        tr.querySelector('.nav-item-label').value = label || '';
        tr.querySelector('.nav-item-url').value = url || '';
        tr.querySelector('.nav-item-newtab').checked = !!newTab;
        return tr;
    }

    addBtn.addEventListener('click', function () {
        tbody.appendChild(makeRow('', '', true));
    });

    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('nav-item-remove')) {
            e.target.closest('tr').remove();
        }
    });

    form.addEventListener('submit', function () {
        var items = [];
        tbody.querySelectorAll('.nav-menu-row').forEach(function (row) {
            var label  = row.querySelector('.nav-item-label').value.trim();
            var url    = row.querySelector('.nav-item-url').value.trim();
            var newTab = row.querySelector('.nav-item-newtab').checked ? 1 : 0;
            if (label && url) {
                items.push({ label: label, url: url, new_tab: newTab });
            }
        });
        hidden.value = JSON.stringify(items);
    });
}());
