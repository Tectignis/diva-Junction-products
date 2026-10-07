# diva-Junction-products

Diva Junction microsite (divadeals.in) — pure PHP 8.2 + SQLite, no frameworks.

- **Landing page** (`index.php`) — the station poster from the 1080 px reference design in `docs/`.
- **Deals page** (`shop.php`) — laid out like the Flipkart "GenZ" store: the Diva Junction header banner, a swipeable **Featured** row, a **Women** grid of category tiles, the "explore more upcoming deals" banner and the disclaimer. Content comes from the client's *Diva Jn Deals Store Wireframe* sheet.
- **Location lock** — when switched on, the public pages open only for visitors within a set radius (default 500 m) of the activation. The admin panel works from anywhere.

## Run

1. Start Apache in XAMPP. The `pdo_sqlite` and `fileinfo` extensions are already enabled, and `mod_rewrite` is needed for the `api/…` URLs.
2. Open http://localhost/diva/ (landing page) or http://localhost/diva/shop.php (deals page).
3. Sign in to the admin at http://localhost/diva/admin/.
   - The first-run login is set in `config.php` (`DEFAULT_ADMIN_USER` / `DEFAULT_ADMIN_PASS`).
   - Change it under **Admin → Account**.

The database `data/diva.sqlite` is created and filled with the client's content on the first request. Older databases are upgraded automatically (the old Featured/Brands/Products tables are left in place but no longer used). Delete the file to reset everything.

## Admin

| Section       | What you can edit |
|---------------|-------------------|
| Dashboard     | Location lock status, location checks in the last 24 h, live sections and tiles |
| Site content  | Landing texts, header heading/button/artwork, bottom banner texts or artwork, disclaimer, optional countdown, location-screen texts and support link, logo, corner icon |
| Sections      | Rows on the deals page: title, layout (swipeable row or grid). Reorder, hide or delete (deleting a section also removes its tiles) |
| Tiles         | 424 × 640 tiles: section, title, hashtag line, photo, link. Reorder, hide or delete |
| Location lock | ON/OFF switch, location name, Google Maps link → coordinates, latitude/longitude on a map, radius, GPS accuracy needed, re-check period, log retention; a tool to test any coordinate |
| Logs          | Every location check (allowed/blocked, distance, accuracy, rounded position, device) and every admin change with old → new values |
| Account       | Username and password |

## Location lock

How it works:

1. A visitor opens a public page. If the lock is OFF, the page is served as usual.
2. If it is ON and this browser has no valid approval, the server sends the branded **Location Access Required** screen instead of the page (HTTP 403). Nothing of the page is sent, so turning off JavaScript or editing the page does not help.
3. The visitor taps **Enable Location**. The browser asks for permission, and the page sends latitude, longitude and accuracy to `POST api/geofence/validate`.
4. The server measures the distance to the configured centre (Haversine) and decides:
   - allowed when the distance is ≤ the radius and the GPS accuracy is within the limit;
   - `poor_accuracy` when the fix is less precise than the limit (the visitor is asked to retry) — unless it is outside the radius even allowing for that error, which is `outside_radius`.
5. On success the approval is stored in the visitor's server-side session for the re-check period (default 30 min) and the page reloads. Any saved change to the lock settings invalidates all approvals at once.

Other behaviour:

- Signed-in admins can preview the public pages from anywhere; a notice at the bottom says so.
- If the settings cannot be read (e.g. the database is unavailable), public pages show a "back in a moment" page (HTTP 503) — never the open site.
- The visitor screen handles: permission denied (with Android/iPhone steps), GPS off, timeout, poor accuracy, outside the area, too many attempts, non-HTTPS pages and old browsers. Every problem screen has **Try Again** and **Contact Support**.
- Location checks are rate-limited: 10 per browser session and 150 per IP address per 10 minutes (`config.php`).
- Privacy: coordinates are stored rounded to 4 decimals (~11 m), IPs only as a keyed hash, and records are deleted after the retention period (default 30 days).

To configure: **Admin → Location lock** → paste the client's Google Maps link and click **Find coordinates** (short `maps.app.goo.gl` links are followed on the server), check the pin and the circle on the map, set the radius, switch ON, **Save**.

## API

| Method | URL | Purpose | Auth |
|--------|-----|---------|------|
| GET  | `api/geofence/status` | Lock state, radius and accuracy limit; whether this visitor is approved | Public |
| POST | `api/geofence/validate` | `{"latitude", "longitude", "accuracy"}` → `{"allowed", "distance_meters", "radius_meters"}` or `{"allowed": false, "reason": …}` | Public, JSON only, rate-limited (429) |
| GET  | `api/admin/geofence` | Current settings | Admin session (401 otherwise) |
| PUT  | `api/admin/geofence` | Update any settings fields; send the `X-CSRF-Token` header (from the admin pages' `csrf-token` meta tag) | Admin session + CSRF (403 otherwise) |
| GET  | `api/admin/geofence/logs?limit=100` | Recent access and audit logs | Admin session |

Without `mod_rewrite`, use `api/index.php?route=geofence/status`.

## Tests

```
php tests/run.php                                  # distance, decision, settings, approvals, logging, rate limits
php tests/run.php --http=http://localhost/diva/    # + checks against the running site (401s, 415, 405, protected files)
```

## Deployment (divadeals.in)

1. Upload everything except `data/*.sqlite`; make `data/` and `uploads/` writable by PHP.
2. **HTTPS is required** — browsers share GPS only with secure pages. Uncomment the redirect in `.htaccess` if the host does not redirect already.
3. Open the site once (creates the database), sign in, change the admin password.
4. Configure **Location lock** with the client's Google Maps location, then test from inside and outside the radius (phone Chrome and Safari) before switching it ON for launch.
5. If a CDN or page cache sits in front of the site, do not cache HTML pages while the lock is ON (they are sent with `Cache-Control: no-store`).

## Structure

```
index.php, shop.php     public pages (both call geofence_gate() first)
api/                    JSON API (router + rewrite rules)
admin/                  admin panel (login, CRUD pages, location lock, logs)
inc/                    db + migrations/seed, helpers, auth, geofence, views   (web access denied)
assets/css|js|img|fonts site and admin styles, scripts, artwork, self-hosted Inter
assets/vendor/leaflet   map on the Location lock page (OpenStreetMap tiles)
uploads/                tile photos and images uploaded in the admin                (scripts never executed)
data/                   SQLite database                                            (web access denied, git-ignored)
tests/                  automated checks                                           (web access denied)
```

## Design notes

- The deals page follows the Flipkart store grid: 424 × 640 tiles (photo on top, caption bar with the title, hashtag and arrow). Featured is a swipeable row with arrows on desktop; grids show 2 columns on phones, 3 on tablets and 4 on desktop.
- Tile photos are only the photo part — the caption is HTML, so titles stay editable. The client's reuse tiles were cropped to their photo; product photos from the sheet's Flipkart links were fitted to the same ratio.
- The header uses the microsite artwork: stacked logo + card on phones, a 1440 × 480 banner (logo left, card right) from 800 px up.
- The landing page uses design units: `--u` = 1/1080 of the poster width, so it scales exactly like the mockup.
