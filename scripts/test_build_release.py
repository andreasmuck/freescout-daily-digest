"""Release-output safety checks; all builds run in disposable directories."""
import contextlib
import hashlib
import io
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest import mock

import build_release as builder


class ReleaseOutputTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve() / 'source'
        self.root.mkdir()
        for name in builder.FILES:
            shutil.copy2(builder.ROOT / name, self.root / name)
        for name in builder.DIRECTORIES:
            shutil.copytree(builder.ROOT / name, self.root / name)
        patch = mock.patch.object(builder, 'ROOT', self.root)
        patch.start()
        self.addCleanup(patch.stop)
        self.output = self.root.parent / 'release'

    def build(self):
        with contextlib.redirect_stdout(io.StringIO()):
            builder.build(self.output)

    def snapshot(self):
        return {p.name: p.read_bytes() for p in self.output.iterdir()}

    def test_success_is_reproducible_and_checksums_match(self):
        self.build()
        before = self.snapshot()
        self.build()
        self.assertEqual(before, self.snapshot())
        for line in (self.output / 'SHA256SUMS').read_text().splitlines():
            digest, name = line.split()
            self.assertEqual(digest, hashlib.sha256((self.output / name).read_bytes()).hexdigest())

    def test_missing_changelog_preserves_existing_assets(self):
        self.build()
        before = self.snapshot()
        (self.root / 'CHANGELOG.md').write_text('Missing release section')
        with self.assertRaisesRegex(ValueError, 'Missing changelog'):
            self.build()
        self.assertEqual(before, self.snapshot())

    def test_archive_validation_failure_preserves_existing_assets(self):
        self.build()
        before = self.snapshot()
        with mock.patch.object(builder.zipfile.ZipFile, 'testzip', return_value='bad.php'):
            with self.assertRaisesRegex(ValueError, 'integrity'):
                self.build()
        self.assertEqual(before, self.snapshot())
        self.assertFalse(list(self.output.parent.glob('.dailydigest-build-*')))

    def test_stale_zip_rejected_without_modifying_any_assets(self):
        self.build()
        (self.output / 'DailyDigest-0.9.0.zip').write_bytes(b'historical package')
        before = self.snapshot()
        with self.assertRaisesRegex(ValueError, 'DailyDigest-0.9.0.zip'):
            self.build()
        self.assertEqual(before, self.snapshot())

    def fail_second_replace(self):
        original = Path.replace
        calls = 0

        def replace(path, target):
            nonlocal calls
            calls += 1
            if calls == 2:
                raise OSError('simulated publication failure')
            return original(path, target)
        return mock.patch.object(Path, 'replace', replace)

    def test_publication_failure_restores_existing_assets(self):
        self.build()
        before = self.snapshot()
        (self.root / 'README.md').write_text('Updated README')
        with self.fail_second_replace():
            with self.assertRaisesRegex(OSError, 'publication failure'):
                self.build()
        self.assertEqual(before, self.snapshot())

    def test_publication_failure_removes_partial_new_release(self):
        with self.fail_second_replace():
            with self.assertRaisesRegex(OSError, 'publication failure'):
                self.build()
        self.assertFalse(self.output.exists())


if __name__ == '__main__':
    unittest.main()
