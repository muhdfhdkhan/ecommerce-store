# Deploying Patches&Motifs on cPanel

The app is plain PHP 8.1+ with MySQL/MariaDB — **no Composer and no Node on the server**.
CSS and JS are pre-built and committed, so you only upload files, create a database and
run the web installer once.

> **Important:** the app builds every link from the site root (`/shop`, `/assets/...`).
> It must run at the **root of a domain or subdomain**, not inside `/subfolder`.
> `APP_URL` in `.env` must therefore be `https://example.com` or `https://shop.example.com`
> with no path.

---

## 1. Choose a layout

The codebase is designed so that `public/` is the only web-accessible folder; `.env`,
`storage/`, `config/` and the PHP sources sit above it.

### Layout A — recommended: point the document root at `public/`

```
/home/USER/patchesandmotifs/          <- project (not reachable from the web)
├── .env                              <- created by the installer (chmod 600)
├── includes/  modules/  admin/  config/  database/  storage/
└── public/                           <- DOCUMENT ROOT
    ├── index.php   install.php
    ├── assets/     uploads/
    └── .htaccess
```

In cPanel open **Domains** (some hosts call it **Domains → Manage** or **Addon Domains**)
and set the domain's **Document Root** to:

```
/home/USER/patchesandmotifs/public
```

- Works directly for **subdomains** and **addon domains**.
- If your host does not let you edit the main domain's document root, use a subdomain
  (e.g. `shop.example.com`) and redirect `example.com` → `shop.example.com`, or use Layout B.

### Layout B — fallback: keep everything in `public_html`

Use this only when the document root cannot be changed. The project lives inside
`public_html`, and a root `.htaccess` serves everything from `public/` and blocks the
private folders. This is slightly less safe than Layout A because secrets are physically
inside the web root — the deny rules below are what protect them.

```
public_html/
├── .htaccess          <- the snippet below (NEW)
├── .env               <- created by the installer (chmod 600)
├── includes/  modules/  admin/  config/  database/  storage/
└── public/
```

Create `public_html/.htaccess` with:

