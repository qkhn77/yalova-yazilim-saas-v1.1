(function ($) {
    "use strict";
	
	var $window = $(window); 
	var $body = $('body'); 

	/* Preloader Effect
	 * PageSpeed'te FCP/LCP'yi geciktirmemek icin preloader'i "load" yerine DOM hazır olunca kapatıyoruz.
	 */
	$(document).ready(function () {
		if ($(".preloader").length) {
			setTimeout(function () {
				$(".preloader").fadeOut(300);
			}, 200);
		}
	});

	/* Sticky Header */	
	if($('.active-sticky-header').length){
		$window.on('resize', function(){
			setHeaderHeight();
		});

		function setHeaderHeight(){
	 		$("header.main-header").css("height", $('header .header-sticky').outerHeight());
		}	
	
		$window.on("scroll", function() {
			var fromTop = $(window).scrollTop();
			setHeaderHeight();
			var headerHeight = $('header .header-sticky').outerHeight()
			$("header .header-sticky").toggleClass("hide", (fromTop > headerHeight + 100));
			$("header .header-sticky").toggleClass("active", (fromTop > 600));
		});
	}	
	
	/* Slick Menu JS */
	if ($.fn.slicknav && $('#menu').length) {
		$('#menu').slicknav({
			label : '',
			prependTo : '.responsive-menu',
			allowParentLinks: true
		});
	}

	if($("a[href='#top']").length){
		$(document).on("click", "a[href='#top']", function() {
			$("html, body").animate({ scrollTop: 0 }, "slow");
			return false;
		});
	}

	/* Hero Slider Layout JS */
	if (typeof window.Swiper !== 'undefined' && $('.hero-slider-layout .swiper').length) {
		const hero_slider_layout = new window.Swiper('.hero-slider-layout .swiper', {
			effect: 'fade',
			slidesPerView : 1,
			speed: 1000,
			spaceBetween: 0,
			loop: true,
			autoplay: {
				delay: 4000,
			},
			pagination: {
				el: '.hero-pagination',
				clickable: true,
			},
		});
	}

	/* testimonial Slider JS */
	if (typeof window.Swiper !== 'undefined' && $('.testimonial-slider').length) {
		const testimonial_slider = new window.Swiper('.testimonial-slider .swiper', {
			slidesPerView : 1,
			speed: 1000,
			spaceBetween: 30,
			loop: true,
			autoplay: {
				delay: 5000,
			},
			pagination: {
				el: '.testimonial-pagination',
				clickable: true,
			},
			navigation: {
				nextEl: '.testimonial-button-next',
				prevEl: '.testimonial-button-prev',
			},
			breakpoints: {
				768:{
					slidesPerView: 2,
				},
				991:{
					slidesPerView: 2,
				}
			}
		});
	}

	/* Team Certificates Slider JS */
	if (typeof window.Swiper !== 'undefined' && $('.team-certificates-slider').length) {
		const testimonial_slider = new window.Swiper('.team-certificates-slider .swiper', {
			slidesPerView : 2,
			speed: 1000,
			spaceBetween: 30,
			loop: true,
			autoplay: {
				delay: 5000,
			},
			pagination: {
				el: '.testimonial-pagination',
				clickable: true,
			},
			navigation: {
				nextEl: '.testimonial-button-next',
				prevEl: '.testimonial-button-prev',
			},
			breakpoints: {
				768:{
					slidesPerView: 3,
				},
				991:{
					slidesPerView: 3,
				}
			}
		});
	}

	/* Skill Bar */
	if ($.fn.waypoint && $('.skills-progress-bar').length) {
		$('.skills-progress-bar').waypoint(function() {
			$('.skillbar').each(function() {
				$(this).find('.count-bar').animate({
				width:$(this).attr('data-percent')
				},2000);
			});
		},{
			offset: '70%'
		});
	}

	/* Youtube Background Video JS */
	if ($.fn.YTPlayer && $('#herovideo').length) {
		var myPlayer = $("#herovideo").YTPlayer();
	}

	/* Init Counter */
	if ($.fn.counterUp && $('.counter').length) {
		$('.counter').counterUp({ delay: 6, time: 3000 });
	}

	/* Image Reveal Animation */
	if (typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined' && $('.reveal').length) {
        gsap.registerPlugin(ScrollTrigger);
        let revealContainers = document.querySelectorAll(".reveal");
        revealContainers.forEach((container) => {
            let image = container.querySelector("img");
            let tl = gsap.timeline({
                scrollTrigger: {
                    trigger: container,
                    toggleActions: "play none none none"
                }
            });
            tl.set(container, {
                autoAlpha: 1
            });
            tl.from(container, 1, {
                xPercent: -100,
                ease: Power2.out
            });
            tl.from(image, 1, {
                xPercent: 100,
                scale: 1,
                delay: -1,
                ease: Power2.out
            });
        });
    }

	/* Parallaxie js */
	var $parallaxie = $('.parallaxie');
	if($.fn.parallaxie && $parallaxie.length && ($window.width() > 991))
	{
		if ($window.width() > 768) {
			$parallaxie.parallaxie({
				speed: 0.55,
				offset: 0,
			});
		}
	}

	/* Zoom Gallery screenshot */
	if ($.fn.magnificPopup && $('.gallery-items').length) {
		$('.gallery-items').magnificPopup({
			delegate: 'a',
			type: 'image',
			closeOnContentClick: false,
			closeBtnInside: false,
			mainClass: 'mfp-with-zoom',
			image: {
				verticalFit: true,
			},
			gallery: {
				enabled: true
			},
			zoom: {
				enabled: true,
				duration: 300, // don't foget to change the duration also in CSS
				opener: function(element) {
				  return element.find('img');
				}
			}
		});
	}

	/* Contact form validation */
	var $contactform = $("#contactForm");
	if ($.fn.validator && $contactform.length) {
		$contactform.validator({focus: false}).on("submit", function (event) {
			return;
		});
	}
	/* Contact form validation end */

	/* Our Project (filtering) Start */
	$window.on( "load", function(){
		if( $.fn.isotope && $(".project-item-boxes").length ) {
				
			/* Init Isotope */
			var $menuitem = $(".project-item-boxes").isotope({
				itemSelector: ".project-item-box",
				layoutMode: "masonry",
				masonry: {
					// use outer width of grid-sizer for columnWidth
					columnWidth: 1,
				}
			});
				
			/* Filter items on click */
			var $menudisesnav = $(".our-Project-nav li a");
				$menudisesnav.on('click', function (e) { 
			
				var filterValue = $(this).attr('data-filter');
				$menuitem.isotope({
					filter: filterValue
				}); 
				
				$menudisesnav.removeClass("active-btn"); 
				$(this).addClass("active-btn");
				e.preventDefault();
			});		
			$menuitem.isotope({ filter: "*" });
		}			
	});
	/* Our Project (filtering) End */

	/* Animated Wow Js */	
	if (typeof window.WOW !== 'undefined') {
		new WOW().init();
	}

	/* Popup Video */
	if ($.fn.magnificPopup && $('.popup-video').length) {
		$('.popup-video').magnificPopup({
			type: 'iframe',
			mainClass: 'mfp-fade',
			removalDelay: 160,
			preloader: false,
			fixedContentPos: true
		});
	}
	
})(jQuery);
