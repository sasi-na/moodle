// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Floating horizontal scrollbar for the grading submissions table (Jain customisation).
 *
 * The grading table can be wider than the viewport, but its native horizontal scrollbar
 * sits at the very bottom of the (tall) table, so faculty have to scroll past every
 * attempt to reach it. This adds a thin scrollbar pinned to the bottom of the viewport,
 * synced with the table, shown only while the table is on screen and its own scrollbar
 * is out of view.
 *
 * @module     mod_assign/sticky_hscroll
 * @copyright  2026 JAIN Online
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    scroller: '.gradingtable',
};

export const init = () => {
    const scroller = document.querySelector(SELECTORS.scroller);
    if (!scroller) {
        return;
    }

    // Build the floating scrollbar: a fixed-position track whose inner spacer mirrors
    // the table's full scroll width so its thumb maps 1:1 to the table's scroll range.
    const bar = document.createElement('div');
    bar.className = 'assign-floating-hscroll';
    const inner = document.createElement('div');
    inner.className = 'assign-floating-hscroll__inner';
    bar.appendChild(inner);
    document.body.appendChild(bar);

    // Keep the two scroll positions in sync without feedback loops.
    let active = null;
    bar.addEventListener('scroll', () => {
        if (active === 'scroller') {
            return;
        }
        active = 'bar';
        scroller.scrollLeft = bar.scrollLeft;
        window.requestAnimationFrame(() => {
            active = null;
        });
    }, {passive: true});
    scroller.addEventListener('scroll', () => {
        if (active === 'bar') {
            return;
        }
        active = 'scroller';
        bar.scrollLeft = scroller.scrollLeft;
        window.requestAnimationFrame(() => {
            active = null;
        });
    }, {passive: true});

    const update = () => {
        const rect = scroller.getBoundingClientRect();
        const viewportH = window.innerHeight;

        // Match the bar's track to the table's on-screen position and width...
        inner.style.width = scroller.scrollWidth + 'px';
        bar.style.left = Math.max(0, rect.left) + 'px';
        bar.style.width = Math.min(rect.width, window.innerWidth - Math.max(0, rect.left)) + 'px';
        bar.scrollLeft = scroller.scrollLeft;

        // ...and only show it when the table actually overflows, is in view, and its own
        // bottom scrollbar is below the fold (otherwise the native one is reachable).
        const overflowing = scroller.scrollWidth > scroller.clientWidth + 1;
        const inView = rect.bottom > 0 && rect.top < viewportH;
        const nativeBarVisible = rect.bottom <= viewportH;
        bar.classList.toggle('is-visible', overflowing && inView && !nativeBarVisible);
    };

    update();
    window.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update, {passive: true});
    if (window.ResizeObserver) {
        // Recompute when the table reflows (quick-grading toggles, column changes, fonts).
        new window.ResizeObserver(update).observe(scroller);
    }
};