```apache
Options -Indexes -MultiViews

# Block dotfiles (.env, .git, .htpasswd, ...) and sensitive extensions
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "\.(env|ini|log|sql|bak|old|orig|swp|zip|tar|gz|7z|yml|yaml|lock|md|json)$">
    Require all denied
</FilesMatch>

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Never execute/serve the private folders directly
    RewriteRule ^(storage|config|includes|modules|database|resources|tests|scripts|docs)(/|$) - [F,L]

    # Serve the app from public/
    RewriteRule ^$ public/index.php [L]
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Requests for `/assets/...` resolve to `public/assets/...`, and app routes such as `/shop`
fall through `public/.htaccess` to `public/index.php`. `/.well-known/` is left alone, so
AutoSSL keeps working.

---

## 2. Create the database (cPanel → MySQL® Databases)

1. **Create New Database** — e.g. `patches` → cPanel prefixes it as `cpuser_patches`.
2. **Add New User** — e.g. `pm` → becomes `cpuser_pm` with a strong password.
3. **Add User To Database** → select the database and user → **ALL PRIVILEGES**.

Write down the full names (`cpuser_patches`, `cpuser_pm`) and the password — the installer
asks for them. Use **`localhost`** as the host on cPanel (it uses the local socket).

---

## 3. Upload the files

1. Create a zip of the project **without**: `.git/`, `node_modules/`, `.env`,
   `.env.bak-*`, `storage/installed.lock`, `storage/logs/*`, `storage/backups/*`.
   Keep the built `public/assets/css` and `public/assets/js`.
2. cPanel → **File Manager** → upload the zip → **Extract**.
3. Place it at `/home/USER/patchesandmotifs` (Layout A) or directly in `public_html`
   (Layout B).

Permissions (File Manager → Permissions):

| Path | Mode |
| --- | --- |
| Folders | `755` |
| Files | `644` |
| `storage/` and `public/uploads/` | `755` (must be writable by the PHP user) |
| `.env` (after install) | `600` |

Do **not** use `777`. cPanel typically runs PHP as your own user, so `755` is enough.

> If you deploy with cPanel **Git™ Version Control**, clone to
> `/home/USER/patchesandmotifs` and then set the document root to its `public/` folder.
> The repo already contains compiled assets — nothing to build on the server.

---

## 4. PHP settings

- **MultiPHP Manager** → select **PHP 8.1, 8.2 or 8.3** for the domain.
- **MultiPHP INI Editor** (or “Select PHP Version → Options”):
  - `upload_max_filesize = 8M`
  - `post_max_size = 10M`
  - `memory_limit = 256M`
  - `max_execution_time = 120` (the installer imports several SQL files)
- Required extensions (usually enabled; otherwise switch them on under
  **Select PHP Version → Extensions**):
  `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`, `openssl`, `json`.
- `mod_rewrite` and `AllowOverride All` are standard on cPanel — the shipped `.htaccess`
  files rely on them.

---

## 5. Run the installer

Open:

```
https://your-domain.example/install.php
```

Fill in:

| Field | Value |
| --- | --- |
| Store URL | `https://your-domain.example` (no trailing slash) |
| Database host | `localhost` |
| Database name | `cpuser_patches` |
| Database user | `cpuser_pm` |
| Database password | the one you created |
| Store name / email / phone / address | your real details |
| Timezone | `Asia/Karachi` |
| Admin name / email / password | your login for `/admin` (password 12–128 chars) |

The installer verifies PHP, extensions and permissions, then:

1. imports `database/schema.sql` + every `database/migrations/*.sql` in order,
2. seeds roles, permissions and your Super Admin,
3. applies the store identity to `store_settings`,
4. writes `.env` in the project root (chmod `600`) with
   `SESSION_SECURE_COOKIE=1` when the URL is HTTPS,
5. creates `storage/installed.lock`.

If you had to move an existing site, open `/install.php` again and use the
**reconfigure** form (it verifies the current Super Admin password first).
**Never delete `.env` or `storage/installed.lock` on a live site.**

---

## 6. Harden immediately after install

1. **Delete `public/install.php`** (Layout B: `public_html/public/install.php`).
   Keep `storage/installed.lock`.
2. Confirm `.env` is **not** reachable: open `https://your-domain.example/.env` — it must
   return 403/404, never the file contents.
3. Confirm `.env` permissions are `600`.
4. cPanel → **Domains** → enable **Force HTTPS Redirect** (or let AutoSSL issue the
   certificate) and check `.env` has `SESSION_SECURE_COOKIE=1`.
5. Never commit or upload `.env`; it contains the DB password.

---

## 7. Finish the store setup

In **Admin → Settings**:

- **Store identity** — name, contact email/phone, address (used across the site and emails).
- **Brand logo**, **header ticker**.
- **Social media & WhatsApp** — Facebook, Instagram, WhatsApp number (with country code).
- **Patch & motif sizing** — default 1–12 in.
- **PostEx** — API token/base if you use the courier integration.
- **Google Sign-In** — create an OAuth Web client in Google Cloud and add:

  ```
  https://your-domain.example/account/google/callback
  ```

  Paste the Client ID/secret in Settings; the button appears once a secret is stored.

**Email:** the app sends with PHP `mail()`. Create a mailbox such as
`no-reply@your-domain.example` in cPanel → **Email Accounts**, then add to `.env`:

```
MAIL_FROM_NAME=Patches & Motifs
MAIL_FROM_EMAIL=no-reply@your-domain.example
```

(Blank `MAIL_FROM_EMAIL` falls back to `no-reply@<your domain>`.) Enable SPF/DKIM under
**Email Deliverability** so order emails don't land in spam. Every attempt is recorded in
**Admin → Banners → Email log**.

**Test order:** add a product image, add an item to the cart, place a COD order and open the
order in Admin → Orders.

---

## 8. Post-deploy checklist

- [ ] `https://your-domain.example/` loads with styles (if unstyled, the document root is wrong)
- [ ] `/shop`, `/product/...`, `/cart`, `/checkout` all load
- [ ] `https://your-domain.example/admin/login` works with the installer admin
- [ ] `/.env` and `/storage/logs/php-error.log` are **not** downloadable
- [ ] Category/product image upload works (checks GD + upload folder)
- [ ] Test order placed; confirmation email logged
- [ ] `/install.php` deleted
- [ ] HTTPS forced; no mixed-content warnings
- [ ] Backup created (below)

---

## 9. Updating the site later

1. Locally: commit changes; run `npm run build:css` if styles changed (compiled CSS is
   committed).
2. On the server: **pull** via cPanel Git Version Control, or re-upload only the changed
   files. **Never overwrite `.env` or `storage/`.**
3. **New migrations** are *not* applied by the installer on an existing site (it never
   re-runs DDL over existing tables). Import the new file(s) manually:
   cPanel → **phpMyAdmin** → select the database → **Import** →
   `database/migrations/00X_*.sql`.
4. If the host has OPcache, clear it (cPanel → “PHP OPcache” or restart PHP for the domain).

---

## 10. Backups

- cPanel → **Backup** / **Backup Wizard** for a full account backup, or
- cPanel → **Cron Jobs** (daily 02:30), e.g.:

```bash
/usr/bin/mysqldump --single-transaction --quick -u cpuser_pm -p'DB_PASSWORD' cpuser_patches \
  | gzip > /home/USER/backups/db_$(date +\%F).sql.gz
```

Keep a copy off the server (download, or push to cloud storage). Uploaded images live in
`public/uploads/` — include them in file backups.

---

## 11. Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| “Application is not configured.” | `.env` missing from the project root (not `public/`). Re-run the installer. |
| 500 error, blank page | Read `storage/logs/php-error.log` (and cPanel → Errors). Disable any stale OPcache. |
| `/shop` 404s but `/` works | `mod_rewrite`/`.htaccess` not applied — check `AllowOverride`, or the document root is not `public/`. |
| Page loads without CSS/JS | Wrong document root, or files missing under `public/assets/`. |
| Installer: DB connection failed | Wrong prefix (`cpuser_`), user not added to the DB, or host should be `localhost`. |
| Installer stops on permissions | Make `storage/` and `public/uploads/` writable (`755`, owner = cPanel user), `.env` writable during install. |
| Image upload fails | `gd` extension off, or `public/uploads/` not writable, or file above `upload_max_filesize`. |
| Forms return “session expired” | `SESSION_SECURE_COOKIE=1` while browsing over HTTP (or vice versa). Match it to the URL scheme. |
| Redirect loop after enabling HTTPS | `APP_URL` still `http://…` — reconfigure via `/install.php`. |
| Emails not delivered | No mailbox/From mismatch, or SPF/DKIM missing. Check Admin → Banners → Email log and cPanel → Email Deliverability. |
| “Already installed” on a fresh server | You uploaded `storage/installed.lock` — delete it and reload `/install.php`. |
| 403 on every POST | ModSecurity rule on the host — ask support to whitelist the domain, or disable it for the vhost. |
