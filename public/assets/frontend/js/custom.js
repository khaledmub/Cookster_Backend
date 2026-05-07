//Tooltip
$(function () {
  $('[data-bs-toggle="tooltip"]').tooltip()
});

//Scroll Header
$(window).scroll(function() {     
  var scroll = $(window).scrollTop();
  if (scroll > 0) {
    $("#main_header").addClass("add_shadow");
  }
  else {
    $("#main_header").removeClass("add_shadow");
  }
});

// Back To Top Button
var back_to_top_btn = $('#back_to_top_btn');
$(window).scroll(function() {
  if ($(window).scrollTop() > 300) {
    back_to_top_btn.addClass('show');
  } else {
    back_to_top_btn.removeClass('show');
  }
});
back_to_top_btn.on('click', function(e) {
  e.preventDefault();
  $('html, body').animate({scrollTop:0}, '300');
});

// Banner Swiper
const banners_swiper = new Swiper('.banners_swiper', {
  slidesPerView: 1,
  // autoplay: true,
  navigation: {
    nextEl: '.banners_swiper_next',
    prevEl: '.banners_swiper_prev'
  },
  pagination: {
    el: '.banners_pagination',
    clickable: true
  }
});

// Top Dresses Swiper
const top_dresses_swiper = new Swiper('.top_dresses_swiper', {
  slidesPerView: 2,
  spaceBetween: 30,
  // autoplay: true,
  breakpoints: {
    482: {
    slidesPerView: 3,
    },
    836: {
    slidesPerView: 4,
    },
    993: {
    slidesPerView: 5,
    },
    1200: {
    slidesPerView: 6,
    },
  }
});

// Product Detail Swiper
const pro_img_swiper = new Swiper('.pro_img_swiper', {
  slidesPerView: 1,
  // autoplay: true,
  navigation: {
    nextEl: '.banners_swiper_next',
    prevEl: '.banners_swiper_prev'
  },
  pagination: {
    el: '.banners_pagination',
    clickable: true
  }
});


const pro_gallery_img_swiper = new Swiper('.pro_gallery_img_swiper', {
  slidesPerView: 1,
  spaceBetween: 10,
  // autoplay: true,
  breakpoints: {
    482: {
    slidesPerView: 2,
    },
    836: {
    slidesPerView: 2,
    },
    993: {
    slidesPerView: 3,
    },
    1200: {
    slidesPerView: 3,
    },
  }

});



$(document).ready(function() {
  $('.minus').click(function () {
    var $input = $(this).parent().find('input');
    var count = parseInt($input.val()) - 1;
    count = count < 1 ? 1 : count;
    $input.val(count);
    $input.change();
    return false;
  });
  $('.plus').click(function () {
    var $input = $(this).parent().find('input');
    $input.val(parseInt($input.val()) + 1);
    $input.change();
    return false;
  });

  $('.whychooseus_accordion_btn').on('click', function() {
    var accordionno = $(this).attr('data-accordionno');
    $('.whychooseus_accordion_img').hide();
    $('#whychooseus_accordion_img' + accordionno).show();
  });
});

//Product Images Swiper + Light Gallery
let product_images_swiper_gallery = document.getElementById('product_images_swiper_gallery');
var product_images_thumbs_swiper = new Swiper('.product_images_thumbs_swiper', {
	spaceBetween: 10,
	slidesPerView: 4,
	freeMode: true,
	watchSlidesProgress: true
});
var product_images_swiper = new Swiper('.product_images_swiper', {
	spaceBetween: 10,
  navigation: {
    nextEl: ".pro_b_slider_swiper_next",
    prevEl: ".pro_b_slider_swiper_prev",
  },
	thumbs: {
  	swiper: product_images_thumbs_swiper
	},
  on: {
    init: function () {
      const lg = lightGallery(product_images_swiper_gallery, {
        plugins: [lgZoom, lgAutoplay, lgThumbnail, lgFullscreen, lgRotate, lgShare],
        thumbnail: true,
        selector: '.lg_selector',
        mobileSettings: {showCloseIcon: true, download: true}
      });
      product_images_swiper_gallery.addEventListener('lgBeforeClose', () => {
        product_images_swiper.slideTo(lg.index, 0);
      });
    }
  }
});

