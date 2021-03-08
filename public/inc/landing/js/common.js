// MAIN PAGE

$('a.registration').css('display', 'none'); 

$(window).scroll(function () {
        
    if ($(this).scrollTop() >= 100) {
        $('.menu').addClass("fixed");
        $('a.registration').css('display', 'inline-block'); 
        
    } else {
         $('.menu').removeClass("fixed");
         $('a.registration').css('display', 'none'); 
    }
});
    
$(function () {
    
     $('.menu_block_mobile ul').css('display', 'none');    
     $(".mobile_menu_open").click(function () {
       $(".menu_block_mobile ul").slideToggle('slow'); 
     });
    
});   

// LOGIN PAGE

$(function () {
    
     $('.menu_block_mobile_second ul').css('display', 'none');    
     $(".mobile_menu_open_second").click(function () {
       $(".menu_block_mobile_second ul").slideToggle('slow'); 
     });
    
});  
    
// BACK TO TOP (MAIN PAGE)

$('a.back_to_top').css('display', 'none'); 

$(document).ready(function(){ 
   $(window).scroll(function(){ 
       if ($(this).scrollTop() > 300) { 
           $('a.back_to_top').fadeIn(); 
       } else { 
           $('a.back_to_top').fadeOut(); 
       } 
   }); 
   $('a.back_to_top').click(function(){ 
       $("html, body").animate({ scrollTop: 0 }, 600); 
       return false; 
   }); 
});

$('#submit-form-button').click(function () {
    $(this).prop('disabled', true);
    $("ul[id$='errors']").html('');
    let data = $('#form_contact').serialize();
    $.ajax({
        url: '/connect',
        type: 'POST',
        data: data,
        success: function (result) {
            $('#form').html('<h3>Ваша заявка принята</h3>');
        },
        error: function (result) {
            let errors = JSON.parse(result.responseText);
            if (errors.length === 0) {
                $('#form_errors').html("<li class='error'>Что-то пошло не так. Попробуйте позже</li>");
            } else {
                $.each(errors, function (key, obj) {
                    $(`#${key}_errors`).html("<li class='error'>" + obj + "</li>");
                });
            }
        },
        complete: function () {
            $('#submit-form-button').prop('disabled', false);
        }
    });
})






