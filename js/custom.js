// to get current year
function getYear() {
    var currentDate = new Date();
    var currentYear = currentDate.getFullYear();
    document.querySelector("#displayYear").innerHTML = currentYear;
}

getYear();


// isotope js
$(window).on('load', function () {
    $('.filters_menu li').click(function () {
        $('.filters_menu li').removeClass('active');
        $(this).addClass('active');

        var data = $(this).attr('data-filter');
        $grid.isotope({
            filter: data
        })
    });

    var $grid = $(".grid").isotope({
        itemSelector: ".all",
        percentPosition: false,
        masonry: {
            columnWidth: ".all"
        }
    })
});

// nice select
$(document).ready(function() {
    $('select').niceSelect();
  });

/** google_map js **/
function myMap() {
    var mapProp = {
        center: new google.maps.LatLng(40.712775, -74.005973),
        zoom: 18,
    };
    var map = new google.maps.Map(document.getElementById("googleMap"), mapProp);
}
// JS Modal
document.getElementById('loginBtn').onclick = function(event) {
    event.preventDefault();
    document.getElementById('loginModal').style.display = 'block';
  };
  
  document.querySelector('.modal .close').onclick = function() {
    document.getElementById('loginModal').style.display = 'none';
  };
  
  window.onclick = function(event) {
    if (event.target === document.getElementById('loginModal')) {
      document.getElementById('loginModal').style.display = 'none';
    }
  };

// client section owl carousel
$(".client_owl-carousel").owlCarousel({
    loop: true,
    margin: 0,
    dots: false,
    nav: true,
    navText: [],
    autoplay: true,
    autoplayHoverPause: true,
    navText: [
        '<i class="fa fa-angle-left" aria-hidden="true"></i>',
        '<i class="fa fa-angle-right" aria-hidden="true"></i>'
    ],
    responsive: {
        0: {
            items: 1
        },
        768: {
            items: 2
        },
        1000: {
            items: 2
        }
    }
});

$(document).ready(function() {
    // ... (kode JavaScript Anda yang sudah ada)

    // Chatbot Logic
    const chatbotToggleButton = $('#chatbotToggleButton');
    const chatbotContainer = $('#chatbotContainer');
    const chatbotCloseBtn = $('#chatbotCloseBtn');
    const chatbotOptions = $('.chatbot-option');
    const chatbotBody = $('#chatbotBody');

    const responses = {
        "jam_buka": "Kami buka setiap hari dari jam 11.00 - 23.00.",
        "lokasi": "Lokasi kami ada di Jl. Sersan Idris No.1, RT.003/RW.004, Marga Jaya, Kec. Bekasi Sel., Kota Bks, Jawa Barat 17141.",
        "promo": "Saat ini belum ada promo yang tersedia. Pantau terus media sosial kami untuk update terbaru!",
        "default": "Maaf, saya tidak mengerti. Silakan pilih salah satu opsi yang tersedia."
    };

    function toggleChatbot() {
        chatbotContainer.slideToggle();
    }

    function addMessage(message, sender) {
        const messageClass = sender === 'user' ? 'user-message' : 'bot-message';
        const messageElement = `<div class="chatbot-message ${messageClass}"><p>${message}</p></div>`;
        chatbotBody.append(messageElement);
        chatbotBody.scrollTop(chatbotBody[0].scrollHeight); // Auto-scroll to bottom
    }

    chatbotToggleButton.on('click', toggleChatbot);
    chatbotCloseBtn.on('click', toggleChatbot);

    chatbotOptions.on('click', function() {
        const question = $(this).data('question');
        const userMessage = $(this).text();
        const botResponse = responses[question] || responses['default'];

        addMessage(userMessage, 'user');

        setTimeout(function() {
            addMessage(botResponse, 'bot');
        }, 500);
    });
});
