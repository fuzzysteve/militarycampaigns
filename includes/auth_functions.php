<?php
// Backs the app's own EVE SSO login (esi.activity.char:read), entirely
// separate from the sitewide identity-only /auth/ login. This is a one-off
// pull, by design: the access/refresh token is used exactly once, right
// after the OAuth callback, to fetch the character's activity - it is never
// written to $_SESSION and there is no refresh-token handling. Only the
// *result* of that one ESI call persists in session (see mc_get_my_activity()).

// ESI always returns campaign/objective ids in standard dashed UUID form,
// but the DB (both eve.milCampaigns/milCampaignObjectives and
// militarycampaigns.milCampaignObjectiveStatus) now stores them via
// SQLAlchemy's Uuid type, which on MySQL is CHAR(32) - the 32 hex digits
// with no dashes. Strip dashes on the way in so activity data keys/values
// match what campaign.tpl/index.tpl render into data-objective-id/
// data-campaign-id (straight from the DB) and what enrichActivityWithStatus()
// joins against.
function mc_normalize_uuid($uuid)
{
    return str_replace('-', '', $uuid);
}

function mc_decode_jwt_character($accessToken)
{
    $payload = explode('.', $accessToken)[1];
    $decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $payload))));
    return array(
        'characterId' => explode(':', $decoded->sub)[2],
        'characterName' => $decoded->name,
    );
}

function mc_exchange_code($code)
{
    require_once __DIR__.'/secret.php';

    $ch = curl_init('https://login.eveonline.com/v2/oauth/token');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'grant_type' => 'authorization_code',
            'code' => $code,
        )),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic '.base64_encode($mc_client_id.':'.$mc_client_secret),
            'Content-Type: application/x-www-form-urlencoded',
        ),
        CURLOPT_TIMEOUT => 30,
    ));
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('mc_exchange_code: curl error: '.$error);
        return null;
    }
    if ($httpCode !== 200) {
        error_log('mc_exchange_code: HTTP '.$httpCode.': '.$result);
        return null;
    }
    return json_decode($result);
}

// Pages through the character's activity (limit defaults to 10 on this
// endpoint - use 50 and follow the cursor, same pattern as
// scripts/syncObjectiveStatus.php's campaign-wide sync). Returns an
// objectiveID-keyed map for O(1) lookup by campaign.php's per-row rendering.
function mc_fetch_activity($accessToken, $characterId)
{
    $limit = 50;
    $after = null;
    $activity = array();

    do {
        $url = 'https://esi.evetech.net/characters/'.$characterId.'/military-campaigns/objectives?limit='.$limit;
        if ($after !== null) {
            $url .= '&after='.urlencode($after);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer '.$accessToken,
                'X-Compatibility-Date: 2026-08-04',
            ),
            CURLOPT_TIMEOUT => 30,
        ));
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            error_log('mc_fetch_activity: HTTP '.$httpCode.' error '.$error.': '.$body);
            break;
        }

        $data = json_decode($body);
        $objectives = isset($data->objectives) ? $data->objectives : array();
        foreach ($objectives as $objective) {
            $activity[mc_normalize_uuid($objective->id)] = array(
                'campaignID' => mc_normalize_uuid($objective->campaign_id),
                'contributed' => $objective->contributed,
                'isCommitted' => $objective->is_committed,
            );
        }

        $returned = count($objectives);
        $after = isset($data->cursor->after) ? $data->cursor->after : null;
    } while ($returned === $limit && $after !== null);

    return $activity;
}

// Read-only accessor for the snapshot stored by auth.php at last login. No
// ESI call, no refresh, no expiry check - reflects whatever was fetched the
// last time the user logged in, nothing more.
function mc_get_my_activity()
{
    return isset($_SESSION['mc_activity_data']) ? $_SESSION['mc_activity_data'] : null;
}
