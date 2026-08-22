<?php
/**
 * Run this from the command line to generate a password hash for config.php:
 *   php generate_hash.php yourpassword
 *
 * Copy the output into WIKI_USERS in config.php, then delete this file
 * (or at least keep it out of your public web root) once you're done.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line, not a browser.');
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php generate_hash.php <password>\n");
    exit(1);
}

echo password_hash($argv[1], PASSWORD_DEFAULT) . PHP_EOL;
