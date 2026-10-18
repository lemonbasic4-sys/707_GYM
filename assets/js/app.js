/**
 * 707 GYM – Main Application JavaScript
 * Vanilla JS: sidebar, flash, modals, API helpers, form validation, data table.
 */

/* ── Sidebar Toggle ──────────────────────────────────────── */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
        sidebar.classList.toggle('mobile-open');
        overlay?.classList.toggle('visible');
    } else {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
    }
}

/** Restore sidebar state from localStorage on desktop. */
function restoreSidebar() {
    if (window.innerWidth <= 768) return;
    const sidebar = document.getElementById('sidebar');
    if (sidebar && localStorage.getItem('sidebarCollapsed') === '1') {
        sidebar.classList.add('collapsed');
    }
}

/* ── Flash Message Auto-Dismiss ─────────────────────────── */
function initFlashDismiss() {
    const flash = document.querySelector('.flash-message');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.4s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 4000);
    }
}

/* ── Confirm Dialog Wrapper ──────────────────────────────── */
/**
 * Show a native confirm dialog, call callback only if user confirms.
 * @param {string}   message
 * @param {Function} callback
 */
function confirmAction(message, callback) {
    if (window.confirm(message)) {
        callback();
    }
}

/* ── Generic Fetch Wrapper ───────────────────────────────── */
/**
 * Perform a fetch request and return parsed JSON.
 * @param {string} url
 * @param {string} method   GET | POST | PUT | DELETE
 * @param {Object} data     Request body (for POST/PUT)
 * @returns {Promise<Object>}
 */
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    };

    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    return response.json();
}

/* ── Form Validation Helpers ─────────────────────────────── */
/**
 * Mark a field as invalid and show an error message.
 * @param {HTMLElement} field
 * @param {string}      message
 */
function setFieldError(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    let errEl = field.parentElement.querySelector('.form-error');
    if (!errEl) {
        errEl = document.createElement('span');
        errEl.className = 'form-error';
        field.parentElement.appendChild(errEl);
    }
    errEl.textContent = message;
}

/**
 * Mark a field as valid and clear any error message.
 * @param {HTMLElement} field
 */
function setFieldValid(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    const errEl = field.parentElement.querySelector('.form-error');
    if (errEl) errEl.textContent = '';
}

/**
 * Validate a required field is non-empty.
 * @param {HTMLElement} field
 * @param {string}      label  Used in error message
 * @returns {boolean}
 */
function validateRequired(field, label = 'This field') {
    if (!field.value.trim()) {
        setFieldError(field, `${label} is required.`);
        return false;
    }
    setFieldValid(field);
    return true;
}

/**
 * Validate an email field.
 * @param {HTMLElement} field
 * @returns {boolean}
 */
function validateEmail(field) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(field.value.trim())) {
        setFieldError(field, 'Please enter a valid email address.');
        return false;
    }
    setFieldValid(field);
    return true;
}

/* ── Active Sidebar Link Detection ──────────────────────── */
function highlightActiveSidebarLink() {
    const path = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href') || '';
        // Strip query strings for comparison
        const linkPath = href.split('?')[0];
        if (path.endsWith(linkPath) || (linkPath !== '/' && path.includes(linkPath))) {
            link.classList.add('active');
        }
    });
}

/* ── Modal Helpers ───────────────────────────────────────── */
function openModal(modalId) {
    const overlay = document.getElementById(modalId);
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const overlay = document.getElementById(modalId);
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

// Close modal when clicking the overlay background
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

/* ── Client-Side Data Table ──────────────────────────────── */
/**
 * Add client-side search/filter on an existing <table>.
 * Searches all visible text in tbody rows.
 *
 * @param {string} tableId   ID of the <table> element
 * @param {string} inputId   ID of the search <input> (optional; auto-created if not provided)
 */
function initDataTable(tableId, inputId = null) {
    const table = document.getElementById(tableId);
    if (!table) return;

    let searchInput = inputId ? document.getElementById(inputId) : null;

    // Auto-create search input if not provided
    if (!searchInput) {
        searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.placeholder = 'Search table...';
        searchInput.className = 'dt-search';
        searchInput.style.marginBottom = '12px';
        table.parentElement.insertBefore(searchInput, table);
    }

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        Array.from(tbody.rows).forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = (!query || text.includes(query)) ? '' : 'none';
        });
    });

    // Sort by column header click
    const headers = table.querySelectorAll('thead th[data-sort]');
    headers.forEach((th, colIndex) => {
        let asc = true;
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            const rows = Array.from(tbody.rows);
            rows.sort((a, b) => {
                const aVal = a.cells[colIndex]?.textContent.trim().toLowerCase() ?? '';
                const bVal = b.cells[colIndex]?.textContent.trim().toLowerCase() ?? '';
                return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });
            asc = !asc;
            rows.forEach(r => tbody.appendChild(r));
            headers.forEach(h => h.removeAttribute('data-sort-active'));
            th.setAttribute('data-sort-active', asc ? 'desc' : 'asc');
        });
    });
}

/* ── Typeahead / Member Search ───────────────────────────── */
/**
 * Wire up a member search typeahead on an input field.
 * @param {string} inputId        Input element ID
 * @param {string} hiddenId       Hidden input to store selected member record ID
 * @param {string} apiUrl         URL for GET ?action=search&q=
 */
function initMemberTypeahead(inputId, hiddenId, apiUrl) {
    const input  = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    if (!input || !hidden) return;

    let dropdown = document.createElement('ul');
    dropdown.className = 'typeahead-dropdown';
    dropdown.style.cssText = `
        position:absolute; top:100%; left:0; right:0; z-index:100;
        background:var(--surface); border:1px solid var(--border);
        border-radius:var(--radius-sm); list-style:none;
        max-height:220px; overflow-y:auto; display:none; margin:0; padding:4px 0;
    `;
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(dropdown);

    let debounceTimer;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }

        debounceTimer = setTimeout(async () => {
            try {
                const data = await apiRequest(`${apiUrl}?action=search&q=${encodeURIComponent(q)}`);
                dropdown.innerHTML = '';
                if (data.success && data.data.length) {
                    data.data.forEach(member => {
                        const li = document.createElement('li');
                        li.textContent = `${member.first_name} ${member.last_name}${member.phone ? ` - ${member.phone}` : ''}`;
                        li.style.cssText = 'padding:9px 14px; cursor:pointer; font-size:13px; color:var(--text);';
                        li.addEventListener('mouseenter', () => li.style.background = 'rgba(255,255,255,0.06)');
                        li.addEventListener('mouseleave', () => li.style.background = '');
                        li.addEventListener('click', () => {
                            input.value  = `${member.first_name} ${member.last_name}`;
                            hidden.value = member.id;
                            dropdown.style.display = 'none';
                        });
                        dropdown.appendChild(li);
                    });
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            } catch (_) {
                dropdown.style.display = 'none';
            }
        }, 280);
    });

    document.addEventListener('click', e => {
        if (!input.parentElement.contains(e.target)) dropdown.style.display = 'none';
    });
}

/* ── DOMContentLoaded Init ───────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    restoreSidebar();
    initFlashDismiss();
    highlightActiveSidebarLink();
});
