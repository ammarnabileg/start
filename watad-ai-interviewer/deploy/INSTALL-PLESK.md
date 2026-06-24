# Fresh install on Plesk — Watad AI Interviewer

A clean from-scratch install. No npm/Vite build is needed: the UI loads Tailwind
and Alpine from CDN. You only need PHP (8.3) + Composer + MySQL.

Server path used below:
`/var/www/vhosts/infallible-aryabhata.198-251-67-150.plesk.page/httpdocs`
PHP CLI: `/opt/plesk/php/8.3/bin/php`

---

## 0. Wipe the old install
In **Plesk → Files**, delete everything inside `httpdocs/` (or back it up first).
In **Plesk → Databases**, drop the old DB or create a new empty one.

## 1. Get the code
**Plesk → Git** → point it at the repo + branch `claude/inspiring-cori-1gwl7h`,
deploy path `httpdocs`. Pull. (Or upload the project files into `httpdocs/`.)

## 2. Create the database
**Plesk → Databases → Add Database**. Note the DB name, user, password.

## 3. Install PHP dependencies
**Plesk → PHP Composer** on the domain → **Install**.
Or via SSH:
```bash
cd /var/www/vhosts/infallible-aryabhata.198-251-67-150.plesk.page/httpdocs
/opt/plesk/php/8.3/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader
```

## 4. Configure the environment
```bash
cp .env.plesk .env
/opt/plesk/php/8.3/bin/php artisan key:generate
```
Then edit `.env` and fill:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (from step 2)
- `OPENAI_API_KEY` (your key)
- `APP_URL` (already set to your domain — change if different)

## 5. Create the schema + seed
```bash
/opt/plesk/php/8.3/bin/php artisan migrate:fresh --seed
```
This creates all tables, roles/permissions, avatars, pipeline, and a login:
- **HR admin:** `admin@watad.com` / `password`  → change this immediately (Users page).
- Demo candidate: `candidate@watad.com` / `password` (`/portal/login`).

> Want a clean board with no demo data? After logging in, archive the demo jobs,
> or run only the essential seeders instead of `--seed`:
> `artisan migrate:fresh && artisan db:seed --class=RolePermissionSeeder && artisan db:seed --class=SettingsSeeder && artisan db:seed --class=AvatarSeeder && artisan db:seed --class=PipelineSeeder`
> (then create your admin via tinker or temporarily run DemoSeeder).

## 6. Storage symlink + permissions
```bash
/opt/plesk/php/8.3/bin/php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

## 7. Point the web root at Laravel's public/
**Best:** Plesk → **Hosting & DNS / Hosting Settings** → set **Document Root** to
`httpdocs/public`. Done — Laravel's own `public/.htaccess` handles routing.

**Fallback** (if you can't change the Document Root): copy `deploy/httpdocs.htaccess`
to `httpdocs/.htaccess`.

## 8. Cron — REQUIRED for AI analysis
**Plesk → Scheduled Tasks → Add Task**, run every **1 minute**:
```
/opt/plesk/php/8.3/bin/php /var/www/vhosts/infallible-aryabhata.198-251-67-150.plesk.page/httpdocs/artisan schedule:run
```
This drives the queue (scoring/reports/notifications), closes abandoned
interviews, expires invitations, and the GDPR purge. Without it, interview
analysis stays "pending" forever.

## 9. PHP settings
**Plesk → PHP Settings:** `upload_max_filesize = 16M`, `post_max_size = 16M`,
`memory_limit = 256M`.

## 10. Finalize
```bash
/opt/plesk/php/8.3/bin/php artisan config:clear
/opt/plesk/php/8.3/bin/php artisan route:clear
/opt/plesk/php/8.3/bin/php artisan view:clear
```
Open the site → log in as `admin@watad.com` → **change the password** on the Users
page → create a Job → generate an interview invitation link → test an interview.

---

## Smoke test checklist
- [ ] `/login` loads styled (Tailwind CDN working)
- [ ] Login as admin works; demo login is rejected after you deactivate it
- [ ] Create a Job → Generate invitation link
- [ ] Open the link → upload a CV (PDF) → interview room loads
- [ ] AI interviewer responds; progress shows `Question X / N` + timer
- [ ] End interview → completion screen
- [ ] Within ~1–2 min the cron finalizes → Decision Center shows scores
- [ ] Pipeline board drag & drop moves a candidate
