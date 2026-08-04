<?php
session_start();
require_once __DIR__.'/includes/secret.php';

$authsite = 'https://login.eveonline.com';
$authurl = '/v2/oauth/authorize';
$redirectUri = 'https%3A%2F%2Fwww.fuzzwork.co.uk%2Fmilitarycampaigns%2Fauth.php';
$state = uniqid();

$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (preg_match('#^https://www\.fuzzwork\.co\.uk/militarycampaigns/#', $referer)) {
    $_SESSION['mc_auth_redirect'] = $referer;
} else {
    $_SESSION['mc_auth_redirect'] = 'https://www.fuzzwork.co.uk/militarycampaigns/';
}
$_SESSION['mc_auth_state'] = $state;
session_write_close();

header(
    'Location:'.$authsite.$authurl
    .'?response_type=code&redirect_uri='.$redirectUri
    .'&client_id='.$mc_client_id
    .'&scope='.rawurlencode('esi.activity.char:read')
    .'&state='.$state
);
exit;
