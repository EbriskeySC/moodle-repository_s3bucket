<?php
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

namespace repository_s3bucket;

use core\hook\output\before_standard_head_html_generation;

/**
 * Page navigation for S3 PDFs embedded in the standard File activity.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_navigation {
    /**
     * Queue page-link handling after the File activity has performed its normal access checks.
     *
     * @param before_standard_head_html_generation $hook Output hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if ($PAGE->pagetype !== 'mod-resource-view' || $PAGE->context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'resource') {
            return;
        }

        // Select exactly the same main file as mod/resource/view.php. Secondary CSVs are not inspected.
        $files = get_file_storage()->get_area_files(
            $PAGE->context->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false,
        );
        $file = reset($files);
        if (!$file || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'pdf') {
            return;
        }
        if ($file->get_repository_type() !== 's3bucket') {
            return;
        }

        $PAGE->requires->js_call_amd('repository_s3bucket/pdf_navigation', 'init');
    }
}
