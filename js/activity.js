$(document).ready(function() {
    var BASE_URL = '/militarycampaigns';

    function escapeHtml(s) {
        return $('<div>').text(s).html();
    }

    // Shared classification: hitting your personal cap means "completed"
    // regardless of the objective's own state; short of the cap, the
    // objective's state distinguishes still-in-progress from too-late.
    function classifyActivity(info) {
        var maxProgress = info.maxProgressPerParticipant || 0;
        var metMax = maxProgress > 0 && info.contributed >= maxProgress;
        if (metMax) {
            return 'completed';
        }
        if (info.status === 'Completed') {
            return 'failed';
        }
        return 'active';
    }

    $.ajax({
        url: BASE_URL + '/api/activity.php',
        dataType: 'json'
    }).done(function(data) {
        if (data.loggedIn) {
            $('#mc-auth-ui').html(
                'Logged in as ' + escapeHtml(data.characterName) +
                ' &mdash; <a href="' + BASE_URL + '/logout.php">Logout</a>'
            );
        } else {
            $('#mc-auth-ui').html(
                '<a href="' + BASE_URL + '/login.php">Log in with EVE Online</a> to see your personal campaign activity'
            );
        }

        if (!data.loggedIn) {
            return;
        }

        // campaign.php: enrich each objective row with the viewer's own
        // contribution (badge + progress bar in the "Your Activity" column),
        // then let the existing filter re-evaluate.
        if ($('#objectivesTable').length) {
            $('.objective-row').each(function() {
                var $row = $(this);
                var $cell = $row.find('.mc-my-activity');
                var info = data.objectives[$row.data('objective-id')];
                var overallStatus = String($row.data('status'));

                if (!info || !(info.contributed > 0)) {
                    $cell.html('<span class="label label-default">No Activity</span>');
                    return;
                }

                var maxProgress = parseFloat($row.data('max-progress')) || 0;
                var percent = maxProgress > 0 ? Math.round(Math.min(1, info.contributed / maxProgress) * 1000) / 10 : 0;
                // Use the row's own server-rendered max/status (authoritative
                // for this page) rather than the API's enriched copy.
                var classification = classifyActivity({
                    contributed: info.contributed,
                    maxProgressPerParticipant: maxProgress,
                    status: overallStatus
                });
                var flags = ['active'];
                var badgeClass, barClass, badgeText;

                if (classification === 'completed') {
                    badgeClass = 'label-success';
                    barClass = 'progress-bar-success';
                    badgeText = 'Completed';
                    flags.push('completed');
                } else if (classification === 'failed') {
                    badgeClass = 'label-danger';
                    barClass = 'progress-bar-danger';
                    badgeText = 'Failed';
                    flags.push('failed');
                } else {
                    badgeClass = 'label-info';
                    barClass = 'progress-bar-info';
                    badgeText = 'Active';
                }

                $row.data('my', flags.join('|'));
                $cell.html(
                    '<span class="label ' + badgeClass + '">' + badgeText + '</span>' +
                    '<div class="progress" style="margin:12px 0 0 0;height:16px;">' +
                    '<div class="progress-bar ' + barClass + '" style="width:' + percent + '%">' + percent + '%</div>' +
                    '</div>' +
                    '<small>' + info.contributed.toLocaleString() + ' / ' + maxProgress.toLocaleString() + '</small>'
                );
            });
            if (typeof applyFilters === 'function') {
                applyFilters();
            }
        }

        // index.php: classify each objective (completed/failed/active) the
        // same way as campaign.php's "Your Activity" column, and tally per
        // campaign - requires the API's enriched maxProgressPerParticipant/
        // status, since this page has no per-objective data of its own.
        if ($('.mc-campaign-activity').length) {
            var byCampaign = {};
            $.each(data.objectives, function(objectiveId, info) {
                if (!(info.contributed > 0)) {
                    return;
                }
                if (!byCampaign[info.campaignID]) {
                    byCampaign[info.campaignID] = {completed: 0, failed: 0, active: 0};
                }
                byCampaign[info.campaignID][classifyActivity(info)] += 1;
            });
            $('.mc-campaign-activity').each(function() {
                var $el = $(this);
                var summary = byCampaign[$el.data('campaign-id')];
                if (!summary) {
                    return;
                }
                var parts = [];
                if (summary.completed) {
                    parts.push(summary.completed + ' completed');
                }
                if (summary.failed) {
                    parts.push(summary.failed + ' failed');
                }
                if (summary.active) {
                    parts.push(summary.active + ' still active');
                }
                $el.text(parts.join(', ') + '.');
            });
        }
    });
});
