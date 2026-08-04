<?php
require_once __DIR__ . '/../app/bootstrap.php';
$code = file_get_contents('php://stdin');
if (!empty($code)) {
    eval($code);
}
