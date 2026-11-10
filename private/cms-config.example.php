<?php
/**
 * Copy this file on the Simply.com server to:
 *   ~/private/cms-config.php
 *
 * It sits next to public_html, so deploys do not overwrite it.
 * github_token is a GitHub personal access token for andynylon/valbyguitarskole.
 * A fine-grained token needs Contents: Read and write on that repository only.
 * A classic token needs the repo scope. GitHub shows the token only once.
 */
return [
    'password' => 'choose-a-long-password',
    'github_token' => '',
    'github_repository' => 'andynylon/valbyguitarskole',
];
