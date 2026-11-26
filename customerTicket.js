let searchTicket = $("#search-ticket");
let searchStatus = $("#searchStatus");
let searchDepartment = $("#searchDepartment");
let tableBody = $("#tickets-body");

function loadTickets () {

    $.ajax({
        url: "customerTicketSearch.php",
        type: "GET", 
        data: {
            search : searchTicket.val(),
            status: searchStatus.val(),
            department: searchDepartment.val()
        },
        success: function(response) {
            tableBody.html(response);
        }
    });

};

searchTicket.on("input", function() {

    loadTickets();

});
searchStatus.on("change", function() {

    loadTickets();

});

searchDepartment.on("change", function() {

    loadTickets();

});