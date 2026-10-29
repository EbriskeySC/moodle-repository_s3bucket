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
 * URL-driven page navigation for the standard embedded S3 PDF resource.
 *
 * @module     repository_s3bucket/pdf_navigation
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Accept only positive, precisely represented whole page numbers.
 *
 * @param {String|null} value Candidate page number
 * @returns {Number|null} Page number, or null
 */
const parsePage = value => {
    if (!value || !/^[1-9][0-9]*$/.test(value)) {
        return null;
    }
    const page = Number(value);
    return Number.isSafeInteger(page) ? page : null;
};

/**
 * Follow page links when Moodle has rendered its embedded PDF frame.
 */
export const init = () => {
    const frame = document.querySelector('.resourcepdf iframe#resourceobject, .resourcegeneral iframe#resourceobject');
    if (!frame || frame.dataset.s3bucketNavigation) {
        return;
    }
    frame.dataset.s3bucketNavigation = '1';

    const fileUrl = new URL(frame.getAttribute('src'), window.location.href);
    let currentPage = null;
    const readLocation = () => {
        const url = new URL(window.location.href);
        const hash = new URLSearchParams(url.hash.substring(1));
        return parsePage(hash.get('page')) ?? parsePage(url.searchParams.get('pdfpage'));
    };
    const showPage = page => {
        if (currentPage !== page) {
            const target = new URL(fileUrl);
            target.hash = 'page=' + page;
            // Reattach the same element to avoid an extra iframe history entry while retaining Moodle's resize handler.
            const parent = frame.parentNode;
            const next = frame.nextSibling;
            frame.remove();
            frame.src = target.href;
            parent.insertBefore(frame, next);
            currentPage = page;
        }
    };
    const followLocation = () => {
        showPage(readLocation() ?? 1);
    };
    window.addEventListener('hashchange', followLocation);
    window.addEventListener('popstate', followLocation);

    const requestedPage = readLocation();
    if (requestedPage !== null) {
        showPage(requestedPage);
    }
};
