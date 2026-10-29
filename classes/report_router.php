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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace repository_s3bucket;

/**
 * Resolve a UUID without maintaining a separate mapping table.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_router {
    /**
     * Find exactly one resource and apply Moodle's course and activity access checks.
     *
     * @param string $idnumber Lowercase UUID stored in the File activity ID number
     * @param string|null $page Optional physical PDF page, starting at one
     * @return \moodle_url Protected standard File activity URL
     */
    public static function target(string $idnumber, ?string $page = null): \moodle_url {
        global $CFG;

        require_once($CFG->dirroot . '/mod/resource/locallib.php');
        require_login(null, false, null, false, true);
        if (isguestuser()) {
            throw new \moodle_exception('reportunavailable', 'repository_s3bucket');
        }
        self::validate_link($idnumber, $page);
        $cm = self::find_activity($idnumber);
        $course = get_course($cm->course);
        try {
            require_login($course, false, $cm, false, true);
        } catch (\require_login_exception $exception) {
            throw new \moodle_exception('reportunavailable', 'repository_s3bucket');
        }
        $context = \context_module::instance($cm->id);
        if (!$cm->uservisible || !has_capability('mod/resource:view', $context)) {
            throw new \moodle_exception('reportunavailable', 'repository_s3bucket');
        }
        self::require_embedded_pdf($cm);

        $params = ['id' => $cm->id, 'forceview' => 1];
        if ($page !== null) {
            $params['pdfpage'] = (int)$page;
        }
        return new \moodle_url('/mod/resource/view.php', $params);
    }

    /**
     * Validate a UUID and optional physical page without silently cleaning invalid input.
     *
     * @param string $idnumber File activity ID number (UUID)
     * @param string|null $page Requested page
     */
    private static function validate_link(string $idnumber, ?string $page): void {
        if (
            !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/D', $idnumber) ||
            ($page !== null && (!preg_match('/^[1-9][0-9]{0,9}$/D', $page) || (int)$page > 2147483647))
        ) {
            throw new \moodle_exception('invalidreportlink', 'repository_s3bucket');
        }
    }

    /**
     * Require one globally unambiguous File activity for this UUID.
     *
     * @param string $idnumber File activity ID number (UUID)
     * @return \cm_info Activity with current-user availability data
     */
    private static function find_activity(string $idnumber): \cm_info {
        global $DB;

        $sql = "SELECT cm.id, cm.course
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.idnumber = :idnumber AND m.name = :module AND cm.deletioninprogress = 0";
        $matches = $DB->get_records_sql($sql, ['idnumber' => $idnumber, 'module' => 'resource'], 0, 2);
        // Never guess between duplicates, even when the requester is an administrator.
        if (count($matches) !== 1) {
            throw new \moodle_exception('reportunavailable', 'repository_s3bucket');
        }
        $match = reset($matches);
        return get_fast_modinfo($match->course)->get_cm($match->id);
    }

    /**
     * Route only embedded S3 PDF aliases; companion CSV files do not select the report.
     *
     * @param \cm_info $cm Authorised File activity
     */
    private static function require_embedded_pdf(\cm_info $cm): void {
        global $DB;

        $context = \context_module::instance($cm->id);
        $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);
        $files = get_file_storage()->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = reset($files);
        if (
            !$file || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'pdf' ||
            $file->get_repository_type() !== 's3bucket' ||
            (int)resource_get_final_display_type($resource) !== RESOURCELIB_DISPLAY_EMBED
        ) {
            throw new \moodle_exception('reportunavailable', 'repository_s3bucket');
        }
    }
}
