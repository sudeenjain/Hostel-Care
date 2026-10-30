<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post();
logout_user();
session_start();
flash('ok', 'You have been signed out.');
redirect('login.php');
