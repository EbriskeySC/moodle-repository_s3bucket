# Amazon S3 bucket — custom Moodle repository

This fork of [ewallah/moodle-repository_s3bucket](https://github.com/ewallah/moodle-repository_s3bucket)
combines administrator-only repository selection, inline PDF delivery,
page-specific PDF links, and routing by File activity ID number in one plugin.
Modified by Evan Briskey in October 2026; original copyright and GPL v3-or-later
notices are retained. See LICENSE.

## Compatibility and installation

Component: `repository_s3bucket`. Release: `v5.2.2-fork.2`.
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

## Open a specific PDF page inside Moodle

For an S3 PDF alias displayed with **Embed** in a standard File activity, this
plugin passes the requested page from the activity URL to the existing viewer.
It adds no visible controls. Keep the same File activity; no new activity type
or Moodle core changes are needed. Uploaded copies, other repository types,
and CSV main files do not receive this page-link handling.

Share the activity URL with a page query parameter, for example:

`https://your-moodle-site/mod/resource/view.php?id=123&pdfpage=12`

Moodle checks login, enrolment, and activity access before displaying the PDF.
The query form survives login redirects. An already signed-in user can also use
`view.php?id=123#page=12`. Page numbers refer to the PDF's physical pages,
starting at 1; browser PDF viewers determine support for page fragments.

The URL selects the requested starting page. Manual scrolling inside the
browser's PDF viewer does not update the activity URL. The existing Download
CSV link and the browser's own PDF controls are unaffected.

Release `v5.2.2-fork.2` includes this feature; the earlier fork.1 ZIP
predates it. Rebuild the JavaScript with Moodle's Grunt AMD task after editing
`amd/src/pdf_navigation.js`. Install the plugin version upgrade and purge
caches so Moodle registers the output hook and loads the new module.

## Link to a File activity by ID number

Multiple PDF resources can share a course. Set each **File activity's ID
number** under **Common module settings** to a lowercase UUID. For an external
system that identifies S3 folders by UUID, use that existing UUID. Keep the
activity's visible name descriptive. The course ID number is not used for
resource routing. Each UUID must identify exactly one File activity across
the site; duplicate ID numbers are rejected.

Link from your platform using:

`https://your-moodle-site/repository/s3bucket/report.php?idnumber=ACTIVITY-UUID&pdfpage=12`

The optional `pdfpage` parameter selects the requested physical PDF page. The
router requires login, finds the activity by its ID number, checks course and
activity access, then redirects to its standard File page with the requested
page. That page and its protected file URLs retain their normal access checks.
The main file must be an S3 bucket PDF alias displayed with **Embed**. The
existing companion CSV link stays in the File activity description.

Manage access through course enrolments and activity availability restrictions.
Users may access several resources or only one, according to their permissions.
The ID number identifies a File activity; it grants no access. The router does
not create enrolments or group or cohort memberships. Missing, duplicate and
inaccessible resources show the same unavailable message.

Links created during local development using the older `client` parameter
continue to work. Use `idnumber` for new links; it takes precedence when both
parameters are present. Only UUID-formatted ID numbers are currently supported.

This uses the existing File activity metadata: no separate mapping file,
database table, LTI integration, or companion plugin is required. The combined
router is included in `v5.2.2-fork.2`; the earlier fork.1 ZIP does not include
it. `report.php` and `classes/report_router.php` implement routing.

## Verification and workflows

The Tests workflow checks Moodle 5.1 and 5.2, PHP 8.3 and 8.4, PostgreSQL 17
and MariaDB 11.4. It seeds a disposable LocalStack bucket with synthetic
fixtures, so S3 integration tests do not require real AWS credentials. Browser
scenarios run on Moodle 5.2 / PHP 8.3 / PostgreSQL. Scheduled checks reuse the
same workflow.

The Infection workflow uses Moodle's main PHPUnit configuration, restricted to
this plugin's test suite, and Moodle's own PHPUnit executable. Coverage includes `lib.php`; mutation reports
are saved as workflow artifacts. Mutation scores are diagnostic; this fork
does not define a minimum score.
Mutants run serially because Moodle test processes share a database and dataroot.
The mutation suite excludes generator and Behat-step helper tests, whose coverage
targets are test-support classes. The regular Tests workflow runs all plugin tests.

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
