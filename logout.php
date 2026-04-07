<?php
require_once 'config/init.php';

$auth = new Auth($db);
$result = $auth->logout();

redirect('login.php');
