#!/usr/bin/env python3
"""Build a private staging tree. Run Composer --no-dev there before archiving."""
import shutil
from pathlib import Path
root = Path(__file__).resolve().parents[1]
stage = root / 'dist' / 'hostinger'
if stage.exists(): shutil.rmtree(stage)
stage.mkdir(parents=True)
backend = root / 'backend'
def ignore(path, names):
    banned = {'.git', 'node_modules', 'tests', '.env', 'database.sqlite', '.phpunit.result.cache'}
    if Path(path) == backend / 'public': banned |= {'storage', 'hot'}
    if Path(path) == backend: banned |= {'vendor', 'storage', 'bootstrap'}
    return [n for n in names if n in banned or n.endswith('.log')]
shutil.copytree(backend, stage / 'quiz-backend', ignore=ignore)
app = stage / 'quiz-backend'
shutil.copytree(backend / 'bootstrap', app / 'bootstrap', ignore=shutil.ignore_patterns('cache'))
(app / 'bootstrap/cache').mkdir()
for name in ['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
    (app / name).mkdir(parents=True, exist_ok=True)
shutil.copytree(backend / 'vendor', app / 'vendor', ignore=shutil.ignore_patterns('.git'))
shutil.copytree(backend / 'public', stage / 'public_html', ignore=shutil.ignore_patterns('storage', 'hot'))
p = stage / 'public_html/index.php'
p.write_text(p.read_text().replace("__DIR__.'/../storage/", "__DIR__.'/../quiz-backend/storage/").replace("__DIR__.'/../vendor/", "__DIR__.'/../quiz-backend/vendor/").replace("__DIR__.'/../bootstrap/", "__DIR__.'/../quiz-backend/bootstrap/"))
shutil.copy2(root / 'docs/hostinger.md', stage / 'DEPLOYMENT.md')
shutil.copy2(root / 'docs/release-status.md', stage / 'RELEASE-STATUS.md')
print(stage)
