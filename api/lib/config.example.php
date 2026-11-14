<?php
// Copy to config.php (same folder, or the private dir in bootstrap.php) and fill in. Never commit config.php.
return [
    'db_dsn'      => 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=biohack;charset=utf8mb4',
    'db_user'     => 'biohack',
    'db_pass'     => '',
    'site_origin' => 'https://edinburghbiohackathon.bio.ed.ac.uk',
    'notify_to'   => 'biohackathon@ed.ac.uk',
    'mail_from'   => 'Edinburgh BioHackathon <biohackathon@ed.ac.uk>',
    'reply_to'    => 'biohackathon@ed.ac.uk',
    'ip_salt'     => '',   // long random string
];
