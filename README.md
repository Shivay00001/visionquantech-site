# VisionQuantech Site

Marketing site + PHP backend for VisionQuantech — AI research positioning
with a services funnel (AI agents, websites, SEO, ads).

## Layout

- `backend-build/api/` — PHP API endpoints (leads, posts, tracking, demo chat)
- `backend-build/admin/` — admin panel (leads, posts, settings, auth)
- `backend-build/seo/` — robots.txt, sitemap, JSON-LD
- `backend-build/private/` — `db.php.example` / `llm.php.example` config templates
- `backend-build/schema.sql` — database schema
- `live-patch/` — current live homepage (`index.html` + bundled `app.js`)

## Setup (backend)

1. Copy `backend-build/private/db.php.example` → `db.php` and fill in DB credentials.
2. Copy `backend-build/private/llm.php.example` → `llm.php` and fill in the LLM key.
3. Import `backend-build/schema.sql` into MySQL.
4. Serve over PHP 8+.

Secrets live in the private config files (git-ignored); never commit them.
