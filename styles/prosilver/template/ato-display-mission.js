function updateMissionTimezone(e) {
    newTz = $("#timezones")[0].value;
    console.log("Changing timezone to " + newTz);

    $("#missiontime").html(moment(missiondatetime).tz(newTz).format("YYYY-MM-DD HH:mm"));
    $("#missiontime-utc").html(moment(missiondatetime).tz("UTC").format("YYYY-MM-DD HH:mm"));
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

})

function setDefaultTimezone()
{
    tz = $("#timezones")[0].value;
    localStorage.setItem("ATODisplayedTimezone", tz);
    alert("Set " + tz + " as default timezone");
}
