{extends file='layout.tpl'}
{block name=title}Campaigns{/block}
{block name=extrajavascript}
<script src="{$baseurl}/js/activity.js"></script>
{/block}
{block name=body}
<div class="page-header">
    <h1>Military Campaigns</h1>
</div>
<p><span id="mc-auth-ui"></span></p>
{foreach $campaigns as $campaign}
<div class="panel panel-default" data-campaign-id="{$campaign->campaignID}">
    <div class="panel-heading">
        <h3 class="panel-title">{$campaign->title} <small>{$campaign->factionName}</small></h3>
    </div>
    <div class="panel-body">
        <p>{$campaign->subtitle}</p>
        <div class="row">
            <div class="col-md-6">
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
            <div class="col-md-6">
                <h4>Your Activity</h4>
                <p class="mc-campaign-activity" data-campaign-id="{$campaign->campaignID}"></p>
            </div>
        </div>
    </div>
</div>
{/foreach}
{/block}
