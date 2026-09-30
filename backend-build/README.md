# VisionQuantech — Backend + Database + Admin Panel

Plain-PHP backend for **visionquantech.com**. Shared-cPanel-hosting friendly:
**no framework, no composer, no Node, no build step.** Upload and run.

Live site context: static React SPA (hash routing `#/`, Tailwind) in `public_html/`.
This package adds the server side beside it: lead capture, research-post
management, basic analytics, and a private admin panel.

---

## What's inside

```
backend-build/
├── schema.sql                  MySQL schema (leads, admins, posts, page_views, settings)
├── api/
│   ├── config.php              DB connection (PDO), JSON helper, rate limiter.
│   │                           Reads creds from env vars or ../private/db.php
│   ├── lead.php                POST contact/pilot form → validates → stores lead
│   ├── posts.php               GET published research posts / updates (JSON)
│   ├── track.php               POST page-view ping → analytics table
│   └── .htaccess               Options -Indexes
├── admin/                      Private admin panel (session login, CSRF on all forms)
│   ├── login.php / logout.php
│   ├── index.php               Dashboard: lead stats, 14-day charts (Chart.js CDN),
│   │                           recent enquiries with status toggle
│   ├── leads.php               Filter/search, status workflow, delete
│   ├── posts.php / post_edit.php  Research-post CRUD (slug, category, scheduling)
│   ├── settings.php            Site settings (key/value)
│   ├── includes/               bootstrap.php, auth.php, layout.php
│   ├── assets/style.css        Hand-written responsive CSS
│   └── .htaccess               Options -Indexes
├── seo/
│   ├── robots.txt              Allow all + sitemap pointer, blocks /api/ /admin/
│   ├── sitemap.xml             Homepage now; /solutions/* planned (commented out)
│   └── jsonld.html             Organization + WebSite JSON-LD snippet for index.html
├── tools/
│   └── make_admin.php          ONE-TIME admin creator (CLI). Delete after use.
└── private/
    └── db.php.example          Template for DB credentials (lives OUTSIDE web root)
```

---

## Deployment (cPanel shared hosting)

### 1. Create the database
cPanel → **MySQL Databases**: create database (e.g. `visionqu_db`) and a user,
grant **ALL PRIVILEGES** on that DB to the user. Note the three values.

### 2. Import the schema
cPanel → **phpMyAdmin** → select the new database → **Import** →
upload `schema.sql` → Go. You should see 5 tables:
`leads`, `admins`, `posts`, `page_views`, `settings`.

### 3. Upload the code
Via cPanel **File Manager** (or FTP), into `public_html/` — **beside** the
React build's `index.html` (do not overwrite it):

| Local file/dir        | Upload to                        |
|-----------------------|----------------------------------|
| `api/`                | `public_html/api/`               |
| `admin/`              | `public_html/admin/`             |
| `seo/robots.txt`      | `public_html/robots.txt`         |
| `seo/sitemap.xml`     | `public_html/sitemap.xml`        |

### 4. Inject the JSON-LD snippet
Open `public_html/index.html`, paste the whole contents of `seo/jsonld.html`
just before `</head>`, save.

### 5. Store DB credentials OUTSIDE the web root
In File Manager go **one level above** `public_html/` (your home dir),
create folder `private/`, create file `private/db.php` with:

```php
<?php
return [
    'host' => 'localhost',
    'name' => 'visionqu_db',      // your DB name from step 1
    'user' => 'visionqu_dbuser',  // your DB user from step 1
    'pass' => 'YOUR_STRONG_PASSWORD',
];
```

`api/config.php` looks for `../private/db.php` relative to `public_html/`,
i.e. `/home/<user>/private/db.php`. (Env vars `DB_HOST/DB_NAME/DB_USER/DB_PASS`
work too and take precedence.)

### 6. Create the admin user (one time)
On any machine with PHP 8+ and MySQL network access:

```bash
DB_HOST=localhost DB_NAME=visionqu_db DB_USER=visionqu_dbuser DB_PASS=... \
  php tools/make_admin.php shivay
# you will be prompted for a password (min 12 chars, hidden input)
```

No SSH on the host? cPanel → **Terminal** often has PHP — run it there.
Last resort: temporarily upload `tools/make_admin.php` to
`public_html/admin/tools/`, set `$ALLOW_WEB_RUN = true` inside it, open it
**once** in the browser, create the user, then **DELETE the file immediately**.

### 7. Log in
Open `https://visionquantech.com/admin/` → sign in.
Dashboard shows lead stats, charts, recent enquiries.

---

## Wiring the React contact form

In the React app's contact/pilot form submit handler, POST JSON to the API
(include a hidden honeypot field named `website` — leave it empty):

```js
async function submitLead(data) {
  const res = await fetch('/api/lead.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: data.name,
      email: data.email,
      phone: data.phone || '',
      business_type: data.businessType || '', // real-estate|hotel|clinic|hospital|other
      message: data.message,
      source_page: window.location.hash || '/',
      website: '', // honeypot — must stay empty
    }),
  });
  const out = await res.json();
  if (!out.ok) {
    // out.errors = { field: message } on validation failure (HTTP 422)
    throw new Error(JSON.stringify(out.errors || out.error));
  }
  return out; // { ok: true, id }
}
```

`business_type` values accepted: `real-estate`, `hotel`, `clinic`, `hospital`,
`other` (or empty). Rate limit: 5 submissions/hour/IP. Bots filling the
`website` field get a fake success and nothing is stored.

## Page-view tracking (optional, one snippet)

Add to the React app (e.g. on route change):

```js
fetch('/api/track.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ path: window.location.pathname + window.location.hash }),
}).catch(() => {});
```

Failures are silent by design — analytics must never break the site.

## Research posts on the website (optional)

Published posts are available at `GET /api/posts.php` →
`{ ok: true, posts: [{ slug, title, category, excerpt, published_at }] }`,
single post via `?slug=...`. Manage them at `/admin/` → Posts.
Categories: `research`, `updates`. Drafts are never exposed publicly.

---

## Test checklist (after deploy)

- [ ] `POST https://visionquantech.com/api/lead.php` with valid JSON → `{"ok":true,"id":N}`
- [ ] Same with bad email → HTTP 422 with `errors.email`
- [ ] New lead appears in `/admin/` → Leads as **new**
- [ ] `GET /api/posts.php` → `{"ok":true,"posts":[]}` (empty until you publish)
- [ ] `/admin/` login works; wrong password shows generic error
- [ ] `https://visionquantech.com/robots.txt` and `/sitemap.xml` serve the new files
- [ ] View-source on homepage contains the JSON-LD block

## Security notes

- All DB access uses **PDO prepared statements**; all admin output is
  HTML-escaped; all admin POST forms carry **CSRF tokens**.
- DB credentials live **outside the web root** (`~/private/db.php`), never in git.
- `tools/make_admin.php` must be **deleted after the one-time use**.
- Passwords are `password_hash()` / `password_verify()` (bcrypt/argon2).
- `/api/` and `/admin/` are `Disallow`ed in robots.txt.
- Consider renaming `public_html/admin/` to something unguessable
  (e.g. `public_html/vq-panel-7x/`) for extra obscurity — update links accordingly.
- The site's hash routing (`#/`) means `/sitemap.xml` planned `/solutions/*`
  URLs stay **commented out** until those real pages exist. Don't submit 404s.
