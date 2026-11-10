<?php
require_once 'includes/head.php';

$sql = "SELECT c.campaignID, c.title, c.subtitle, c.factionID, f.factionName,
               c.targetProgress, c.campaignSet, c.race, c.themePack,
               c.mapFocusEntityID, m.itemName AS focusName,
               (SELECT COUNT(*) FROM milCampaignObjectives o WHERE o.campaignID = c.campaignID) AS objectiveCount
        FROM milCampaigns c
        LEFT JOIN chrFactions f ON f.factionID = c.factionID
        LEFT JOIN mapDenormalize m ON m.itemID = c.mapFocusEntityID
        ORDER BY f.factionName, c.title";

$stmt = $dbh->prepare($sql);
$stmt->execute();

$states = fetchCampaignStates($dbh);
$campaigns = array();
while ($row = $stmt->fetchObject()) {
    $row->subtitle = eveRichText($row->subtitle);
    applyCampaignState($row, $states);
    $campaigns[] = $row;
}

$annotations = fetchCampaignAnnotations($dbh, array_map(function ($c) { return $c->campaignID; }, $campaigns));
foreach ($campaigns as $c) {
    $a = isset($annotations[$c->campaignID]) ? $annotations[$c->campaignID] : array();
    $c->resolutionLabel = null;
    if ($c->ended) {
        $key = $c->victory ? 'finishedResolutionStateSuccess' : 'finishedResolutionStateFailure';
        $c->resolutionLabel = eveRichText(isset($a[$key]) ? $a[$key] : ($c->victory ? 'Victory' : 'Unsuccessful'));
    }
    $c->presentingCharacterName = isset($a['presentingCharacterName']) ? eveRichText($a['presentingCharacterName']) : '';
}

// Running campaigns first, then ended ones (most recently ended first);
// the SQL's faction/title order is kept within each group.
$order = array_flip(array_keys($campaigns));
usort($campaigns, function ($x, $y) use ($order) {
    if ($x->ended !== $y->ended) {
        return $x->ended ? 1 : -1;
    }
    if ($x->ended && $x->endedAt !== $y->endedAt) {
        return strcmp((string)$y->endedAt, (string)$x->endedAt);
    }
    return 0;
});

// A season (campaignSet) is rendered collapsed once every campaign in it has
// finished; until then all of its campaigns stay in the main list, finished
// ones included (a campaign can hit its target well before its season ends).
$setOpen = array();
foreach ($campaigns as $c) {
    $set = (string)$c->campaignSet;
    $setOpen[$set] = (isset($setOpen[$set]) && $setOpen[$set]) || !$c->ended;
}
$activeCampaigns = array();
$endedSeasons = array();
foreach ($campaigns as $c) {
    $set = (string)$c->campaignSet;
    if ($setOpen[$set]) {
        $activeCampaigns[] = $c;
        continue;
    }
    if (!isset($endedSeasons[$set])) {
        $endedSeasons[$set] = array(
            'id' => 'ended-'.preg_replace('/[^A-Za-z0-9_-]/', '', $set),
            'label' => preg_match('/season[_ ]?(\d+)/i', $set, $m) ? 'Season '.$m[1] : ($set !== '' ? humanizeCode($set) : 'Other'),
            'lastFinished' => $c->endedAt,
            'campaigns' => array(),
        );
    }
    if (strcmp((string)$c->endedAt, (string)$endedSeasons[$set]['lastFinished']) > 0) {
        $endedSeasons[$set]['lastFinished'] = $c->endedAt;
    }
    $endedSeasons[$set]['campaigns'][] = $c;
}

$smarty->assign('activeCampaigns', $activeCampaigns);
$smarty->assign('endedSeasons', array_values($endedSeasons));
$smarty->display('index.tpl');
