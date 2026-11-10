<?php
// Rename to darts-config.php (optional).
return [
    // The installer (install.php) creates this file for you; by hand you only need to edit it.
    // 'club'  => 'Springfield Darts Club',  // shown at the top left of the start screen
    // 'color' => '#16a34a',                 // main colour (a darker one is recommended)
    // 'logo'  => 'logo.png',                // optional: file name of an uploaded image, or an https URL

    // ABSOLUTE path of the data folder, preferably OUTSIDE the web root (public_html / www). If omitted, it is searched automatically.
    // 'data_dir' => '/home/USER/darts-data',

    // Administrators (moderation: reports, suspension, deletion). IMPORTANT: register the name in the app first,
    // and only then add it here – a name on this list cannot be claimed by a new registration.
    // 'admins' => ['admin'],

    // Storage: 'auto' (default: SQLite if the hosting has pdo_sqlite, otherwise JSON files), 'sqlite' or 'files'.
    // When switching from JSON files to SQLite the old files are migrated into the database automatically (once), then deleted.
    // 'storage' => 'auto',

    // Updates from the admin page (Update tab): GitHub repository with releases that contain GozieDarts.zip.
    // 'update_repo' => 'gozie-lab/GozieDarts',
    // 'update'      => false,   // disable the update feature / outgoing GitHub requests
];
