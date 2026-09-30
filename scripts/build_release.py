#!/usr/bin/env python3
"""Build verified FreeScout release assets without third-party dependencies."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import shutil
import struct
import zipfile

ROOT = Path(__file__).resolve().parents[1]
REPOSITORY = 'andreasmuck/freescout-daily-digest'
DIRECTORIES = ('Console', 'Http', 'Providers', 'Resources', 'Services', 'Public', 'Tests')
FILES = ('module.json', 'start.php', 'LICENSE', 'README.md', 'CHANGELOG.md', 'VALIDATION.md', 'PUBLISHING.md')


def build(output, tag=None, repository=None):
    manifest = json.loads((ROOT / 'module.json').read_text())
    version = manifest['version']
    if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', version):
        raise ValueError('Use a stable numeric major.minor.patch version.')
    if tag and tag != 'v' + version:
        raise ValueError('Tag must match module.json: v' + version)
    if repository and repository != REPOSITORY:
        raise ValueError('Repository does not match the configured update URLs.')
    base = 'https://github.com/' + REPOSITORY
    for key, expected in {
        'detailsUrl': base,
        'latestVersionUrl': base + '/releases/latest/download/version.txt',
        'latestVersionZipUrl': base + '/releases/latest/download/DailyDigest.zip',
    }.items():
        if manifest.get(key) != expected:
            raise ValueError('Incorrect ' + key)
    if 'latestVersionNumberUrl' in manifest:
        raise ValueError('FreeScout expects latestVersionUrl in module.json.')
    paths = [ROOT / name for name in FILES]
    for name in DIRECTORIES:
        paths.extend(p for p in (ROOT / name).rglob('*') if p.is_file())
    for path in paths:
        if not path.is_file() or path.is_symlink() or ROOT not in path.resolve().parents:
            raise ValueError('Missing or unsafe package file: ' + str(path))
        if path.name.startswith('.') or path.suffix in ('.zip', '.pyc'):
            raise ValueError('Unexpected package file: ' + str(path))
    png = (ROOT / 'Public/img/icon.png').read_bytes()
    if png[:8] != b'\x89PNG\r\n\x1a\n' or struct.unpack('>II', png[16:24]) != (256, 256):
        raise ValueError('Module icon must be a 256 x 256 PNG.')
    output.mkdir(parents=True, exist_ok=True)
    stable = output / 'DailyDigest.zip'
    with zipfile.ZipFile(stable, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(set(paths)):
            info = zipfile.ZipInfo('DailyDigest/' + path.relative_to(ROOT).as_posix(), (2020, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())
    with zipfile.ZipFile(stable) as archive:
        if archive.testzip():
            raise ValueError('Archive integrity check failed.')
        for path in paths:
            if archive.read('DailyDigest/' + path.relative_to(ROOT).as_posix()) != path.read_bytes():
                raise ValueError('Archive does not match source: ' + str(path))
    versioned = output / ('DailyDigest-' + version + '.zip')
    shutil.copyfile(stable, versioned)
    (output / 'version.txt').write_text(version + '\n')
    changelog = (ROOT / 'CHANGELOG.md').read_text()
    marker = '## ' + version + '\n'
    if marker not in changelog:
        raise ValueError('Missing changelog section for ' + version)
    notes = changelog.split(marker, 1)[1].split('\n## ', 1)[0].strip()
    (output / 'release-notes.md').write_text(notes + '\n')
    artifacts = [stable, versioned, output / 'version.txt']
    (output / 'SHA256SUMS').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest() + '  ' + p.name + '\n' for p in artifacts))
    print('Verified release ' + version + ': ' + str(len(set(paths))) + ' files in ' + str(output))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'dist')
    parser.add_argument('--tag')
    parser.add_argument('--repository')
    args = parser.parse_args()
    build(args.output.resolve(), args.tag, args.repository)
