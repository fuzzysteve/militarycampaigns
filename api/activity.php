<?php
session_start();
require_once __DIR__.'/../includes/auth_functions.php';

header('Content-Type: application/json');

$activity = mc_get_my_activity();
if ($activity === null) {
    echo json_encode(array('loggedIn' => false));
    exit;
}

if ($activity) {
    require_once __DIR__.'/../includes/db.inc.php';
    require_once __DIR__.'/../includes/functions.php';
    $activity = enrichActivityWithStatus($dbh, $activity);
}

echo json_encode(array(
    'loggedIn' => true,
    'characterID' => $_SESSION['mc_character_id'],
    'characterName' => $_SESSION['mc_character_name'],
    'objectives' => (object)$activity,
));
