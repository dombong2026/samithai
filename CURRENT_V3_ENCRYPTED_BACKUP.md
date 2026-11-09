# Current production v3 encrypted backup

The initial local v2 backup predates the prompt review migration. For the Saved Prompts upgrade, a new v3 snapshot is necessary.

`support/current-v3-encrypted-backup.sh --check` reads the current v3 inventory and verifies that 14 tables and media integrity are healthy. `--snapshot` creates a root-restricted temporary snapshot of MySQL, `.env`, and `storage/app`; computes a SHA256 manifest; requires matching before-and-after v3 recovery inventories; prompts for a strong passphrase in the terminal; AES-256 encrypts, decrypts to verify, and checks the ciphertext SHA256. Temporary plaintext and MySQL options files are cleaned up automatically.

The output is a `.tar.gpg` file and matching `.sha256` file in `/root/SamithAI-Upgrade-Evidence`. The passphrase MUST be saved independently and never provided in chat or uploaded to GitHub.

The command does not alter MySQL rows, Laravel source, media, Nginx, payments or the site configuration. There is no offsite transfer or independent restoration. The MySQL dump and application storage are not globally atomic; run at low write activity, and stop if the source inventory changes. The local backup can be lost along with its host.

Only after this capture is successful should the additive `prompt_saves` migration and release be considered.
