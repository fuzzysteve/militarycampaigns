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
{function name=campaignPanel}
<div class="panel panel-default" data-campaign-id="{$campaign->campaignID}">
    <div class="panel-heading">
        <h3 class="panel-title">{$campaign->title} <small>{$campaign->factionName}</small>
            <span class="pull-right">
            {if $campaign->ended}
                <span class="label label-default">Ended</span>
                <span class="label label-{if $campaign->victory}success{else}danger{/if}">{$campaign->resolutionLabel}</span>
            {elseif $campaign->hasStatus}
                <span class="label label-info">Active</span>
            {/if}
            </span>
        </h3>
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
                    {if $campaign->presentingCharacterName}<dt>Presented By</dt><dd>{$campaign->presentingCharacterName}</dd>{/if}
                    {if $campaign->hasStatus}
                    <dt>Campaign Progress</dt><dd>{$campaign->completedCount} / {$campaign->targetProgress|number_format} objectives completed</dd>
                    <dt>Started</dt><dd>{$campaign->startedAt}</dd>
                    {if $campaign->ended}<dt>Ended</dt><dd>{$campaign->endedAt}</dd>{/if}
                    {/if}
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
{/function}
{foreach $activeCampaigns as $campaign}
{call name=campaignPanel campaign=$campaign}
{foreachelse}
<p class="text-muted">No campaigns are currently running.</p>
{/foreach}
{foreach $endedSeasons as $season}
<h3>
    {$season.label} <small>ended {$season.endedAt|substr:0:10}, {$season.campaigns|@count} campaign{if $season.campaigns|@count != 1}s{/if}</small>
    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#{$season.id}" aria-expanded="false" aria-controls="{$season.id}">Show / hide</button>
</h3>
<div class="collapse" id="{$season.id}">
{foreach $season.campaigns as $campaign}
{call name=campaignPanel campaign=$campaign}
{/foreach}
</div>
{/foreach}
{/block}
