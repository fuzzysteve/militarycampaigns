<?php
require_once 'includes/head.php';

$campaignID = isset($_GET['campaignID']) ? $_GET['campaignID'] : '';

$sql = "SELECT c.campaignID, c.title, c.subtitle, c.factionID, f.factionName,
               c.targetProgress, c.campaignSet, c.race, c.themePack,
               c.mapFocusEntityID, m.itemName AS focusName
        FROM milCampaigns c
        LEFT JOIN chrFactions f ON f.factionID = c.factionID
        LEFT JOIN mapDenormalize m ON m.itemID = c.mapFocusEntityID
        WHERE c.campaignID = ?";
$stmt = $dbh->prepare($sql);
$stmt->execute(array($campaignID));
$campaign = $stmt->fetchObject();

if (!$campaign) {
    header('Location: '.$baseurl.'/');
    exit;
}
$campaign->subtitle = eveLinkStrip($campaign->subtitle);

$objsql = "SELECT o.*,
                  ic.corporationName AS issuerCorpName,
                  sf.factionName AS rewardStandingFactionName,
                  ef.factionName AS enlistmentFactionName,
                  st.state AS statusState,
                  st.progress AS statusProgress,
                  st.participantsTotal AS statusParticipantsTotal,
                  st.participantsCommitted AS statusParticipantsCommitted,
                  st.participantsContributors AS statusParticipantsContributors,
                  st.started AS statusStarted,
                  st.finished AS statusFinished,
                  st.lastModified AS statusLastModified
           FROM milCampaignObjectives o
           LEFT JOIN crpNPCCorporations ic ON ic.corporationID = o.issuerCorporationID
           LEFT JOIN chrFactions sf ON sf.factionID = o.rewardStandingFactionID
           LEFT JOIN chrFactions ef ON ef.factionID = o.requiredEnlistmentFactionID
           LEFT JOIN militarycampaigns.milCampaignObjectiveStatus st ON st.objectiveID = o.objectiveID
           WHERE o.campaignID = ?
           ORDER BY o.careerPath, o.title";
$objstmt = $dbh->prepare($objsql);
$objstmt->execute(array($campaignID));

$tagsql = "SELECT tag FROM milCampaignObjContentTags WHERE objectiveID = ? ORDER BY tag";
$tagstmt = $dbh->prepare($tagsql);

$objectives = array();
while ($row = $objstmt->fetchObject()) {
    $row->subtitle = eveLinkStrip($row->subtitle);

    $tagstmt->execute(array($row->objectiveID));
    $tags = array();
    while ($trow = $tagstmt->fetchObject()) {
        $tags[] = $trow->tag;
    }
    $row->tags = $tags;
    $row->tagsAttr = implode('|', $tags);
    $row->methodDisplay = camelCaseToWords($row->contributionMethod);

    $row->progressPercent = null;
    if ($row->statusProgress !== null && $row->targetProgress > 0) {
        $row->progressPercent = round(min(100, ($row->statusProgress / $row->targetProgress) * 100), 1);
    }

    $row->contributionParametersPretty = '';
    $row->solarSystems = array();
    $row->stations = array();
    $row->contributionDetails = array();
    if ($row->contributionParameters) {
        $decoded = json_decode($row->contributionParameters);
        if ($decoded !== null) {
            $row->contributionParametersPretty = htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
            $row->solarSystems = fetchSolarSystems($dbh, extractIdsByValueType($decoded, 'solarsystem'));
            $row->stations = fetchStations($dbh, extractIdsByValueType($decoded, 'station'));
            $row->contributionDetails = extractContributionDetails($decoded, $dbh);
        }
    }

    $objectives[] = $row;
}

$careerPaths = array();
$methods = array();
$allTags = array();
foreach ($objectives as $obj) {
    if ($obj->careerPath) {
        $careerPaths[$obj->careerPath] = true;
    }
    if ($obj->contributionMethod) {
        $methods[$obj->contributionMethod] = $obj->methodDisplay;
    }
    foreach ($obj->tags as $tag) {
        $allTags[$tag] = true;
    }
}

$careerPathFilters = array();
foreach (array_keys($careerPaths) as $careerPath) {
    $careerPathFilters[] = array('value' => $careerPath, 'label' => humanizeCode($careerPath));
}
usort($careerPathFilters, function ($a, $b) {
    return strcmp($a['label'], $b['label']);
});

$methodFilters = array();
foreach ($methods as $method => $display) {
    $methodFilters[] = array('value' => $method, 'label' => $display);
}
usort($methodFilters, function ($a, $b) {
    return strcmp($a['label'], $b['label']);
});

$tagFilters = array();
foreach (array_keys($allTags) as $tag) {
    $tagFilters[] = array('value' => $tag, 'label' => humanizeCode($tag));
}
usort($tagFilters, function ($a, $b) {
    return strcmp($a['label'], $b['label']);
});

$smarty->assign('campaign', $campaign);
$smarty->assign('objectives', $objectives);
$smarty->assign('careerPathFilters', $careerPathFilters);
$smarty->assign('methodFilters', $methodFilters);
$smarty->assign('tagFilters', $tagFilters);
$smarty->display('campaign.tpl', $campaignID);
