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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Integration coverage for page navigation on the existing File activity.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pdf_navigation::class)]
final class pdf_navigation_test extends \advanced_testcase {
    /**
     * Main-file cases; secondary files must not enable page navigation.
     *
     * @return array
     */
    public static function file_cases(): array {
        return [
            'S3 PDF alias for learner' => ['current.pdf', true, false, true],
            'uppercase S3 PDF alias' => ['current.PDF', true, false, true],
            'uploaded PDF' => ['current.pdf', false, false, false],
            'S3 CSV alias' => ['current.csv', true, false, false],
            'secondary PDF beside main CSV' => ['current.csv', true, true, false],
        ];
    }

    /**
     * Dispatch the registered output hook with real resource files and a learner session.
     *
     * @param string $filename Main filename
     * @param bool $alias Whether the main file references S3
     * @param bool $secondarypdf Add a secondary PDF
     * @param bool $expected Whether to queue page navigation
     */
    #[DataProvider('file_cases')]
    public function test_only_main_s3_pdf_gets_navigation(
        string $filename,
        bool $alias,
        bool $secondarypdf,
        bool $expected,
    ): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $resource = $generator->create_module('resource', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('resource', $resource->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_resource', 'content');
        $record = [
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'sortorder' => 1,
        ];
        if ($alias) {
            $generator->create_repository_type('s3bucket');
            $id = \repository::create('s3bucket', $USER->id, \context_system::instance(), [
                'name' => 'Navigation test bucket',
                'bucket_name' => 'testbucket',
                'access_key' => 'test',
                'secret_key' => 'test',
                'endpoint' => 'us-east-1',
            ]);
            $fs->create_file_from_reference($record, $id, $filename);
            if ($secondarypdf) {
                $record['filename'] = 'secondary.pdf';
                $record['sortorder'] = 0;
                $fs->create_file_from_reference($record, $id, 'secondary.pdf');
            }
        } else {
            $fs->create_file_from_string($record, '%PDF-test');
        }

        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, 'student');
        $this->setUser($learner);
        $PAGE = new \moodle_page();
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url('/mod/resource/view.php', ['id' => $cm->id]);
        $PAGE->set_pagetype('mod-resource-view');

        $hook = new before_standard_head_html_generation($PAGE->get_renderer('core'));
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $javascript = $PAGE->requires->get_end_code();
        if ($expected) {
            $this->assertStringContainsString('repository_s3bucket/pdf_navigation', $javascript);
            $this->assertStringNotContainsString('PDF page', $javascript);
        } else {
            $this->assertStringNotContainsString('repository_s3bucket/pdf_navigation', $javascript);
        }
    }

    /**
     * Other pages must not load file metadata or the navigation module.
     */
    public function test_other_pages_do_not_get_navigation(): void {
        global $PAGE;

        $this->resetAfterTest();
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
        $PAGE->set_pagetype('site-index');
        $hook = new before_standard_head_html_generation($PAGE->get_renderer('core'));
        pdf_navigation::before_standard_head_html_generation($hook);
        $this->assertStringNotContainsString('repository_s3bucket/pdf_navigation', $PAGE->requires->get_end_code());
    }
}
