#!/usr/bin/env python3
from pathlib import Path
import os, zipfile
root = Path(__file__).resolve().parents[1]
mobile = root / 'mobile'
(root / 'dist').mkdir(exist_ok=True)
with zipfile.ZipFile(root / 'dist/mobile-source.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for current, dirs, files in os.walk(mobile):
        dirs[:] = [d for d in dirs if d not in {'node_modules','android','ios','.expo','.git'}]
        for name in files:
            if name == '.env': continue
            p = Path(current) / name
            z.write(p, Path('mobile') / p.relative_to(mobile))
    for name in ['hostinger.md','build-apk-windows.md','setup.md','api.md','privacy-template.md','terms-template.md','release-status.md']:
        z.write(root / 'docs' / name, Path('docs') / name)
print('Mobile source archive created without dependencies or credentials')
