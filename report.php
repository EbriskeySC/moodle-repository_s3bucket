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

/**
 * Open an authorised S3 PDF resource using its File activity ID number.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

$PAGE->set_url('/repository/s3bucket/report.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_cacheable(false);

// Keep the entire original link as the login return URL, including pdfpage.
require_login(null, false);
if (isguestuser()) {
    $SESSION->wantsurl = qualified_me();
    redirect(get_login_url());
}
$idnumber = optional_param('idnumber', null, PARAM_RAW_TRIMMED);
if ($idnumber === null) {
    // Retain compatibility with links created during local development.
    $idnumber = optional_param('client', '', PARAM_RAW_TRIMMED);
}
$page = optional_param('pdfpage', null, PARAM_RAW_TRIMMED);
redirect(\repository_s3bucket\report_router::target($idnumber, $page));
