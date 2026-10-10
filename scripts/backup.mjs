import { spawnSync } from "node:child_process";
import {
  randomBytes,
  scryptSync,
  createCipheriv,
  createDecipheriv,
} from "node:crypto";
import { mkdirSync, writeFileSync, readFileSync } from "node:fs";
import { join } from "node:path";
const passphrase = process.env.QUIZ_BACKUP_PASSPHRASE;
if (!passphrase || passphrase.length < 16)
  throw new Error(
    "Inject a strong QUIZ_BACKUP_PASSPHRASE securely; never pass it as an argument.",
  );
const magic = Buffer.from("QUIZBACKUP1");
if (process.argv[2] === "decrypt") {
  const input = readFileSync(process.argv[3]);
  const n = magic.length;
  if (input.length < n + 60 || !input.subarray(0, n).equals(magic))
    throw new Error("Unknown backup format.");
  const salt = input.subarray(n, n + 32),
    iv = input.subarray(n + 32, n + 44),
    tag = input.subarray(n + 44, n + 60);
  const decipher = createDecipheriv(
    "aes-256-gcm",
    scryptSync(passphrase, salt, 32),
    iv,
  );
  decipher.setAAD(input.subarray(0, n + 44));
  decipher.setAuthTag(tag);
  const plaintext = Buffer.concat([
    decipher.update(input.subarray(n + 60)),
    decipher.final(),
  ]);
  writeFileSync(process.argv[4], plaintext, { flag: "wx", mode: 0o600 });
  console.log(
    "Backup authenticated and decrypted to the requested private file.",
  );
} else {
  const result = spawnSync(
    "docker",
    [
      "compose",
      "exec",
      "-T",
      "db",
      "sh",
      "-c",
      'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --host=127.0.0.1 --user=quiz --single-transaction --set-gtid-purged=OFF --no-tablespaces --triggers quiz',
    ],
    { maxBuffer: 256 * 1024 * 1024 },
  );
  if (result.error || result.status !== 0)
    throw new Error("Database dump failed; no backup written.");
  const salt = randomBytes(32),
    iv = randomBytes(12),
    header = Buffer.concat([magic, salt, iv]);
  const cipher = createCipheriv(
    "aes-256-gcm",
    scryptSync(passphrase, salt, 32),
    iv,
  );
  cipher.setAAD(header);
  const ciphertext = Buffer.concat([
    cipher.update(result.stdout),
    cipher.final(),
  ]);
  const directory = process.argv[2] ?? "/tmp/quiz-backups";
  mkdirSync(directory, { recursive: true, mode: 0o700 });
  const path = join(
    directory,
    "quiz-" + new Date().toISOString().replace(/[:.]/g, "-") + ".sql.enc",
  );
  writeFileSync(
    path,
    Buffer.concat([header, cipher.getAuthTag(), ciphertext]),
    { flag: "wx", mode: 0o600 },
  );
  console.log("Authenticated encrypted backup saved:", path);
}
