function updateUTCMissionTime(e) {
    var date = $("#mission-date")[0].value;
    var tz = $("#timezones")[0].value;

    var utc = moment.tz(date, "YYYY/MM/DD HH:mm", tz).utc().format("YYYY/MM/DD HH:mm");

    $("#mission-time-utc")[0].innerHTML = "GMT: " + utc;
}


$(document).ready(function() {
    updateUTCMissionTime();

    $('.select2').select2({
        width: '100%'
    });

    $('.datetimepicker').datetimepicker({
        controlType: 'select',
        oneLine: true,
        dateFormat: "yy-mm-dd",
        timeFormat: 'HH:mm',
        stepMinute: 15,
        showSecond: false,
        showTimezone: false,
        // altField: "#mission-time"
        // timezone: moment.tz.guess(),
        // timezoneList: tznames.map(function (tzname) {
        //     tz = moment.tz(tzname);
        //     return {
        //         value: tz.utcOffset(),
        //         label: tzname
        //     };
        // })
    });

    $('.datetimepicker').on("change", updateUTCMissionTime);
    $('#timezones').on("change", updateUTCMissionTime);

    $(".duration-picker").timesetter({});

    $("#duration-picker").setValuesByTotalMinutes($("#duration-value")[0].value);
    $("#duration-picker").on("change", function() {
        var mins = $("#duration-picker").getTotalMinutes();
        $("#duration-value")[0].value = mins;
    });

    // $("#edit-mission-form").submit(function() {
    // });

})
