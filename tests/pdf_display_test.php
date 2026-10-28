<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace repository_s3bucket;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/repository/s3bucket/lib.php');

/**
 * PDF display tests, without live S3 access.
 *
 * @package    repository_s3bucket
 * @copyright  2026 Evan Briskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\repository_s3bucket::class)]
final class pdf_display_test extends \advanced_testcase {
    /**
     * PDF embedding and download cases.
     *
     * @return array
     */
    public static function display_cases(): array {
        return [
            'embedded PDF' => ['folder/current.pdf', false, 'inline', 'application/pdf'],
            'uppercase PDF' => ['folder/current.PDF', false, 'inline', 'application/pdf'],
            'PDF download' => ['folder/current.pdf', true, 'attachment', 'application/pdf'],
            'CSV download' => ['folder/current.csv', false, 'attachment', 'text/csv'],
        ];
    }

    /**
     * The signed S3 response must respect embedding and preserve downloads.
     *
     * @param string $key Object key
     * @param bool $forcedownload Download request
     * @param string $disposition Expected disposition
     * @param string $mimetype Expected MIME type
     */
    #[DataProvider('display_cases')]
    public function test_signed_response(string $key, bool $forcedownload, string $disposition, string $mimetype): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $repo = $this->getMockBuilder(\repository_s3bucket::class)
            ->disableOriginalConstructor()->onlyMethods(['get_option'])->getMock();
        $repo->method('get_option')->with('bucket_name')->willReturn('testbucket');
        $client = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
        $property = new \ReflectionProperty(\repository_s3bucket::class, 's3client');
        $property->setValue($repo, $client);

        ob_start();
        try {
            $repo->send_otherfile($key, '+5 minutes', $forcedownload);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('Content-Type: ' . $mimetype, $output);
        $this->assertStringContainsString('Content-Disposition: ' . $disposition . ';', $output);
        preg_match('/Location: (.+)$/', $output, $matches);
        parse_str(parse_url($matches[1], PHP_URL_QUERY), $query);
        $this->assertSame($disposition, $query['response-content-disposition']);
        $this->assertSame('private, no-cache, max-age=0, must-revalidate', $query['response-cache-control']);
        if ($disposition === 'inline') {
            $this->assertSame('application/pdf', $query['response-content-type']);
        }
    }

    /** Moodle's File activity must pass its embed choice through to S3. */
    public function test_file_activity_passes_display_choice(): void {
        $this->resetAfterTest();
        set_config('duration', 5, 's3bucket');
        $repo = $this->getMockBuilder(\repository_s3bucket::class)
            ->disableOriginalConstructor()->onlyMethods(['send_otherfile'])->getMock();
        $repo->expects($this->once())->method('send_otherfile')
            ->with('folder/current.pdf', '+5 minutes', false);
        $file = $this->getMockBuilder(\stored_file::class)
            ->disableOriginalConstructor()->onlyMethods(['get_reference'])->getMock();
        $file->method('get_reference')->willReturn('folder/current.pdf');
        $repo->send_file($file, null, 0, false);
    }
}
