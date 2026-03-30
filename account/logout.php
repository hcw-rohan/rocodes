<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

client_area_logout();
client_area_set_flash('success', 'You have been logged out.');
client_area_redirect('/account/');
