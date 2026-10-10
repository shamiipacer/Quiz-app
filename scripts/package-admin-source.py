#!/usr/bin/env python3
from pathlib import Path
import os, zipfile
root = Path(__file__).resolve().parents[1]
backend = root / 'backend'
(root / 'dist').mkdir(exist_ok=True)
with zipfile.ZipFile(root / 'dist/admin-backend-source.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for current, dirs, files in os.walk(backend):
        rel = Path(current).relative_to(backend)
        dirs[:] = [d for d in dirs if d not in {'vendor','node_modules','.git','.phpunit.cache'} and not (rel == Path('.') and d == 'storage') and not (rel == Path('public') and d == 'storage')]
        for name in files:
            p = Path(current) / name
            if name in {'auth.json','.phpunit.result.cache','.DS_Store'} or name.endswith(('.log','.sqlite')): continue
            if name.startswith('.env') and not name.endswith('.example'): continue
            if rel == Path('bootstrap/cache') and name != '.gitignore': continue
            if p.is_symlink(): continue
            z.write(p, Path('backend') / p.relative_to(backend))
    for directory in ['storage/app/public','storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache']:
        z.writestr('backend/' + directory + '/', '')
    for name in ['hostinger.md','setup.md','deployment.md','admin.md','api.md','architecture.md','release-status.md','privacy-template.md','terms-template.md']:
        z.write(root / 'docs' / name, Path('docs') / name)
    for name in ['backend.Dockerfile','compose.yaml','.env.example','.dockerignore']:
        z.write(root / name, name)
    for name in ['smoke.py','verification-url.php','concurrency.py','concurrent-answer.php','backup.mjs','verify-backup.py']:
        if (root / 'scripts' / name).is_file(): z.write(root / 'scripts' / name, Path('scripts') / name)
    z.writestr('README.md', '# Laravel admin panel and REST API source\n\nThe admin panel is part of the Laravel backend and uses the same database and authorization system. Source is in backend/, including Blade views, controllers, routes, migrations, seeders and tests. No credentials or vendor dependencies are included.\n\nFor the prepared Hostinger upload package with production dependencies, use hostinger.zip and docs/hostinger.md. For local source installation, install PHP 8.4 and Composer, enter backend/ and run composer install, copy .env.example to .env, configure your development database and run php artisan key:generate, php artisan migrate and php artisan quiz:seed-initial. Create your admin with php artisan admin:create EMAIL NAME. See docs/setup.md for full setup and test instructions. Docker backend configuration is included. Extract mobile-source.zip into the same parent folder when following instructions for both components.\n')
print('Admin/backend source archive created')
