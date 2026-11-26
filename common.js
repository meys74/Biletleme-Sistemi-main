

$(".notification").on("click", function() {
    $(".notification-container").toggleClass("show");
});
$(".close-notification-sidebar").on("click", function() {
    $(".notification-container").removeClass("show");
});

$(document).on("click", ".notification-inputs", function () {
    let notification_ID = $(this).data("id");
    let notification = $(this);
    
    $.ajax({
        url: "markNotificationRead.php",
        type:"POST",
        data: {
            notification_ID: notification_ID,
            csrf_token: csrfToken
        },
        success: function(response) {
            notification.remove();
            
        }
        
    })
});

$("#cancel").on("click" , function(){
    history.back();
});
// Success toast
const successToastElement = document.getElementById("successToast");
const successToast = new bootstrap.Toast(successToastElement, {
    delay: 2000
});

// Error toast
const errorToastElement = document.getElementById("errorToast");
const errorToast = new bootstrap.Toast(errorToastElement, {
    delay: 2000
});

if (updateStatus === 1) {
    successToast.show();
    history.replaceState({}, "", window.location.pathname);
}else if (updateStatus === 0) {
    errorToast.show();
    history.replaceState({}, "", window.location.pathname);
};
