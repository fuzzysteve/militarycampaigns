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

$campaigns = array();
while ($row = $stmt->fetchObject()) {
    $row->subtitle = eveLinkStrip($row->subtitle);
    $campaigns[] = $row;
}

$smarty->assign('campaigns', $campaigns);
$smarty->display('index.tpl');
