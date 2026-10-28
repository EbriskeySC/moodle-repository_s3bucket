# repository_s3bucket

## PDF embedding customization

Modified on 2026-10-26 by Evan Briskey. Original copyright and GPL notices are
retained. This branch changes file delivery only; repository role permissions
remain the same as upstream.

PDFs can display inline when Moodle requests an embedded view. An explicit
Force download choice still downloads the PDF. CSVs and other formats remain
attachments. Inline PDFs receive the application/pdf MIME type even if their
S3 metadata specifies application/octet-stream. Both the Moodle redirect and
signed S3 response request cache revalidation.

### Publish a PDF that follows S3 updates

1. Add a File activity and select the PDF from the S3 bucket repository.
2. Choose Create an alias/shortcut rather than making a copy.
3. Set Appearance > Display to Embed, then save.
4. Replace the S3 object at exactly the same bucket and key to update the report.
5. Refresh or reopen the Moodle activity to request the current object. An already
   open PDF does not poll S3 or automatically reload.

For an optional companion CSV, select it as a second alias in the same File
activity, keep the PDF as the main file, and add a Download CSV link in the
activity description using the protected Moodle file URL with forcedownload=1.
Alternatively, use a separate File activity with Force download. The button and
page styling are course configuration; this plugin does not generate them.
Never save an expiring presigned S3 URL as the course link.

### Installation and verification

Install this fork in repository/s3bucket, replacing the original plugin. Visit
Site administration > Notifications to apply the version change. Release
v5.2.1-pdf.1 requires Moodle 5.1 or newer and declares Moodle 5.1–5.2 support;
it is not a Moodle 4.5 backport.

Run tests/pdf_display_test.php in a configured Moodle PHPUnit environment.
The tests cover embedded PDFs, uppercase PDF extensions, explicit PDF downloads,
CSV downloads, signed response settings, and the File activity's display choice.
Existing tests/localstack_test.php additionally covers delivery through LocalStack.

Instead of giving all users access to your complete S3 account, this plugin makes it
possible to give teachers and managers access to a specific S3 folder (bucket).

Multiple instances are supported, you only have to create a IAM user who has read access
to your S3 root folder, but read and write permissions to your S3 bucket.

## Warning

* This plugin is 100% open source and has NOT been tested in Moodle Workplace, Totara, or any other proprietary software system. As long as the latter do not reward plugin developers, you can use this plugin only in 100% open source environments.
* Encrypted files or buckets are not supported.

## Theme support

This plugin is developed and tested on Moodle Core's Boost theme and Boost child themes, including Moodle Core's Classic theme.

## Database support

This plugin is developed and tested using

* MYSQL
* MariaDB
* PostgreSQL

## Plugin repositories

This plugin will be published and regularly updated on [Github](https://github.com/ewallah/moodle-repository_s3bucket)

## Bug and problem reports / Support requests

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.
Please report bugs and problems on [Github](https://github.com/ewallah/moodle-repository_s3bucket/issues)
We will do our best to solve your problems, but please note that we can't provide per-case support.

## Feature proposals

- Please issue feature proposals on [Github](https://github.com/ewallah/moodle-repository_s3bucket/issues)
- Please create pull requests on [Github](https://github.com/ewallah/moodle-repository_s3bucket/pulls)
- We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature proposals and not as feature requests.


[![Build Status](https://github.com/ewallah/moodle-repository_s3bucket/workflows/Tests/badge.svg)](https://github.com/ewallah/moodle-repository_s3bucket/actions)
[![Coverage Status](https://coveralls.io/repos/github/ewallah/moodle-repository_s3bucket/badge.svg?branch=main)](https://coveralls.io/github/ewallah/moodle-repository_s3bucket?branch=main)
