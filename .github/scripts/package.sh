#!/usr/bin/env bash
# Package tracked runtime sources with the folder name Moodle expects.
set -euo pipefail
tag="${1:?Pass the release tag}"
[[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+([.-][a-zA-Z0-9.-]+)?$ ]] || { echo "Invalid release tag" >&2; exit 1; }
grep -Fq "\$plugin->release = '$tag';" version.php || { echo "Tag must match version.php release" >&2; exit 1; }
mkdir -p dist
archive="repository_s3bucket-$tag.zip"
git archive --format=zip --prefix=s3bucket/ --output="dist/$archive" HEAD -- \
    lib.php report.php version.php LICENSE README.md amd classes db lang pix
(cd dist && sha256sum "$archive" > "$archive.sha256")
unzip -t "dist/$archive"
