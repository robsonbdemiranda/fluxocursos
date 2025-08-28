$('.slide-curso .slider').slick({
    infinite: true,
    slidesToShow: 3,
    slidesToScroll: 1,
    autoplay: true,
    autoplaySpeed: 2000,
    arrows: true,
    prevArrow: '<button type="button" class="slick-prev"><i class="fa fa-arrow-left" aria-hidden="true"></i></button>',
    nextArrow: '<button type="button" class="slick-next"><i class="fa fa-arrow-right" aria-hidden="true"></i></button>',
    responsive: [
        {
          breakpoint: 1024,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 1,
          }
        },
        {
        breakpoint: 768,
        settings: {
            slidesToShow: 1,
            slidesToScroll: 1,
        }
        }
    ]
  });