jQuery(document).ready(function ($) {

    $('#kwils_tabs').responsiveTabs({
        startCollapsed: 'accordion'
    });

    var kwils_widget_added = kwils_config_js.kwils_widget_added;
    var kwils_refresh_interval = kwils_config_js.kwils_refresh_interval;
    var kwils_api_url = kwils_config_js.kwils_api_url;

    function fetch_latest_score() {
        $.ajax({
            type: 'GET',
            url: kwils_api_url,
            dataType: 'json',
            success: function (data) {

                if (data.t && kwils_can_update_score(data)) {
                    var newTeamInnings = kwils_get_updated_innings(data);
                    var newBatSummary = kwils_get_updated_batsummary(data);
                    $("#kwils_live_score").find("tr.team-innings:gt(0)").remove();
                    $("#kwils_live_score").find("tr.team-innings").replaceWith(newTeamInnings);

                    $("#kwils_live_score").find("tr.bat-summary:gt(0)").remove();
                    $("#kwils_live_score").find("tr.bat-summary").replaceWith(newBatSummary);
                    setTimeout(fetch_latest_score, kwils_refresh_interval);
                }
            }
        });
    }
    function kwils_can_update_score(data) {

        return (data && data.nm == 0) ? true : false;
    }
    function kwils_get_updated_innings(data) {
        var row = '';
        for (var i = 0; i < data.i.length; i++) {
            row += '<tr class="team-innings"><td>' + data.i[i].t + '</td><td>' + data.i[i].s + '</td></tr>';
        }
        return row;
    }
    function kwils_get_updated_batsummary(data) {
        var row = '';
        for (var i = 0; i < data.b.length; i++) {
            row += '<tr class="bat-summary"><td>' + data.b[i].n + '</td><td>' + data.b[i].s + '</td></tr>';
        }
        return row;
    }

    if (kwils_widget_added) {
        setTimeout(fetch_latest_score, kwils_refresh_interval);
    }
});