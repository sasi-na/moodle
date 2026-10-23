// AMD module for Edwiser Reports Pro: Dashboard UI (Dropdowns, Insights)
// Implements robust dropdown logic, accessibility, and Insights AJAX logic
// Exports: init(rootElement), addInsight, setAjaxMock
// For use in main.js and in tests

define(['jquery'], function($) {
    let ajaxImpl = defaultAjaxImpl;

    function defaultAjaxImpl(url, data) {
        // Real AJAX call (to be replaced/mocked in tests)
        return $.ajax({ url: url, method: 'POST', data: data });
    }

    // Set a mock AJAX implementation (for tests)
    function setAjaxMock(mockFn) {
        ajaxImpl = mockFn;
    }

    // Dropdown logic: event delegation, close others, global click, ARIA, keyboard
    function initDropdowns(root) {
        const dropdowns = root.querySelectorAll('.custom-dropdown');
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (!toggle) return;
            // Set ARIA attributes
            toggle.setAttribute('aria-haspopup', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            // Click handler
            toggle.onclick = function(e) {
                e.stopPropagation();
                closeAllDropdowns(root, dropdown);
                const isOpen = dropdown.classList.toggle('open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };
            // Keyboard accessibility
            toggle.onkeydown = function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle.click();
                }
            };
        });
        // Global click closes all
        root.addEventListener('click', function(e) {
            if (!e.target.classList.contains('dropdown-toggle')) {
                closeAllDropdowns(root);
            }
        });
    }

    function closeAllDropdowns(root, except) {
        const dropdowns = root.querySelectorAll('.custom-dropdown');
        dropdowns.forEach(d => {
            if (d !== except) {
                d.classList.remove('open');
                const toggle = d.querySelector('.dropdown-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Insights add logic (mockable for tests)
    function addInsight(options = {}) {
        // Example: trigger AJAX to add an insight block
        const url = '/local/edwiserreports/ajax.php';
        const data = Object.assign({ action: 'add_insight', sesskey: window.M && window.M.cfg ? window.M.cfg.sesskey : '' }, options);
        return ajaxImpl(url, data)
            .then(response => {
                // Update UI as needed (omitted for brevity)
                return response;
            })
            .catch(err => {
                // Handle error (show notification, etc.)
                throw err;
            });
    }

    // Main init function: call after DOM ready or AJAX content load
    function init(rootElement) {
        if (!rootElement) rootElement = document;
        initDropdowns(rootElement);
        // Add Insights button logic
        const addBtn = rootElement.getElementById ? rootElement.getElementById('add-insight') : null;
        if (addBtn) {
            addBtn.onclick = function() {
                addInsight().catch(() => {
                    // Error handling (could show a message)
                });
            };
        }
    }

    return {
        init: init,
        addInsight: addInsight,
        setAjaxMock: setAjaxMock
    };
}); 