// document.addEventListener('DOMContentLoaded', function () {
//   // Function to fetch metal prices based on the selected currency
//   function fetchMetalPrices(currency) {
//     const apiKey = '6a55c29d01e3f437cceea661555463ef'; // Replace with your actual API key
//     const apiUrl = `https://api.metalpriceapi.com/v1/latest?api_key=${apiKey}&base=${currency}`;

//     fetch(apiUrl)
//       .then(response => {
//         // Check if the response is OK
//         if (!response.ok) {
//           throw new Error(`HTTP error! status: ${response.status}`);
//         }
//         return response.json();
//       })
//       .then(data => {
//         console.log("data :",data);
        
//         // Update metal prices in the DOM
//         const goldRate = data.rates;
//         const silverRate = data.rates['Silver(USDXAG)'];
//         console.log("goldRate :",goldRate);

//         document.getElementById('gold_oz').textContent = goldRate.toFixed(2);
//         document.getElementById('gold_g').textContent = (goldRate * 31.1035).toFixed(2);
//         document.getElementById('silver_oz').textContent = silverRate.toFixed(2);
//         document.getElementById('silver_g').textContent = (silverRate * 31.1035).toFixed(2);
//       })
//       .catch(error => {
//         // Handle errors
//         console.error('Error fetching metal prices:', error);
//         alert('Unable to fetch metal prices. Please try again later.');
//       });
//   }

//   // Set up event listener for currency dropdown
//   const currencySelect = document.getElementById('currency');
//   currencySelect.addEventListener('change', function () {
//     fetchMetalPrices(this.value);
//   });

//   // Fetch initial metal prices using the default selected currency
//   fetchMetalPrices(currencySelect.value);
// });


if (typeof(stockdio_events) == "undefined") {
  stockdio_events = true;
  var stockdio_eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
  var stockdio_eventer = window[stockdio_eventMethod];
  var stockdio_messageEvent = stockdio_eventMethod == "attachEvent" ? "onmessage" : "message";
  stockdio_eventer(stockdio_messageEvent, function (e) {
     if (typeof(e.data) != "undefined" && typeof(e.data.method) != "undefined") {
        eval(e.data.method);
     }
  },false);
}

$(document).ready(function() {
  var baseurl = $('#baseurl').val();
  var language = $('#language').val();
  var pageName = $('#pageName').val();
  $("#contact_btn_submit").click(function(e){
      e.preventDefault();
      var name = $('#contactForm').find('input[name="name"]').val();
      var email = $('#contactForm').find('input[name="email"]').val();
      var phone = $('#contactForm').find('input[name="phone"]').val();
      var message = $('#contactForm').find('textarea[name="message"]').val();
      if(name == '' || email == '' || phone == '' || message == ''){
        swal.fire({
          title: $('#error_translated_text').val(),
          text: $('#please_fill_all_the_fields_translated_text').val(),
          icon: 'error',
          confirmButtonText: $('#ok_translated_text').val()
        });
      }
      else{
          $("#contact_btn_submit").prop('disabled', true);
          $.ajax({
              url: baseurl + "ajax/submit_contact_us",
              type:'POST',
              headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
              data: {name:name, email:email, phone:phone, message:message},
              success: function(data) {
                      
                  $("#contact_btn_submit").prop('disabled', false);
                  if(data.status==true){
                      $('#contactForm')[0].reset();
                      swal.fire({
                        title: $('#success_translated_text').val(),
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: $('#ok_translated_text').val()
                      });
                  }
                  else if(data.status==false){
                      swal.fire({
                        title: $('#error_translated_text').val(),
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: $('#ok_translated_text').val()
                      });
                  }
              }
          });
      }
  });
});