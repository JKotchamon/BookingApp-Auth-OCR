<?php
session_start();
error_reporting(0);

// User declined to link their OAuth account to their existing local account.
// Drop the pending state and send them back to sign-in with a friendly note.
unset($_SESSION['pending_link']);

header('Location: signin.php?msg=link_cancelled');
exit;
