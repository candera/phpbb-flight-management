$(function() {

    // page is now ready, initialize the calendar...

    $('#ato-calendar').fullCalendar({
        events: atoEventData,
        timezone: "local"
    })

    
});
