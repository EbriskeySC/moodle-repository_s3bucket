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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Check routing and Moodle permissions using real course resources.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_router::class)]
final class report_router_test extends \advanced_testcase {
    /** @var string File activity ID number used by the fixture. */
    private const IDNUMBER = '11111111-1111-4111-8111-111111111111';

    /**
     * Create a report without contacting S3.
     *
     * @param \stdClass $course Course record
     * @param string $idnumber Activity ID number
     * @param string $filename Main filename
     * @param bool $alias Use an S3 reference
     * @return \stdClass Resource record
     */
    private function report(
        \stdClass $course,
        string $idnumber,
        string $filename = 'latest-scan.pdf',
        bool $alias = true,
    ): \stdClass {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/repository/lib.php');
        $generator = $this->getDataGenerator();
        $resource = $generator->create_module('resource', ['course' => $course->id, 'display' => RESOURCELIB_DISPLAY_EMBED]);
        $DB->set_field('course_modules', 'idnumber', $idnumber, ['id' => $resource->cmid]);
        $context = \context_module::instance($resource->cmid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        $record = [
            'contextid' => $context->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename, 'sortorder' => 1,
        ];
        if ($alias) {
            if (!\repository::get_type_by_typename('s3bucket')) {
                $generator->create_repository_type('s3bucket');
            }
            $id = \repository::create('s3bucket', $USER->id, \context_system::instance(), [
                'name' => 'Routing fixture', 'bucket_name' => 'testbucket',
                'access_key' => 'test', 'secret_key' => 'test', 'endpoint' => 'us-east-1',
            ]);
            $fs->create_file_from_reference($record, $id, $idnumber . '/' . $filename);
        } else {
            $fs->create_file_from_string($record, '%PDF-test');
        }
        rebuild_course_cache($course->id, true);
        return $resource;
    }

    /**
     * Cases which must not resolve even when a UUID is known.
     *
     * @return array
     */
    public static function unavailable_cases(): array {
        return [
            'not enrolled' => ['unenrolled'],
            'hidden activity' => ['hidden'],
            'group restriction' => ['group'],
            'future date restriction' => ['future'],
            'duplicate UUID across courses' => ['duplicate'],
            'CSV main file' => ['csv'],
            'uploaded PDF copy' => ['upload'],
            'missing UUID' => ['missing'],
            'hidden course' => ['hiddencourse'],
            'view capability denied' => ['capability'],
        ];
    }

    /**
     * Unavailable reports all return the same message.
     *
     * @param string $reason Fixture change
     */
    #[DataProvider('unavailable_cases')]
    public function test_unavailable_report(string $reason): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/resourcelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $resource = $this->report($course, self::IDNUMBER, $reason === 'csv' ? 'scan.csv' : 'scan.pdf', $reason !== 'upload');
        $learner = $generator->create_user();
        if ($reason !== 'unenrolled') {
            $generator->enrol_user($learner->id, $course->id, 'student');
        }
        if ($reason === 'hidden') {
            set_coursemodule_visible($resource->cmid, 0);
        } else if ($reason === 'group' || $reason === 'future') {
            if ($reason === 'group') {
                $group = $generator->create_group(['courseid' => $course->id]);
                $condition = ['type' => 'group', 'id' => (int)$group->id];
            } else {
                $condition = ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS];
            }
            $DB->set_field(
                'course_modules',
                'availability',
                json_encode(['op' => '&', 'c' => [$condition], 'showc' => [false]]),
                ['id' => $resource->cmid],
            );
        } else if ($reason === 'duplicate') {
            $this->report($generator->create_course(), self::IDNUMBER);
        } else if ($reason === 'hiddencourse') {
            $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        } else if ($reason === 'capability') {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
            assign_capability('mod/resource:view', CAP_PROHIBIT, $roleid, \context_module::instance($resource->cmid));
        }
        rebuild_course_cache($course->id, true);
        $this->setUser($learner);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('reportunavailable', 'repository_s3bucket'));
        report_router::target($reason === 'missing' ? '11111111-1111-1111-1111-111111111111' : self::IDNUMBER, '12');
    }

    /**
     * Group membership grants access to multiple File activities in the same course.
     */
    public function test_group_access_to_multiple_resources_in_one_course(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->libdir . '/resourcelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $multigroupuser = $generator->create_user();
        $singlegroupuser = $generator->create_user();
        foreach ([$multigroupuser, $singlegroupuser] as $user) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        $reports = [];
        foreach ([self::IDNUMBER, '22222222-2222-2222-2222-222222222222'] as $index => $idnumber) {
            $reports[$idnumber] = $this->report($course, $idnumber);
            $group = $generator->create_group(['courseid' => $course->id]);
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $multigroupuser->id]);
            if ($index === 1) {
                $generator->create_group_member(['groupid' => $group->id, 'userid' => $singlegroupuser->id]);
            }
            $DB->set_field('course_modules', 'availability', json_encode([
                'op' => '&', 'c' => [['type' => 'group', 'id' => (int)$group->id]], 'showc' => [false],
            ]), ['id' => $reports[$idnumber]->cmid]);
        }
        rebuild_course_cache($course->id, true);
        $this->setUser($multigroupuser);
        foreach ($reports as $idnumber => $resource) {
            // Each resolution represents a separate HTTP request with a fresh page context.
            $PAGE = new \moodle_page();
            $url = report_router::target($idnumber, '12');
            $this->assertSame(['id' => (string)$resource->cmid, 'forceview' => '1', 'pdfpage' => '12'], $url->params());
        }
        $this->setUser($singlegroupuser);
        $PAGE = new \moodle_page();
        $restrictedidnumber = '22222222-2222-2222-2222-222222222222';
        $expected = ['id' => (string)$reports[$restrictedidnumber]->cmid, 'forceview' => '1'];
        $this->assertSame($expected, report_router::target($restrictedidnumber)->params());
        $PAGE = new \moodle_page();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('reportunavailable', 'repository_s3bucket'));
        report_router::target(self::IDNUMBER, '12');
    }

    /**
     * A guest must not resolve any restricted resource, even when guest course access exists.
     */
    public function test_guest_is_denied(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('reportunavailable', 'repository_s3bucket'));
        report_router::target(self::IDNUMBER, '12');
    }

    /**
     * Login is required before UUID lookup.
     */
    public function test_anonymous_user_requires_login(): void {
        $this->resetAfterTest();
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        report_router::target(self::IDNUMBER, '12');
    }

    /**
     * Invalid identifiers and pages must not silently select a different target.
     *
     * @return array
     */
    public static function invalid_links(): array {
        return [
            'path instead of UUID' => ['../../config.php', '12'],
            'zero page' => [self::IDNUMBER, '0'],
            'negative page' => [self::IDNUMBER, '-1'],
            'fractional page' => [self::IDNUMBER, '1.5'],
            'overflow page' => [self::IDNUMBER, '99999999999999999999'],
        ];
    }

    /**
     * Reject malformed link parameters.
     *
     * @param string $idnumber Identifier
     * @param string $page Page number
     */
    #[DataProvider('invalid_links')]
    public function test_invalid_link(string $idnumber, string $page): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidreportlink', 'repository_s3bucket'));
        report_router::target($idnumber, $page);
    }
}
