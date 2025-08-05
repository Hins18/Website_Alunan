// Fungsi untuk tahun, biarkan saja
function getYear() {
    var currentDate = new Date();
    var currentYear = currentDate.getFullYear();
    if (document.querySelector("#displayYear")) {
        document.querySelector("#displayYear").innerHTML = currentYear;
    }
}
getYear();

// Fungsi untuk Isotope filter menu, biarkan saja
$(window).on('load', function () {
    $('.filters_menu li').click(function () {
        $('.filters_menu li').removeClass('active');
        $(this).addClass('active');

        var data = $(this).attr('data-filter');
        var $grid = $(".grid");
        if ($grid.length) {
            $grid.isotope({ filter: data });
        }
    });

    if ($(".grid").length) {
        $(".grid").isotope({
            itemSelector: ".all",
            percentPosition: false,
            masonry: {
                columnWidth: ".all"
            }
        });
    }
});

// nice select, biarkan saja
$(document).ready(function() {
    if (typeof $.fn.niceSelect === 'function') {
        $('select').niceSelect();
    }
});

// client section owl carousel, biarkan saja
if (typeof $.fn.owlCarousel === 'function') {
    $(".client_owl-carousel").owlCarousel({
        loop: true,
        margin: 0,
        dots: false,
        nav: true,
        navText: [
            '<i class="fa fa-angle-left" aria-hidden="true"></i>',
            '<i class="fa fa-angle-right" aria-hidden="true"></i>'
        ],
        autoplay: true,
        autoplayHoverPause: true,
        responsive: {
            0: { items: 1 },
            768: { items: 2 },
            1000: { items: 2 }
        }
    });
}

// --- KODE CHATBOT BARU (HAPUS YANG LAMA, GANTI DENGAN INI) ---
document.addEventListener("DOMContentLoaded", function() {

    const chatbotToggleButton = document.getElementById("chatbot-toggle-btn");
    const chatbotContainer = document.getElementById("chatbot-container");
    const chatbotCloseButton = document.getElementById("chatbot-close-btn");
    const chatbotOptions = document.querySelectorAll(".chatbot-option");
    const chatbotBody = document.getElementById("chatbotBody");

    // Jika salah satu elemen penting tidak ditemukan, hentikan eksekusi
    if (!chatbotToggleButton || !chatbotContainer || !chatbotCloseButton) {
        console.error("Error: Elemen penting chatbot tidak ditemukan. Periksa kembali ID di file index.php!");
        return; // Menghentikan script jika elemen tidak ada
    }

    // Fungsi untuk menampilkan/menyembunyikan jendela chat
    function toggleChatbot() {
        const isVisible = chatbotContainer.style.display === "flex";
        chatbotContainer.style.display = isVisible ? "none" : "flex";
    }

    // Tambahkan event listener ke tombol
    chatbotToggleButton.addEventListener('click', toggleChatbot);
    chatbotCloseButton.addEventListener('click', toggleChatbot);

    // --- LOGIKA UNTUK MENJAWAB PESAN ---

    // Fungsi untuk menambahkan bubble chat
    function addMessage(messageText, sender) {
        const messageContainer = document.createElement('div');
        messageContainer.className = `chatbot-message ${sender}-message`;
        
        const messageBubble = document.createElement('p');
        messageBubble.textContent = messageText;
        
        messageContainer.appendChild(messageBubble);
        chatbotBody.appendChild(messageContainer);
        chatbotBody.scrollTop = chatbotBody.scrollHeight; // Auto scroll
    }

    // Database jawaban bot
    const responses = {
        "jam_buka": "Kami buka setiap hari dari jam 11.00 - 23.00.",
        "lokasi": "Lokasi kami ada di Jl. Sersan Idris No.1, Marga Jaya, Kec. Bekasi Sel., Kota Bks, Jawa Barat 17141.",
        "promo": "Saat ini belum ada promo yang tersedia. Pantau terus media sosial kami untuk update terbaru!",
        "default": "Maaf, saya tidak mengerti. Silakan pilih salah satu opsi yang tersedia."
    };

    // Tambahkan event listener untuk setiap tombol opsi
    chatbotOptions.forEach(function(option) {
        option.addEventListener('click', function() {
            const questionKey = this.dataset.question;
            const userMessage = this.textContent;
            
            addMessage(userMessage, 'user');

            // Tunda jawaban bot selama setengah detik agar terlihat alami
            setTimeout(function() {
                const botResponse = responses[questionKey] || responses['default'];
                addMessage(botResponse, 'bot');
            }, 500);
        });
    });
});