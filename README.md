# PHP Markdown Wiki (read-only, sign-in required)

A tiny, dependency-free wiki that renders `.md` files from a folder tree.
No database — content lives entirely on disk.

## Structure

```
wiki/
  config.php           <- users, site title, settings
  generate_hash.php     <- CLI helper to create password hashes
  index.php             <- main router (article view + search)
  login.php / logout.php
  includes/
    auth.php
    functions.php       <- tree building, search
    markdown.php        <- markdown -> HTML converter
    .htaccess            <- blocks direct access
  views/
    login.php
    wiki.php
  assets/
    style.css            <- dark + magenta theme
  content/               <- YOUR WIKI CONTENT GOES HERE
    .htaccess             <- blocks direct access to raw .md files
    General/
      Welcome.md
    Networking/
      Zabbix-Notes.md
      Servers/
        DL380-Gen9.md
```

Just create folders and `.md` files under `content/` — they appear in the
sidebar automatically, nested to any depth. No build step, no re-indexing.

## Setup

1. **Requirements**: PHP 7.4+ with the `session` extension (bundled by default).
   No database needed.

2. **Set a password.** From the command line:

   ```bash
   php generate_hash.php "your-strong-password"
   ```

   Copy the output hash into `config.php`:

   ```php
   define('WIKI_USERS', [
       'admin' => '$2y$10$....your generated hash....',
   ]);
   ```

   You can add as many users as you like — just add more array entries.
   Delete `generate_hash.php` from the web root afterward (or keep it
   outside the document root) since it's only needed once per password.

3. **Serve it.** Point your web server's document root at this folder
   (Apache or nginx). If using Apache, the included `.htaccess` files
   already block direct access to `includes/` and `content/` so nobody
   can browse to a raw `.md` file or PHP internals directly — everything
   must go through `index.php`, which checks the login session first.

   If you're on **nginx**, add rules to deny access to those two
   directories (nginx doesn't read `.htaccess`), e.g.:

   ```nginx
   location ~ ^/(includes|content)/ {
       deny all;
   }
   ```

4. **Enable HTTPS** in production and uncomment the `'secure' => true`
   line in `includes/auth.php`'s session cookie params.

5. Visit the site, sign in, and you're in.

## Adding content

Just drop new `.md` files/folders anywhere inside `content/`:

```
content/
  Infrastructure/
    Zabbix/
      Overview.md
      Alerts.md
  Runbooks/
    Backup-Procedure.md
```

Standard Markdown is supported: headings, bold/italic, inline code,
fenced code blocks, blockquotes, ordered/unordered lists, links, images,
horizontal rules, and simple pipe tables.

## Search

The search box does a case-insensitive full-text scan of every `.md`
file's title and contents, with title matches ranked first and a short
snippet shown for context. It's intentionally simple (no external index)
since this is meant for read-only, moderate-sized wikis — for very large
content sets you may want to swap it out for a proper search index.

## Security notes

- This app is **read-only by design** — there is no code path anywhere
  that writes to `content/`, so a compromised session can't modify the wiki.
- All page paths are resolved with `realpath()` and checked against the
  content directory to block `../` traversal.
- Passwords are stored as bcrypt hashes (`password_hash`/`password_verify`),
  never plaintext.
- Sessions use `httponly` cookies with a configurable lifetime.
