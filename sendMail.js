$(document).ready(function() {

    $("#tempAlert").delay(3000).fadeOut(500, function() {
        $(this).remove();
    });

    var $btn = $('#sendBtn');

    var savedEndTime = localStorage.getItem('mail_cooldown_end');//halihazırda sayac var mı
            
    if (savedEndTime) {
        var currentTime = new Date().getTime();
                
        if (currentTime < savedEndTime) {//varsa devam ettir
            var remainingSeconds = Math.ceil((savedEndTime - currentTime) / 1000);
            runTimer(remainingSeconds);
        } else {
            localStorage.removeItem('mail_cooldown_end');//bitmisse temizle
        }
    }

    $('#mailForm').on('submit', function() {
        var endTime = new Date().getTime() + 60000;//simdi + 60 sn
        
        localStorage.setItem('mail_cooldown_end', endTime);//hafızaya kaydet
        
        $btn.prop('disabled', true);//butonu dondur
        $btn.html("Sending...");
    });

    function runTimer(timeLeft) {//geri sayım
        $btn.prop('disabled', true);
        $btn.html(`Wait (${timeLeft}s) before sending another request`);

        var timer = setInterval(function() {
            timeLeft--;
            $btn.html(`Wait (${timeLeft}s) before sending another request`);

            if (timeLeft <= 0) {
                clearInterval(timer); // Sayacı durdur
                $btn.prop('disabled', false); // Butonu aktifleştir
                $btn.html("Send Link"); // Orijinal yazıya dön
                localStorage.removeItem('mail_cooldown_end'); // Hafızayı temizle
            }
        }, 1000);
    }
});