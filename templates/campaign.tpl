{extends file='layout.tpl'}
{block name=title}{$campaign->title}{/block}
{block name=extrajavascript}
{literal}
<script type="text/javascript">
$(document).ready(function() {
    $('.objective-details-toggle').click(function() {
        $(this).closest('tr').next('.objective-details-row').toggle();
    });

    function applyFilters() {
        var status = $('.status-filter-btn.active').data('status');
        var career = $('.career-filter-btn.active').data('career');
        var method = $('.method-filter-btn.active').data('method');
        var activeTags = $('.tag-filter-btn.active').map(function() {
            return String($(this).data('tag'));
        }).get();

        $('#objectivesTable tbody tr.objective-row').each(function() {
            var $row = $(this);
            var show = true;

            if (status && $row.data('status') !== status) {
                show = false;
            }
            if (career && $row.data('career') !== career) {
                show = false;
            }
            if (method && String($row.data('method')) !== method) {
                show = false;
            }
            if (show && activeTags.length && activeTags[0] !== '') {
                var rowTags = String($row.data('tags') || '').split('|');
                var matches = activeTags.some(function(t) {
                    return rowTags.indexOf(t) !== -1;
                });
                if (!matches) {
                    show = false;
                }
            }

            $row.toggle(show);
            $row.next('.objective-details-row').hide();
        });
    }

    $('.status-filter-btn, .career-filter-btn, .method-filter-btn').click(function() {
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        applyFilters();
    });

    $('.tag-filter-btn').click(function() {
        if ($(this).data('tag') === '') {
            $('.tag-filter-btn').removeClass('active');
            $(this).addClass('active');
        } else {
            $(".tag-filter-btn[data-tag='']").removeClass('active');
            $(this).toggleClass('active');
            if ($('.tag-filter-btn.active').length === 0) {
                $(".tag-filter-btn[data-tag='']").addClass('active');
            }
        }
        applyFilters();
    });
});
</script>
{/literal}
{/block}
{block name=body}
<div class="page-header">
    <h1>{$campaign->title} <small>{$campaign->factionName}</small></h1>
</div>
<p>{$campaign->subtitle}</p>
<dl class="dl-horizontal">
    <dt>Race</dt><dd>{$campaign->race|capitalize}</dd>
    <dt>Focus</dt><dd>{$campaign->focusName}</dd>
    <dt>Theme Pack</dt><dd>{$campaign->themePack}</dd>
    <dt>Campaign Set</dt><dd>{$campaign->campaignSet}</dd>
    <dt>Target Progress</dt><dd>{$campaign->targetProgress|number_format}</dd>
</dl>
<p><a href="{$baseurl}/">&laquo; Back to all campaigns</a></p>

<h2>Objectives</h2>
<div style="margin-bottom:6px;">
    <strong>Status:</strong>
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-default btn-sm status-filter-btn active" data-status="">All</button>
        <button type="button" class="btn btn-default btn-sm status-filter-btn" data-status="Active">Active</button>
        <button type="button" class="btn btn-default btn-sm status-filter-btn" data-status="Completed">Completed</button>
    </div>
</div>
{if $careerPathFilters}
<div style="margin-bottom:6px;">
    <strong>Career Path:</strong>
    <button type="button" class="btn btn-default btn-sm career-filter-btn active" data-career="" style="margin:2px;">All</button>
    {foreach $careerPathFilters as $cp}
    <button type="button" class="btn btn-default btn-sm career-filter-btn" data-career="{$cp.value}" style="margin:2px;">{$cp.label}</button>
    {/foreach}
</div>
{/if}
{if $methodFilters}
<div style="margin-bottom:6px;">
    <strong>Method:</strong>
    <button type="button" class="btn btn-default btn-sm method-filter-btn active" data-method="" style="margin:2px;">All</button>
    {foreach $methodFilters as $m}
    <button type="button" class="btn btn-default btn-sm method-filter-btn" data-method="{$m.value}" style="margin:2px;">{$m.label}</button>
    {/foreach}
</div>
{/if}
{if $tagFilters}
<div style="margin-bottom:10px;">
    <strong>Tags:</strong>
    <button type="button" class="btn btn-default btn-sm tag-filter-btn active" data-tag="" style="margin:2px;">All</button>
    {foreach $tagFilters as $t}
    <button type="button" class="btn btn-default btn-sm tag-filter-btn" data-tag="{$t.value}" style="margin:2px;">{$t.label}</button>
    {/foreach}
