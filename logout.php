<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/auth.php';

wiki_logout();
header('Location: login.php');
exit;
