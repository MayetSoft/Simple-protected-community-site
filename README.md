# Simple Protected Community Site

**English** · [Français](README.fr.md)

A minimal PHP app to publish a single information page protected by a
shared password: no user accounts, no database. Runs on any Apache + PHP
shared hosting (cPanel, o2switch…).

Typical use: a residents' or co-owners' board, an association, a club —
any community that wants to share meeting notes, announcements or useful
contacts without making them public.

> The user interface is in French. Contributions adding translations are
> welcome.

## Features

- One content page, protected by a **shared access code**.
- An admin page with a rich text editor (TinyMCE) and embedded images.
- Maintenance mode (page unavailable) and read-only mode.
- No database: everything is stored in files.

## Requirements

- PHP ≥ 7.4 (`mbstring` extension recommended).
- Apache ≥ 2.4 with `mod_rewrite` and `.htaccess` overrides allowed.
- An HTTPS certificate (Let's Encrypt is fine).

## Files

```
site/
├── index.php      User page: shared access code, content display
├── admin.php      Admin: first-run setup, TinyMCE editor, settings
├── common.php     Shared helpers and settings (not web-accessible)
├── .htaccess      Protects data files, forces HTTPS
└── robots.txt     Disallows search engine indexing
```

Files created at runtime (excluded from the repository by `.gitignore`):

```
├── config.json    Password hashes, options
├── content.html   Published content (served only through index.php)
└── throttle.json  Login attempt counter
```

## Built-in security

- No default password: the first visit to `admin.php` forces you to choose
  both passwords (admin ≥ 10 characters, user ≥ 8 characters, confirmed).
- Passwords stored hashed (bcrypt via `password_hash`).
- `content.html` and `config.json` are **not directly reachable**: content
  is only served by `index.php` after the password is entered.
- CSRF tokens on every form.
- Login throttling: 5 failures per IP → 15-minute lockout.
- Session cookies are `HttpOnly`, `SameSite=Lax`, `Secure` (over HTTPS).
- `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` headers.

## Deployment (example: cPanel)

1. **Upload** the `site/` folder into `public_html/`. You can rename it
   (e.g. `info/`): that name will appear in the URL.
2. **Rename `admin.php`** to something hard to guess, e.g.
   `manage-3fA9kP.php`. The code follows the new name automatically.
3. **Enable HTTPS** (Let's Encrypt) in cPanel. The `.htaccess` then
   redirects all traffic to HTTPS.
4. **Immediately open** the admin page to run the setup (choose the
   passwords). ⚠️ Until setup is done, the first visitor who finds the
   admin page can choose the passwords: do not leave an uninitialised
   site online.
5. Check that `https://yoursite/site/content.html` and
   `https://yoursite/site/config.json` return **403 Forbidden**.

## Customisation

Settings live at the top of `common.php`:

```php
define('SITE_TITLE', "Espace d'informations"); // displayed title
define('ADMIN_PWD_MIN', 10);                   // admin password min length
define('USER_PWD_MIN', 8);                     // user password min length
```

### Option: data outside the web root

By default, data files (`config.json`, `content.html`, `throttle.json`)
live in the app folder, protected by `.htaccess`. For protection that does
not depend on `.htaccess`, edit `common.php`:

```php
define('DATA_DIR', '/home/YOURLOGIN/site_data');
```

Create that folder first (outside `public_html/`), writable by PHP.

## Known limitations

- TinyMCE is loaded from a CDN (pinned version). The admin page needs
  Internet access, and there is no Subresource Integrity hash because
  TinyMCE dynamically loads further resources from the same CDN.
- The user password is shared by all members: it keeps out the curious,
  not a member who leaks it. Do not publish sensitive data (the tool is
  meant for meeting notes, announcements, useful contacts…).
- Images are embedded as Base64 in the content: avoid large images
  (2 MB per image limit in the editor).

## Security

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE)
