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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/repository/s3bucket/lib.php');

/**
 * Administrator-only repository access tests, without live S3 access.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\repository_s3bucket::class)]
final class admin_access_test extends \advanced_testcase {
    /**
     * Non-administrator roles and file-picker operations.
     *
     * @return array
     */
    public static function picker_operations(): array {
        $operations = [
            'browse' => ['get_listing', []],
            'search' => ['search', ['current']],
            'link' => ['get_link', ['current.pdf']],
            'alias' => ['get_file_reference', ['current.pdf']],
            'copy' => ['get_file', ['current.pdf']],
        ];
        $cases = [];
        foreach (['student', 'editingteacher', 'manager'] as $role) {
            foreach ($operations as $name => [$method, $args]) {
                $cases[$role . ' ' . $name] = [$role, $method, $args];
            }
        }
        return $cases;
    }

    /**
     * Existing role grants cannot bypass the administrator restriction.
     *
     * @param string $role Role shortname
     * @param string $method File-picker method
     * @param array $args Method arguments
     */
    #[DataProvider('picker_operations')]
    public function test_nonadmin_cannot_select_files(string $role, string $method, array $args): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $context = \context_system::instance();
        $roleid = $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST);
        assign_capability('repository/s3bucket:view', CAP_ALLOW, $roleid, $context);
        role_assign($roleid, $USER->id, $context->id);
        $this->assertTrue(has_capability('repository/s3bucket:view', $context));
        $repo = (new \ReflectionClass(\repository_s3bucket::class))->newInstanceWithoutConstructor();
        $this->assertFalse($repo->is_visible());
        $this->expectException(\repository_exception::class);
        $repo->$method(...$args);
    }

    /**
     * Site administrators can see repositories and create file references.
     */
    public function test_admin_can_select_files(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_repository_type('s3bucket');
        $context = \context_system::instance();
        $id = \repository::create('s3bucket', $USER->id, $context, [
            'name' => 'Administrator test bucket', 'bucket_name' => 'testbucket',
            'access_key' => 'test', 'secret_key' => 'test', 'endpoint' => 'us-east-1',
        ]);
        $repo = \repository::get_repository_by_id($id, $context);
        $this->assertTrue($repo->is_visible());
        $this->assertSame('current.pdf', $repo->get_file_reference('current.pdf'));
        $this->assertStringContainsString('/repository_s3bucket/s3/', $repo->get_link('current.pdf'));
    }

    /**
     * Guests cannot browse the repository.
     */
    public function test_guest_cannot_browse(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $repo = (new \ReflectionClass(\repository_s3bucket::class))->newInstanceWithoutConstructor();
        $this->assertFalse($repo->is_visible());
        $this->expectException(\repository_exception::class);
        $repo->get_listing();
    }
}
