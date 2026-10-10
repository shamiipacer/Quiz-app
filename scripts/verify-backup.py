"""Create/authenticate/tamper-check a backup and restore to quiz_restore, never the app DB."""
import os,pathlib,secrets,subprocess,tempfile
with tempfile.TemporaryDirectory(prefix='quiz-backup-check-') as directory:
 env=os.environ.copy();env['QUIZ_BACKUP_PASSPHRASE']=secrets.token_hex(32)
 subprocess.run(['node','scripts/backup.mjs',directory],env=env,check=True,capture_output=True)
 encrypted=next(pathlib.Path(directory).glob('*.sql.enc'));sql=pathlib.Path(directory)/'verified.sql'
 subprocess.run(['node','scripts/backup.mjs','decrypt',str(encrypted),str(sql)],env=env,check=True,capture_output=True)
 tampered=pathlib.Path(directory)/'tampered.enc';data=bytearray(encrypted.read_bytes());data[-1]^=1;tampered.write_bytes(data)
 bad=pathlib.Path(directory)/'invalid.sql';r=subprocess.run(['node','scripts/backup.mjs','decrypt',str(tampered),str(bad)],env=env,capture_output=True)
 assert r.returncode!=0 and not bad.exists()
 base=['docker','compose','exec','-T','db','sh','-c']
 subprocess.run(base+['MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root -e "CREATE DATABASE IF NOT EXISTS quiz_restore"'],check=True,capture_output=True)
 with sql.open('rb') as source:
  subprocess.run(base+['MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root quiz_restore'],stdin=source,check=True,capture_output=True)
 query='SELECT (SELECT COUNT(*) FROM quiz.categories)=(SELECT COUNT(*) FROM quiz_restore.categories), (SELECT COUNT(*) FROM quiz.users)=(SELECT COUNT(*) FROM quiz_restore.users), (SELECT COUNT(*) FROM quiz.coin_transactions)=(SELECT COUNT(*) FROM quiz_restore.coin_transactions);'
 result=subprocess.run(base+['MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root -N -e '+"'"+query+"'"],check=True,capture_output=True,text=True)
 assert result.stdout.strip()=='1\t1\t1'
 subprocess.run(base+['MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root -e "DROP DATABASE quiz_restore"'],check=True,capture_output=True)
 print('PASS: encrypted backup authenticated, tampering rejected, isolated MySQL restore matched category/user/ledger counts.')
