<?php session_start(); session_unset(); session_destroy();
header('Location: caretaker_login.php'); exit;