$(function() {

    $( "#confirm-delete-dialog" ).dialog({
        autoOpen: false,
        modal: true,
        width: 400,
        position: { my: "bottom", at: "bottom", of: "#runs-info" },
        buttons: [
            {
                text: "Yes",
                click: function() {
                    $( this ).dialog( "close" );
                    window.location = url;
                }
            },
            {
                text: "No",
                click: function() {
                    $( this ).dialog( "close" );
                }
            }
        ]
    });

    // Link to open the dialog
    $( ".delete-run" ).click(function( event ) {
        $( "#confirm-delete-dialog" ).dialog( "moveToTop" );
        $( "#confirm-delete-dialog" ).dialog( "open" );
        url = event.currentTarget.attributes['href'].value;
        event.preventDefault();
    });
});
