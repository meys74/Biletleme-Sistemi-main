//required fields errors
const settingsInputs = $(".settings-input");
const passwordInputs = $(".settings-input-password");
const save = $("#save");


save.prop("disabled" , true);
function checkForm() {
    let valid = true 

    settingsInputs.each(function(){
        if($(this).val().trim() === ""){
            valid = false;
        }
    });

    const currentPassword = $("#currentPassword").val().trim();
    const newPassword = $("#newPassword").val().trim();

    if(currentPassword !== "" || newPassword !=="") {

        if (currentPassword === "" || newPassword ==="") {
            valid = false;
        }
    }

    save.prop("disabled", !valid);
}

settingsInputs.each(function() {

    $(this).on("blur", function() {

        const profileError = $(this).next();

        if($(this).val().trim() === ""){
            profileError.text("This field is required!");
        }else{
            profileError.text("");
        }
        
    });
});
settingsInputs.on("input", checkForm);
passwordInputs.on("input", checkForm);
