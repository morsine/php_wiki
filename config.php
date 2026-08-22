<?php
/**
 * Wiki configuration
 * Change these values before deploying.
 */

// Root directory where your .md content lives (folder/file.md structure)
define('WIKI_CONTENT_DIR', __DIR__ . '/content');

// Site title
define('WIKI_TITLE', 'Syntrix Wiki');

// Session name / cookie
define('WIKI_SESSION_NAME', 'wiki_session');

// How long a login lasts (seconds) - 8 hours
define('WIKI_SESSION_LIFETIME', 8 * 60 * 60);

/**
 * Users allowed to sign in.
 * Generate a password hash with:
 *   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT), PHP_EOL;"
 * and paste the result below. Never store plaintext passwords.
 */
define('WIKI_USERS', [
    // REPLACE this hash before deploying. Run generate_hash.php (included) or:
    //   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT), PHP_EOL;"
    'admin' => 'REPLACE_WITH_GENERATED_HASH',
]);
