$(function() {

    // page is now ready, initialize the calendar...

    $('#ato-calendar').fullCalendar({
        events: atoEventData,
        timezone: "local",
        defaultView: 'agendaWeek',
        header: {
            left: 'title',
            center: '',
            right: 'today prev,next month agendaWeek'
        }
    })

    
});
