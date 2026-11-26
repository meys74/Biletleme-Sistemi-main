//required fields errors
const ticketInput = $("#ticket-title, #ticket-description");
const submit = $("#submit");

submit.prop("disabled", true);
ticketInput.each(function() {

    $(this).on("blur", function ()  {

        const inputError = $(this).next();

        if($(this).val().trim() === ""){
            inputError.text("This field is required!");
        }else{
            inputError.text("");
        }
    });

    $(this).on("input", function () {

        const inputError = $(this).next();

        if($(this).val().trim() === ""){
            inputError.text("This field is required!");
        }else{
            inputError.text("");
        }
        
        let i = 0;
        ticketInput.each(function(){

            if($(this).val().trim() !=="") {
                i++;
            }
        });
        
        if(ticketInput.length === i){
            submit.prop("disabled" , false);
        }else{
            submit.prop("disabled" , true);
        };
    });
});