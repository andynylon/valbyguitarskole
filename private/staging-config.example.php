<?php
/**
 * Copy this file on the Simply.com server to:
 *   ~/private/staging-config.php
 *
 * Staging deploys to ~/staging.valbyguitarskole.dk/, next to public_html.
 * Point the subdomain's document root at that folder.
 * Deploys do not overwrite ~/private/.
 * The hash is not the password. Create it on the server:
 *   php -r "echo password_hash('a-long-password', PASSWORD_DEFAULT), PHP_EOL;"
 */
return [
    'username' => 'staging',
    'password_hash' => '',
];
