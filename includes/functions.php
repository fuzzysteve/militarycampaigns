<?php

function eveLinkStrip($text)
{
    if ($text === null) {
        return '';
    }
    $text = preg_replace('#<a\s+href=showinfo:\d+//\d+>(.*?)</a>#i', '$1', $text);
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// "CaptureDefendFWComplex" -> "Capture Defend FW Complex" (keeps runs of
// capitals like "FW"/"NPC" together instead of splitting every letter).
function camelCaseToWords($s)
{
    return preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $s);
}

// "career_path_enforcer" / "soldier-of-fortune" -> "Career Path Enforcer" / "Soldier Of Fortune"
function humanizeCode($s)
{
    return ucwords(str_replace(array('_', '-'), ' ', $s));
}

function extractIdsByValueType($decodedParams, $valueType)
{
    $ids = array();
    if (!is_array($decodedParams)) {
        return $ids;
    }
    foreach ($decodedParams as $param) {
        if (!isset($param->matcher->values) || !is_array($param->matcher->values)) {
            continue;
        }
        foreach ($param->matcher->values as $valueSet) {
            if (isset($valueSet->valueType) && $valueSet->valueType === $valueType && isset($valueSet->values) && is_array($valueSet->values)) {
                foreach ($valueSet->values as $id) {
                    $ids[(int)$id] = true;
                }
            }
        }
    }
    return array_keys($ids);
}

