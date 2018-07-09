function changeTimezone(event, zone) {
    console.log(event);

    newEvent = {}
    newEvent.start = moment(event.start).tz(zone).format();
    newEvent.end = moment(event.end).tz(zone).format();
    newEvent.title = event.title;
    newEvent.url = event.url;
    newEvent.color = event.color;

    console.log(newEvent);

    return newEvent;
}

function localizedEventData(tz)
{
    return atoEventData.map(function(evt) { return changeTimezone(evt, tz); });
}

function updateCalendarTimezone(e) {
    newTz = $("#timezones")[0].value;
    console.log("Changing timezone on calendar to " + newTz);
    $('#ato-calendar').fullCalendar('option', 'timezone',  newTz);
    $('#ato-calendar').fullCalendar('removeEvents');
    $('#ato-calendar').fullCalendar('addEventSource', localizedEventData(newTz));

    console.log("Events updated");
}

$(function() {

    // page is now ready, initialize the calendar...

    var timezoneOverride = localStorage.getItem("ATODisplayedTimezone");
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

    $('#ato-calendar').fullCalendar({
        events: localizedEventData(timezone),
        timezone: timezone,
        defaultView: 'sixtyDay',
        timeFormat: 'HH:mm',
        header: {
            left: 'title',
            center: '',
            right: 'today prev,next sixtyDay month agendaWeek'
        },
        views: {
            sixtyDay: {
                type: "list",
                duration: { days: 60 },
                buttonText: "list",
                dateIncrement: { days: 60 },
                listDayFormat: 'ddd MMM D'
            }
        }
    });

    $('.select2').select2({
    });


    $("#timezones").val(timezone).trigger("change");

    $('#timezones').on("change", updateCalendarTimezone);

});

function setDefaultTimezone()
{
    tz = $("#timezones")[0].value;
    localStorage.setItem("ATODisplayedTimezone", tz);
    alert("Set " + tz + " as default timezone");
}
