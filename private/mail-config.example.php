<?php
/**
 * Copy this file on the Simply.com server to:
 *   ~/private/mail-config.php
 *
 * That path sits next to public_html, so deploys do not overwrite it
 * and the password is not reachable from the website.
 * The username and password are for a mailbox in Simply.com Mail Administration,
 * such as hej@valbyguitarskole.dk. websmtp.simply.com only accepts mail
 * sent from Simply.com's own web servers.
 */
return [
    'host' => 'websmtp.simply.com',
    'port' => 587,
    'username' => 'hej@valbyguitarskole.dk',
    'password' => 'mailbox-password',
    'from' => 'hej@valbyguitarskole.dk',
    'to' => 'hej@valbyguitarskole.dk',
];