function fetchSolarSystems($dbh, $ids)
{
    if (!$ids) {
        return array();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT ss.solarSystemID, ss.solarSystemName, ss.security,
                   c.constellationName, r.regionName
            FROM mapSolarSystems ss
            LEFT JOIN mapConstellations c ON c.constellationID = ss.constellationID
            LEFT JOIN mapRegions r ON r.regionID = ss.regionID
            WHERE ss.solarSystemID IN ($placeholders)
            ORDER BY r.regionName, c.constellationName, ss.solarSystemName";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($ids);

    $systems = array();
    while ($row = $stmt->fetchObject()) {
        $row->securityRounded = round($row->security, 1);
        $systems[] = $row;
    }
    return $systems;
}

function fetchStations($dbh, $ids)
{
    if (!$ids) {
        return array();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT st.stationID, st.stationName, st.security,
                   ss.solarSystemName, c.constellationName, r.regionName
            FROM staStations st
            LEFT JOIN mapSolarSystems ss ON ss.solarSystemID = st.solarSystemID
            LEFT JOIN mapConstellations c ON c.constellationID = st.constellationID
            LEFT JOIN mapRegions r ON r.regionID = st.regionID
            WHERE st.stationID IN ($placeholders)
            ORDER BY r.regionName, c.constellationName, ss.solarSystemName, st.stationName";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($ids);

    $stations = array();
    while ($row = $stmt->fetchObject()) {
        $row->securityRounded = round($row->security, 1);
        $stations[] = $row;
    }
    return $stations;
}

// Maps id -> name for a set of ids from a lookup table. Table/column names
// are always fixed literals passed by our own code, never request input.
function fetchNameMap($dbh, $table, $idColumn, $nameColumn, $ids)
{
    if (!$ids) {
        return array();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT $idColumn AS id, $nameColumn AS name FROM $table WHERE $idColumn IN ($placeholders)";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($ids);

    $names = array();
    while ($row = $stmt->fetchObject()) {
        $names[(int)$row->id] = $row->name;
    }
    return $names;
}

// Friendly section labels for each contributionParameters "key". Anything
// not listed here falls back to a title-cased version of the raw key.
function contributionDetailLabels()
{
    return array(
        'agent_division' => 'Agent Division',
        'agent_identity' => 'Agent Faction',
        'agent_level' => 'Mission Level',
        'agent_type' => 'Agent Type',
        'container_type' => 'Container Types',
        'damaged_identity' => 'Faction to Damage',
        'damaged_ship' => 'Ship Types to Damage',
        'damager_identity' => 'Damaging Faction',
        'destroyed_identity' => 'Faction to Destroy',
        'destroyed_ship' => 'Ship Types to Destroy',
        'destroyer_identity' => 'Destroying Faction',
        'empire_faction' => 'Empire Faction',
        'fw_complex_type' => 'Complex Types',
        'manufactured_item' => 'Items to Manufacture',
        'npc_type' => 'NPC Ship Types',
        'ore' => 'Ore Types',
        'repaired_identity' => 'Faction to Repair',
        'repaired_ship' => 'Ship Types to Repair',
    );
}

// Expands the parts of contributionParameters that aren't already covered by
// the dedicated Systems/Stations tabs (factions, ship/item/container types,
// ore groups, FW complex sizes, mission level) into a label => values map,
// resolving ids to names wherever a matching lookup table exists.
function extractContributionDetails($decodedParams, $dbh)
{
    if (!is_array($decodedParams)) {
        return array();
    }

    $skipKeys = array('location', 'manufacturing_station');

    $factionIds = array();
    $typeIds = array();
    $groupIds = array();
    $archetypeIds = array();
    $divisionIds = array();
    $agentTypeIds = array();

    foreach ($decodedParams as $param) {
        if (!isset($param->matcher->values) || !is_array($param->matcher->values)) {
            continue;
        }
        foreach ($param->matcher->values as $valueSet) {
            if (!isset($valueSet->values) || !is_array($valueSet->values) || !isset($valueSet->valueType)) {
                continue;
            }
            switch ($valueSet->valueType) {
                case 'faction':
                    foreach ($valueSet->values as $v) {
                        $factionIds[(int)$v] = true;
                    }
                    break;
                case 'ship_type':
                case 'item_type':
                case 'container_type':
                    foreach ($valueSet->values as $v) {
                        $typeIds[(int)$v] = true;
                    }
                    break;
                case 'ore_group':
                    foreach ($valueSet->values as $v) {
                        $groupIds[(int)$v] = true;
                    }
                    break;
                case 'archetype':
                    foreach ($valueSet->values as $v) {
                        $archetypeIds[(int)$v] = true;
                    }
                    break;
                case 'agent_division':
                    foreach ($valueSet->values as $v) {
                        $divisionIds[(int)$v] = true;
                    }
                    break;
                case 'agent_type':
                    foreach ($valueSet->values as $v) {
                        $agentTypeIds[(int)$v] = true;
                    }
                    break;
            }
        }
    }

    $factionNames = fetchNameMap($dbh, 'chrFactions', 'factionID', 'factionName', array_keys($factionIds));
    $typeNames = fetchNameMap($dbh, 'invTypes', 'typeID', 'typeName', array_keys($typeIds));
    $groupNames = fetchNameMap($dbh, 'invGroups', 'groupID', 'groupName', array_keys($groupIds));
    $archetypeNames = fetchNameMap($dbh, 'dungeonArchetypes', 'archetypeID', 'title', array_keys($archetypeIds));
    $divisionNames = fetchNameMap($dbh, 'crpNPCDivisions', 'divisionID', 'divisionName', array_keys($divisionIds));
    $agentTypeNames = fetchNameMap($dbh, 'agtAgentTypes', 'agentTypeID', 'agentType', array_keys($agentTypeIds));

    $labels = contributionDetailLabels();
    $details = array();

    foreach ($decodedParams as $param) {
        $key = isset($param->key) ? $param->key : '';
        if (in_array($key, $skipKeys, true) || !isset($param->matcher->values) || !is_array($param->matcher->values)) {
            continue;
        }

        $values = array();
        foreach ($param->matcher->values as $valueSet) {
            if (!isset($valueSet->values) || !is_array($valueSet->values) || !isset($valueSet->valueType)) {
                continue;
            }
            foreach ($valueSet->values as $v) {
                switch ($valueSet->valueType) {
                    case 'faction':
                        $values[] = isset($factionNames[(int)$v]) ? $factionNames[(int)$v] : "Faction $v";
                        break;
                    case 'ship_type':
                    case 'item_type':
                    case 'container_type':
                        $name = isset($typeNames[(int)$v]) ? $typeNames[(int)$v] : "Type $v";
                        if ($key === 'manufactured_item') {
                            $name = '<a href="/blueprint/?typeid='.(int)$v.'">'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</a>';
                        }
                        $values[] = $name;
                        break;
                    case 'ore_group':
                        $values[] = isset($groupNames[(int)$v]) ? $groupNames[(int)$v] : "Ore Group $v";
                        break;
                    case 'archetype':
                        $values[] = isset($archetypeNames[(int)$v]) ? $archetypeNames[(int)$v] : "Complex Type $v";
                        break;
                    case 'agent_level':
                        $values[] = "Level $v";
                        break;
                    case 'agent_division':
                        $values[] = isset($divisionNames[(int)$v]) ? $divisionNames[(int)$v] : "Division $v";
                        break;
                    case 'agent_type':
                        $values[] = isset($agentTypeNames[(int)$v]) ? camelCaseToWords($agentTypeNames[(int)$v]) : "Type $v";
                        break;
                }
            }
        }

        if ($values) {
            $label = isset($labels[$key]) ? $labels[$key] : ucwords(str_replace('_', ' ', $key));
            if (!isset($details[$label])) {
                $details[$label] = array();
            }
            $details[$label] = array_merge($details[$label], $values);
        }
    }

    foreach ($details as $label => $values) {
        $details[$label] = array_values(array_unique($values));
    }

    return $details;
}

// Adds maxProgressPerParticipant + overall objective state to each entry of
// a personal-activity map (as built by mc_fetch_activity()), so api/activity.php
// can classify an objective as completed/failed/active the same way
// campaign.tpl's "Your Activity" column does, even on pages (like index.php)
// that don't already have this per-objective data rendered server-side. This
// is public data (same as shown on campaign.php) - no privacy concern.
function enrichActivityWithStatus($dbh, $activity)
{
    $ids = array_keys($activity);
    if (!$ids) {
        return $activity;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $states = fetchCampaignStates($dbh);
    $sql = "SELECT o.objectiveID, o.campaignID, o.maxProgressPerParticipant, o.targetProgress,
                   st.state, st.progress
            FROM milCampaignObjectives o
            LEFT JOIN militarycampaigns.milCampaignObjectiveStatus st ON st.objectiveID = o.objectiveID
            WHERE o.objectiveID IN ($placeholders)";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($ids);

    while ($row = $stmt->fetchObject()) {
        if (isset($activity[$row->objectiveID])) {
            $activity[$row->objectiveID]['maxProgressPerParticipant'] = (int)$row->maxProgressPerParticipant;
            $cs = isset($states[$row->campaignID]) ? $states[$row->campaignID] : null;
            $activity[$row->objectiveID]['status'] = effectiveObjectiveState($row->state, $row->progress, $row->targetProgress, $cs);
        }
    }

    return $activity;
}

// EVE text fields (annotations, and season-2+ subtitles) carry in-game
// markup: real newlines, <a href=showinfo:...>, <a href="localsvc:...">,
// <url=showinfo:...>/<url=show_on_map:...> (none of which work in a
// browser), plus <b>, <i> and <br>. Drop every <a>/<url> tag but keep its
// text, escape everything, then re-allow just <b>/<i>/<br> and turn newlines
// into <br>. Safe to echo unescaped.
function eveRichText($text)
{
    if ($text === null) {
        return '';
    }
    $text = preg_replace('#<a\b[^>]*>(.*?)</a>#is', '$1', $text);
    $text = preg_replace('#<url=[^>]*>(.*?)</url>#is', '$1', $text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('#&lt;(/?[bi]|br\s*/?)&gt;#i', '<$1>', $text);
    return nl2br($text, false);
}

// All annotations for the given campaigns, as campaignID => key => value
// (raw, unescaped - run through eveRichText() before display).
function fetchCampaignAnnotations($dbh, $campaignIDs)
{
    $result = array();
    if (!$campaignIDs) {
        return $result;
    }
    $placeholders = implode(',', array_fill(0, count($campaignIDs), '?'));
    $stmt = $dbh->prepare("SELECT campaignID, annotationKey, value
                           FROM milCampaignAnnotations
                           WHERE campaignID IN ($placeholders)
                           ORDER BY annotationID");
    $stmt->execute(array_values($campaignIDs));
    while ($row = $stmt->fetchObject()) {
        $result[$row->campaignID][$row->annotationKey] = $row->value;
    }
    return $result;
}

// Objective annotations for one campaign, as objectiveID => key => value.
function fetchObjectiveAnnotations($dbh, $campaignID)
{
    $result = array();
    $stmt = $dbh->prepare("SELECT a.objectiveID, a.annotationKey, a.value
                           FROM milCampaignObjAnnotations a
                           JOIN milCampaignObjectives o ON o.objectiveID = a.objectiveID
                           WHERE o.campaignID = ?
                           ORDER BY a.annotationID");
    $stmt->execute(array($campaignID));
    while ($row = $stmt->fetchObject()) {
        $result[$row->objectiveID][$row->annotationKey] = $row->value;
    }
    return $result;
}

// Neither the SDE nor ESI gives a campaign-level state or end date, so it's
// derived from the synced objective rows. A campaign has ended when it has
// no Active objective left, or nothing in it has changed for
// CAMPAIGN_STALE_DAYS. The second case is needed because the sync is a
// cursor-based change feed: once a campaign closes, ESI stops returning it,
// and a few objectives can be left "Active" in our table forever (season 1
// left three). The end date is the latest objective `finished` time.
//
// Leftover Active rows on an ended campaign were really Completed: when a
// campaign closes, ESI expires every objective still open in one batch
// (season 1: all 53 Expired rows finished at 2026-09-29 11:00, and the sync
// got every one of them), so a row that never got that expiry was already
// closed - and the only other way to close is completing. The sync just
// missed its final update (two of season 1's three sat at exactly
// progress == target; Rogue Edge's courier row stopped updating at 94% in
// June). This only applies if we did see that close-out batch (the campaign
// has Expired rows); without it, fall back to "reached its own target".
// Victory = Completed (incl. those) >= milCampaigns.targetProgress.
define('CAMPAIGN_STALE_DAYS', 3);

function fetchCampaignStates($dbh)
{
    $sql = "SELECT st.campaignID,
                   MIN(st.started) AS startedAt,
                   MAX(st.finished) AS lastFinished,
                   SUM(st.state = 'Active') AS activeCount,
                   SUM(st.state = 'Expired') AS expiredCount,
                   SUM(st.state = 'Active' AND o.targetProgress > 0 AND st.progress >= o.targetProgress) AS activeAtTargetCount,
                   MAX(st.lastModified) < UTC_TIMESTAMP() - INTERVAL ".CAMPAIGN_STALE_DAYS." DAY AS isStale,
                   SUM(st.state = 'Completed') AS completedCount
            FROM militarycampaigns.milCampaignObjectiveStatus st
            LEFT JOIN milCampaignObjectives o ON o.objectiveID = st.objectiveID
            GROUP BY st.campaignID";
    $stmt = $dbh->prepare($sql);
    $stmt->execute();

    $states = array();
    while ($row = $stmt->fetchObject()) {
        $ended = ((int)$row->activeCount === 0 || (int)$row->isStale === 1);
        $closeoutSeen = $ended && (int)$row->expiredCount > 0;
        $completed = (int)$row->completedCount;
        if ($ended) {
            $completed += $closeoutSeen ? (int)$row->activeCount : (int)$row->activeAtTargetCount;
        }
        $states[$row->campaignID] = (object)array(
            'startedAt' => $row->startedAt,
            'ended' => $ended,
            'closeoutSeen' => $closeoutSeen,
            'endedAt' => $row->lastFinished,
            'completedCount' => $completed,
        );
    }
    return $states;
}

// Fills in the derived state for one campaign row (needs ->campaignID and
// ->targetProgress): ->ended, ->startedAt, ->endedAt, ->completedCount,
// ->victory (null unless ended), and ->campaignProgressPercent.
function applyCampaignState($campaign, $states)
{
    $s = isset($states[$campaign->campaignID]) ? $states[$campaign->campaignID] : null;
    $campaign->hasStatus = ($s !== null);
    $campaign->ended = $s ? $s->ended : false;
    $campaign->closeoutSeen = $s ? $s->closeoutSeen : false;
    $campaign->startedAt = $s ? $s->startedAt : null;
    $campaign->endedAt = ($s && $s->ended) ? $s->endedAt : null;
    $campaign->completedCount = $s ? $s->completedCount : 0;
    $campaign->victory = $campaign->ended ? ($campaign->completedCount >= (int)$campaign->targetProgress) : null;
    $campaign->campaignProgressPercent = $campaign->targetProgress > 0
        ? round(min(100, $campaign->completedCount / $campaign->targetProgress * 100), 1)
        : null;
    return $campaign;
}

// An objective left "Active" after its campaign ended is shown with the
// same rule fetchCampaignStates() uses for the outcome: Completed if the
// close-out expiry batch was seen (it wasn't open at close), otherwise
// Completed only if it had reached its target, else Expired.
// $campaignState is a fetchCampaignStates() entry or null.
function effectiveObjectiveState($state, $progress, $targetProgress, $campaignState)
{
    if ($state === 'Active' && $campaignState && $campaignState->ended) {
        if ($campaignState->closeoutSeen) {
            return 'Completed';
        }
        return ($targetProgress > 0 && $progress >= $targetProgress) ? 'Completed' : 'Expired';
    }
    return $state;
}
