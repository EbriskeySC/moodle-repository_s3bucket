# Amazon S3 bucket — custom Moodle repository

This fork of [ewallah/moodle-repository_s3bucket](https://github.com/ewallah/moodle-repository_s3bucket)
combines administrator-only repository selection with inline PDF delivery.
Modified by Evan Briskey in October 2026; original copyright and GPL v3-or-later
notices are retained. See LICENSE.

## Compatibility and installation

Component: `repository_s3bucket`. Release: `v5.2.2-fork.1`.
Requires Moodle 5.1 or newer; declared support is Moodle 5.1–5.2.
**This package cannot be installed on Moodle 4.5.**

Download the installation ZIP attached to this fork's GitHub release.
The ZIP contains a single `s3bucket/` directory. Install it at
`repository/s3bucket` (under `public/` in Moodle 5.1+), replacing the
existing plugin of the same component if present. Keep a backup and test on
staging first. Visit Site administration > Notifications to apply the upgrade.
The bundled core `repository_s3` dependency must be available.

Enable Amazon S3 bucket under Site administration > Plugins > Repositories >
Manage repositories. Configure a repository instance with its bucket, region,
and IAM credentials. AWS permissions to list the bucket and read its objects
are sufficient; upload and delete permissions are unnecessary. Do not commit
credentials or put them in the installation ZIP.

Only site administrators can browse, search, or select files, including through
direct file-picker requests. Legacy teacher or manager capability grants cannot
bypass this restriction. Published files still use Moodle's activity permissions.

## Publish an updating PDF

1. Add a File activity and select a PDF from the S3 bucket repository as an administrator.
2. Choose **Create an alias/shortcut**, rather than making a copy.
3. Set Appearance > Display to **Embed**, then save.
4. Replace the S3 object at exactly the same bucket and key to update the report.
5. Refresh or reopen the activity to request the current object.

An open PDF does not poll S3 or reload automatically. PDF responses use
`application/pdf` and request cache revalidation, including when S3 metadata
originally says `application/octet-stream`. Explicit Force download requests
remain downloads. Other formats, including CSV, remain attachments.

For a companion CSV, select it as a second alias in the same File activity,
keep the PDF as the main file, and add a **Download CSV** link in the activity
description using the protected Moodle file URL with `forcedownload=1`.
Alternatively, use a separate File activity with Force download. Course setup
provides the button and page styling; the plugin does not generate them.
Never save an expiring signed S3 URL as the course link.

## Verification and workflows

The Tests workflow checks Moodle 5.1 and 5.2, PHP 8.3 and 8.4, PostgreSQL 17
and MariaDB 11.4. It seeds a disposable LocalStack bucket with synthetic
fixtures, so S3 integration tests do not require real AWS credentials. Browser
scenarios run on Moodle 5.2 / PHP 8.3 / PostgreSQL. Scheduled checks reuse the
same workflow.

The Infection workflow uses the plugin's component PHPUnit configuration and
Moodle's own PHPUnit executable. Coverage includes `lib.php`; mutation reports
are saved as workflow artifacts. Mutation scores are diagnostic; this fork
does not define a minimum score.

The Package release workflow creates a Moodle installation ZIP and SHA-256
checksum when a version tag is pushed, or for an existing tag selected manually.
The tag must match `$plugin->release` in `version.php`. Runtime sources and GPL
license are included; tests, workflows, and local configuration are excluded.
Releases are published to this GitHub fork. No Moodle.org publication occurs.

Before deployment, verify administrator file selection, denial for teacher,
manager, student, and guest users, learner access to published activities,
embedded PDF refresh after replacing the same S3 key, and CSV download only
after clicking its link.

## Upstream information

The upstream plugin targets Moodle core Boost and Boost child themes, including
Classic, and documents MySQL, MariaDB, and PostgreSQL support. Its documentation
states that encrypted files or buckets are unsupported. Moodle Workplace and
Totara have not been tested.

Report issues with this customization to
[this fork](https://github.com/EbriskeySC/moodle-repository_s3bucket/issues).
Upstream development is at
[ewallah/moodle-repository_s3bucket](https://github.com/ewallah/moodle-repository_s3bucket).

[![Tests](https://github.com/EbriskeySC/moodle-repository_s3bucket/actions/workflows/main.yml/badge.svg)](https://github.com/EbriskeySC/moodle-repository_s3bucket/actions/workflows/main.yml)
