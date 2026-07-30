{extends file='layout.tpl'}
{block name=title}Campaigns{/block}
{block name=body}
<div class="page-header">
    <h1>Military Campaigns</h1>
</div>
{foreach $campaigns as $campaign}
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{$campaign->title} <small>{$campaign->factionName}</small></h3>
    </div>
    <div class="panel-body">
        <p>{$campaign->subtitle}</p>
        <dl class="dl-horizontal">
            <dt>Race</dt><dd>{$campaign->race|capitalize}</dd>
            <dt>Focus</dt><dd>{$campaign->focusName}</dd>
            <dt>Theme Pack</dt><dd>{$campaign->themePack}</dd>
            <dt>Campaign Set</dt><dd>{$campaign->campaignSet}</dd>
            <dt>Target Progress</dt><dd>{$campaign->targetProgress|number_format}</dd>
            <dt>Objectives</dt><dd>{$campaign->objectiveCount}</dd>
        </dl>
        <a class="btn btn-primary" href="{$baseurl}/campaign.php?campaignID={$campaign->campaignID}">View Objectives</a>
    </div>
</div>
{/foreach}
{/block}
