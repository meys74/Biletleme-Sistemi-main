const container = $(".profile-image-container");
const input = $("#profileImage");
const photo = $("#profilePhoto");
const topPhoto = $("#topImg");
const customerTopPhoto = $("#customerTopImg");

container.on("click", function () {
    input[0].click();
});

input.on("change", function(){
    const file = this.files[0];
    if(file){
        const imageURL = URL.createObjectURL(file);
        photo.attr("src" ,  imageURL);
        topPhoto.attr("src" ,  imageURL);
        customerTopPhoto.attr("src" ,  imageURL);
    }
});