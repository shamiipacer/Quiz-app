#!/usr/bin/env python3
"""Archive staging after Composer --no-dev and package discovery."""
from pathlib import Path
import hashlib, shutil, zipfile
root = Path(__file__).resolve().parents[1]
stage = root / 'dist/hostinger'
shutil.copy2(root / 'docs/hostinger.md', stage / 'DEPLOYMENT.md')
shutil.copy2(root / 'docs/release-status.md', stage / 'RELEASE-STATUS.md')
shutil.copy2(root / 'backend/.env.hostinger.example', stage / 'quiz-backend/.env.hostinger.example')
for p in list((stage / 'quiz-backend/vendor').rglob('*')):
    if p.is_dir() and p.name in {'tests', 'Tests', 'test', '.github'}:
        shutil.rmtree(p, ignore_errors=True)
for p in (stage / 'quiz-backend/storage/logs').glob('*.log'):
    p.unlink()
archive = root / 'dist/hostinger.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for p in sorted(stage.rglob('*')):
        z.write(p, p.relative_to(stage))
with zipfile.ZipFile(archive) as z:
    names = z.namelist()
    assert 'quiz-backend/storage/framework/views/' in names
    assert 'public_html/.htaccess' in names
    assert not any(n.endswith('/.env') or '/.git/' in n or n.endswith('.sqlite') for n in names)
    assert 'quiz-backend/vendor/phpunit/phpunit/phpunit' not in names
    assert z.testzip() is None
(root / 'dist/SHA256SUMS').write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  hostinger.zip\n')
print(f'Validated {archive} ({archive.stat().st_size} bytes)')
