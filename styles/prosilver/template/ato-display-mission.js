function updateMissionTimezone(e) {
    newTz = $("#timezones")[0].value;
    console.log("Changing timezone to " + newTz);

    $("#missiontime").html(moment(missiondatetime).tz(newTz).format("ddd YYYY-MM-DD HH:mm")
                           + " ("
                           + newTz
                           + ")");
    $("#missiontime-utc").html(moment(missiondatetime).tz("UTC").format("ddd YYYY-MM-DD HH:mm"));
}

$(document).ready(function() {
    $('.select2').select2({
        width: '100%'
    });

    $('#timezones').on("change", updateMissionTimezone);

    var timezoneOverride = localStorage.getItem("ATODisplayedTimezone")
    var timezone;
    if (timezoneOverride)
    {
        timezone = timezoneOverride;
    }
    else
    {
        timezone = moment.tz.guess();
    }

    console.log("using timezone " + timezone);

    $('#timezones').on("change", updateMissionTimezone);
    $("#timezones").val(timezone).trigger("change");

    // Open select2 controls when tabbing to them
    $(document).on('focus', '.select2', function (e) {
        if (e.originalEvent) {
            $(this).siblings('select').select2('open');
        }
    });

})

function setDefaultTimezone()
{
    tz = $("#timezones")[0].value;
    localStorage.setItem("ATODisplayedTimezone", tz);
    alert("Set " + tz + " as default timezone");
}
