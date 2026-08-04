<?php
// OAuth callback for this app's own EVE SSO login (esi.activity.char:read).
// Registered callback URL - must stay at exactly this path.
session_start();
require_once __DIR__.'/includes/auth_functions.php';

if (isset($_SESSION['mc_auth_state']) && isset($_GET['state']) && isset($_GET['code'])
    && $_SESSION['mc_auth_state'] === $_GET['state']) {
    $code = $_GET['code'];
    $redirect = isset($_SESSION['mc_auth_redirect']) ? $_SESSION['mc_auth_redirect'] : 'https://www.fuzzwork.co.uk/militarycampaigns/';
    unset($_SESSION['mc_auth_state']);
    unset($_SESSION['mc_auth_redirect']);

    $tokenResponse = mc_exchange_code($code);
    if ($tokenResponse === null || !isset($tokenResponse->access_token)) {
        error_log('militarycampaigns auth.php: token exchange failed');
        echo 'Login failed - could not exchange the code for a token. Please try logging in again.';
        exit;
    }

    // The access token (and any refresh token in $tokenResponse) is used
    // once, right here, and then discarded - it is never written to
    // $_SESSION or referenced again after this block.
    $accessToken = $tokenResponse->access_token;
    $character = mc_decode_jwt_character($accessToken);
    $activity = mc_fetch_activity($accessToken, $character['characterId']);

    $_SESSION['mc_character_id'] = $character['characterId'];
    $_SESSION['mc_character_name'] = $character['characterName'];
    $_SESSION['mc_activity_data'] = $activity;
    $_SESSION['mc_activity_fetched_at'] = time();
    session_write_close();

    header('Location: '.$redirect);
    exit;
} else {
    error_log('militarycampaigns auth.php: state mismatch or missing code/state');
    echo 'Login state mismatch. Please try logging in again from the campaigns page.';
}
