<?php
session_start();
$_SESSION['hbmsuid'] = 1;
$_POST['submit'] = 1;
$_FILES['passport_image'] = [
    'name' => 'test.png',
    'type' => 'image/png',
    'tmp_name' => '/tmp/test.png',
    'error' => 0,
    'size' => 1000
];

require_once('html/kyc-process.php');
