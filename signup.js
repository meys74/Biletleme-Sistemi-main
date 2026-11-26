$(document).ready(function() {

    let iti = null;
    const phoneInput = document.querySelector("#phone-number");

    if (phoneInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "tr",
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js",
        });
    }   

    $('#username').on('blur', function() {
        let usernameValue = $(this).val();

        if (usernameValue.length > 2) {
            $.ajax({
                url: 'ajax_check.inc.php',
                type: 'POST',
                data: { username_check: usernameValue },
                dataType: 'json',
                success: function(result) {
                    if (result.exists) {
                        $('#username-message').css('color', 'red').text('Username is already taken!');
                        $('#username').css('border-color', 'red');
                    } else {
                        $('#username-message').css('color', 'green').text('Username is available.');
                        $('#username').css('border-color', 'green');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                }
            });
        }
        else{
            $('#username-message').css('color', 'red').text('Empty input');
            $('#username').css('border-color', 'red');
        }
    
    });

    $("#tempAlert").delay(3000).fadeOut(500, function() {
        $(this).remove();
    });

    $('#first-name').on('blur', function() {
        let name = $("#first-name").val();

        if (name.length < 2 ) {
                $('#name-message').css('color', 'red').text('Empty input');
                $('#first-name').css('border-color', 'red');
            }
        else{
            $('#name-message').css('color', 'green').text('');
            $('#first-name').css('border-color', 'green');
        }
    });
    $('#last-name').on('blur', function() {
        let surname = $("#last-name").val();

        if (surname.length < 2 ) {
            $('#surname-message').css('color', 'red').text('Empty input');
            $('#last-name').css('border-color', 'red');
        }
        else{
            $('#surname-message').css('color', 'green').text('');
            $('#last-name').css('border-color', 'green');
        }
    });
    $('#phone-number').on('blur', function() {
        if (iti) {
            let rawNumber = iti.getNumber();
            if(rawNumber) {
                iti.setNumber(rawNumber); 
            }
        }

        let phone = $("#phone-number").val();
        
        
        if (phone.length < 2 ) {
            $('#number-message').css('color', 'red').text('Empty input');
            $('#phone-number').css('border-color', 'red');
        }
        else{
            $('#number-message').css('color', 'green').text('');
            $('#phone-number').css('border-color', 'var(--mid-dark-text-color)');
        }
    });

    $('#phone-number').on('input', function() {
        this.value = this.value.replace(/[^0-9\s\-\+\(\)]/g, '');
    });
    
    $("#nextBtn").click(function() {
        let name = $("#first-name").val();
        let surname = $("#last-name").val();
        let phone = $("#phone-number").val();

        if (name !== "" && surname !== "" && phone !== "") {
            $("#step-1").hide();
            $("#step-2").fadeIn(400);
        } else {
            alert("Please fill in all the inputs!");
        }
    });

    $("#prevBtn").click(function() {
        $("#step-2").hide();
        $("#step-1").fadeIn(400);
    });

    $('form').on('submit', function(e) {
    
        if (iti) {
            let rawNumber = iti.getNumber();
            if(rawNumber) {
                iti.setNumber(rawNumber);
            }

            if (!iti.isValidNumber()) {
                e.preventDefault(); 
                alert('Invalid phone number or an error accured. Please re-enter your phone number.');
                $('#phone-number').css('border-color', 'red'); 
                return false; 
            }
        }
    });

    $('#email').on('blur', function() {
        let email = $(this).val();

        if (email.length < 1 ) {
                $('#email-message').css('color', 'red').text('Empty input');
                $('#email').css('border-color', 'red');
            }
        else{
            $('#email-message').css('color', 'green').text('');
            $('#email').css('border-color', 'var(--mid-dark-text-color)');
        }
    });
    $('#password').on('blur', function() {
        let pwd = $(this).val();

        if (pwd.length < 1 ) {
            $('#pwd-message').css('color', 'red').text('Empty input');
            $('#password').css('border-color', 'red');
        }
        else{
            $('#pwd-message').css('color', 'green').text('');
            $('#password').css('border-color', 'var(--mid-dark-text-color)');
        }
    });
    $('#confirm-password').on('blur', function() {
        let pwdConfirm = $(this).val();

        if (pwdConfirm.length < 1 ) {
            $('#pwdConfirm-message').css('color', 'red').text('Empty input');
            $('#confirm-password').css('border-color', 'red');
        }
        else{
            $('#pwdConfirm-message').css('color', 'green').text('');
            $('#confirm-password').css('border-color', 'var(--mid-dark-text-color)');
        }
    });

});