</div>
{/if}
<table id="objectivesTable" class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Title</th>
            <th>Career Path</th>
            <th>Method</th>
            <th>Status</th>
            <th>Target Progress</th>
            <th>Max / Participant</th>
            <th>ISK Reward</th>
            <th>LP Reward</th>
            <th>Standing Reward</th>
            <th>Issuer</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    {foreach $objectives as $objective}
        <tr class="objective-row" data-status="{$objective->statusState}" data-career="{$objective->careerPath}" data-method="{$objective->contributionMethod}" data-tags="{$objective->tagsAttr}">
            <td>{$objective->title}</td>
            <td>{$objective->careerPath}</td>
            <td>{$objective->methodDisplay}</td>
            <td class="text-center">
                {if $objective->statusState}
                <span class="label label-{if $objective->statusState=='Completed'}success{else}info{/if}">{$objective->statusState}</span>
                {if $objective->progressPercent !== null}
                <div class="progress" style="margin:12px 0 0 0;height:16px;">
                    <div class="progress-bar {if $objective->statusState=='Completed'}progress-bar-success{else}progress-bar-info{/if}" style="width:{$objective->progressPercent}%">{$objective->progressPercent}%</div>
                </div>
                <small>{$objective->statusProgress|number_format} / {$objective->targetProgress|number_format}</small>
                {/if}
                {else}
                <span class="label label-default">Unknown</span>
                {/if}
            </td>
            <td>{$objective->targetProgress|number_format}</td>
            <td>{$objective->maxProgressPerParticipant|number_format}</td>
            <td>{if $objective->rewardIskAmount}{$objective->rewardIskAmount|number_format} ISK / {$objective->rewardIskInterval|number_format} progress{/if}</td>
            <td>{if $objective->rewardLpAmount}{$objective->rewardLpAmount|number_format} LP / {$objective->rewardLpInterval|number_format} progress{/if}</td>
            <td>{if $objective->rewardStandingPercent}{$objective->rewardStandingPercent}% ({$objective->rewardStandingFactionName}) / {$objective->rewardStandingInterval|number_format} progress{/if}</td>
            <td>{$objective->issuerCorpName}</td>
            <td><button type="button" class="btn btn-xs btn-default objective-details-toggle">Details</button></td>
        </tr>
        <tr class="objective-details-row" style="display:none">
            <td colspan="11">
                {if $objective->subtitle}<p>{$objective->subtitle}</p>{/if}
                {if $objective->statusState}
                <p><strong>Participants:</strong> {$objective->statusParticipantsTotal|number_format} total, {$objective->statusParticipantsCommitted|number_format} committed, {$objective->statusParticipantsContributors|number_format} contributed</p>
                <p><strong>Started:</strong> {$objective->statusStarted} {if $objective->statusFinished}<strong>&nbsp;Finished:</strong> {$objective->statusFinished}{/if}</p>
                {/if}
                {if $objective->enlistmentFactionName}<p><strong>Requires Enlistment In:</strong> {$objective->enlistmentFactionName}</p>{/if}
                {if $objective->tags}<p><strong>Tags:</strong> {foreach $objective->tags as $tag}<span class="label label-default">{$tag}</span> {/foreach}</p>{/if}
                {if $objective->contributionParametersPretty}
                <p><strong>Contribution Parameters:</strong></p>
                {if $objective->contributionDetails || $objective->solarSystems || $objective->stations}
                {assign var="tabActive" value=false}
                <ul class="nav nav-tabs" role="tablist">
                    {if $objective->contributionDetails}
                    <li role="presentation" class="active"><a href="#requirements-{$objective->objectiveID}" role="tab" data-toggle="tab">Requirements</a></li>
                    {assign var="tabActive" value=true}
                    {/if}
                    {if $objective->solarSystems}
                    <li role="presentation" class="{if !$tabActive}active{/if}"><a href="#systems-{$objective->objectiveID}" role="tab" data-toggle="tab">Systems ({$objective->solarSystems|@count})</a></li>
                    {assign var="tabActive" value=true}
                    {/if}
                    {if $objective->stations}
                    <li role="presentation" class="{if !$tabActive}active{/if}"><a href="#stations-{$objective->objectiveID}" role="tab" data-toggle="tab">Stations ({$objective->stations|@count})</a></li>
                    {assign var="tabActive" value=true}
                    {/if}
                    <li role="presentation"><a href="#raw-{$objective->objectiveID}" role="tab" data-toggle="tab">Raw Parameters</a></li>
                </ul>
                {assign var="tabActive" value=false}
                <div class="tab-content">
                    {if $objective->contributionDetails}
                    <div role="tabpanel" class="tab-pane active" id="requirements-{$objective->objectiveID}">
                        <dl class="dl-horizontal">
                        {foreach $objective->contributionDetails as $label => $values}
                            <dt>{$label}</dt>
                            <dd>{foreach $values as $value}{$value}{if !$value@last}, {/if}{/foreach}</dd>
                        {/foreach}
                        </dl>
                    </div>
                    {assign var="tabActive" value=true}
                    {/if}
                    {if $objective->solarSystems}
                    <div role="tabpanel" class="tab-pane {if !$tabActive}active{/if}" id="systems-{$objective->objectiveID}">
                        <table class="table table-condensed table-striped">
                            <thead>
                                <tr><th>System</th><th>Security</th><th>Constellation</th><th>Region</th></tr>
                            </thead>
                            <tbody>
                            {foreach $objective->solarSystems as $system}
                                <tr>
                                    <td>{$system->solarSystemName}</td>
                                    <td>{$system->securityRounded}</td>
                                    <td>{$system->constellationName}</td>
                                    <td>{$system->regionName}</td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {assign var="tabActive" value=true}
                    {/if}
                    {if $objective->stations}
                    <div role="tabpanel" class="tab-pane {if !$tabActive}active{/if}" id="stations-{$objective->objectiveID}">
                        <table class="table table-condensed table-striped">
                            <thead>
                                <tr><th>Station</th><th>System</th><th>Security</th><th>Constellation</th><th>Region</th></tr>
                            </thead>
                            <tbody>
                            {foreach $objective->stations as $station}
                                <tr>
                                    <td>{$station->stationName}</td>
                                    <td>{$station->solarSystemName}</td>
                                    <td>{$station->securityRounded}</td>
                                    <td>{$station->constellationName}</td>
                                    <td>{$station->regionName}</td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {assign var="tabActive" value=true}
                    {/if}
                    <div role="tabpanel" class="tab-pane" id="raw-{$objective->objectiveID}">
                        <pre>{$objective->contributionParametersPretty}</pre>
                    </div>
                </div>
                {else}
                <pre>{$objective->contributionParametersPretty}</pre>
                {/if}
                {/if}
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>
{/block}
