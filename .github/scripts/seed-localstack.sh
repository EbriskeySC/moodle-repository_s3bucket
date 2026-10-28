#!/usr/bin/env bash
# Only synthetic, public test data; never use a real AWS account in CI.
set -euo pipefail
container="${1:?Pass the LocalStack container ID}"
fixtures="$(mktemp -d)"
trap 'rm -rf "$fixtures"' EXIT
# Optimising GD produces a small valid JPEG. Pad it to the legacy fixture sizes.
php -r '
$image = imagecreatetruecolor(1, 1);
imageinterlace($image, true);
ob_start();
imagejpeg($image, null, 75);
$data = ob_get_clean();
if (strlen($data) > 645) {
    fwrite(STDERR, "JPEG fixture exceeds expected size\n");
    exit(1);
}
file_put_contents($argv[1] . "/root.jpg", str_pad($data, 645, "\0"));
file_put_contents($argv[1] . "/nested.jpg", str_pad($data, 7237, "\0"));
' "$fixtures"
touch "$fixtures/keep"
docker cp "$fixtures/." "$container:/tmp/s3bucket-fixtures"
docker exec "$container" awslocal s3api create-bucket --bucket testbucket
docker exec "$container" awslocal s3 cp /tmp/s3bucket-fixtures/root.jpg s3://testbucket/testfile.jpg --content-type image/jpeg
docker exec "$container" awslocal s3 cp /tmp/s3bucket-fixtures/nested.jpg s3://testbucket/testdirectory/testfile.jpg --content-type image/jpeg
docker exec "$container" awslocal s3 cp /tmp/s3bucket-fixtures/keep s3://testbucket/fakedirectory/.keep
