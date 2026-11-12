<?php
/**
 * Copy this file on the Simply.com server to:
 *   ~/private/db-config.php
 *
 * It sits next to public_html, so deploys do not overwrite it
 * and the password is not reachable from the website.
 *
 * In the Simply control panel, create one MySQL database and leave
 * external access turned off. Copy the host, database name, user and
 * password from that panel into this file. Then open phpMyAdmin and
 * run private/enquiries.sql from this project.
 */
return [
    'host' => 'mysql.simply.com',
    'port' => 3306,
    'database' => 'database-name',
    'username' => 'database-user',
    'password' => 'database-password',
];
