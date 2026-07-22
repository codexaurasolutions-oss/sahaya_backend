$(document).ready(function () {

 
    // Detect scroll direction
    let lastScrollTop = 0;

    $(window).on("scroll", function () {
        let scrollTop = $(this).scrollTop();

        if (scrollTop > lastScrollTop) {
            // Scrolling Down
            $('.bottom-scroll').removeClass('scroll-active');
            $('.top-scroll').addClass('scroll-active');
        } else if (scrollTop < lastScrollTop) {
            // Scrolling Up
            $('.top-scroll').removeClass('scroll-active');
            $('.bottom-scroll').addClass('scroll-active');
        }

        lastScrollTop = scrollTop; // Update lastScrollTop
    });

    // Handle click event on top-scroll
    $('.bottom-scroll').on('click', function () {
        $(window).trigger('pageup');
    });

    // Handle click event on bottom-scroll
    $('.top-scroll').on('click', function () {
        $(window).trigger('pagedown');
    });

    // Define actions for the pagedown and pageup events
    $(window).on('pageup', function () {
        console.log('Page Up event triggered');
        $('html, body').animate({ scrollTop: 0 }, 500); // Scroll to the top
    });

    $(window).on('pagedown', function () {
        console.log('Page Down event triggered');
        $('html, body').animate({ scrollTop: $(document).height() }, 500); // Scroll to the bottom
    });
 

  // new WOW().init();

  var HeadH = $('#header').outerHeight();
  // $('body').css('padding-top', HeadH);

  var scrollWindow = function () {
    $(window).on('load scroll', function () {
      var navbar = $('#header');

      // if ($(this).scrollTop() > HeadH) {
      if ($(this).scrollTop() > 250) {
        if (!navbar.hasClass('is-sticky')) {
          navbar.addClass('is-sticky');
          $('body').css('padding-top', HeadH);
        }
      }
      // if ($(this).scrollTop() < HeadH) {
      if ($(this).scrollTop() < 250) {
        if (navbar.hasClass('is-sticky')) {
          navbar.removeClass('is-sticky');
          $('body').css('padding-top', 0);
        }
      }
      // if ($(this).scrollTop() > HeadH*2) {
      if ($(this).scrollTop() > 250) {
        if (!navbar.hasClass('awake')) {
          navbar.addClass('awake');
        }
      }
      // if ($(this).scrollTop() < HeadH*2) {
      if ($(this).scrollTop() < 50) {
        if (navbar.hasClass('awake')) {
          navbar.removeClass('awake');
        }
      }
      // if ($(this).scrollTop() >= 400) { 
      //     $('.back_top').addClass('active');
      // }
      // else {
      //     $('.back_top').removeClass('active');
      // }
    });
  };
  scrollWindow();


  var btn = $('#top-button');

  $(window).scroll(function () {
    if ($(window).scrollTop() > 300) {
      btn.addClass('show');
    } else {
      btn.removeClass('show');
    }
  });

  btn.on('click', function (e) {
    e.preventDefault();
    $('html, body').animate({ scrollTop: 0 }, '300');
  });

  // $('.back_top').click(function(){
  //     $('html, body').animate({scrollTop:0}, 500);
  // });

  // $('.back_top').click(function () {
  //     $('html, body').animate({ scrollTop: 0 }, 500);
  // });

  // $(window).scroll(function () {
  //     if ($(this).scrollTop() > 100) {
  //         $('.back_top').fadeIn();
  //     } else {
  //         $('.back_top').fadeOut();
  //     }
  // });

  $(".navbar-toggler").click(function () {
    $(this).toggleClass("menu-opened");
    $("#header .navcollapse:not(.show)").toggleClass("menu-show");
    $("body").toggleClass("scroll-off");
    $(".overlay").fadeToggle();
  });

  $(".overlay").click(function () {
    $(this).fadeToggle();
    $("#header .navcollapse:not(.show)").toggleClass("menu-show");
    $("body").toggleClass("scroll-off");
    $(".navbar-toggler").toggleClass("menu-opened");
  });


  $(window).on("resize", function (e) {
    checkScreenSize();
  });
  var logo = $(".navbar-brand img").attr("src");

  checkScreenSize();
  function checkScreenSize() {
    var newWindowWidth = $(window).width();
    if (newWindowWidth <= 991) {
      $("#header .navcollapse:not(.show)").find(".mobile_logo").remove();
      $("#header .navcollapse:not(.show)").append("<div class='mobile_logo'>" + "<img src=" + logo + " alt=''>" + "</div>");
    }
  }


  /* ======= Scroll back to top ======= */
  var progressPath = document.querySelector('.progress-wrap .progress-circle path');
  if (!progressPath) return; // Exit if the element is not found

  var pathLength = progressPath.getTotalLength();
  progressPath.style.transition = progressPath.style.WebkitTransition = 'none';
  progressPath.style.strokeDasharray = pathLength + ' ' + pathLength;
  progressPath.style.strokeDashoffset = pathLength;
  progressPath.getBoundingClientRect();
  progressPath.style.transition = progressPath.style.WebkitTransition = 'stroke-dashoffset 10ms linear';

  var updateProgress = function () {
    var scroll = window.scrollY;
    var height = document.documentElement.scrollHeight - window.innerHeight;
    var progress = pathLength - (scroll * pathLength / height);
    progressPath.style.strokeDashoffset = progress;
  }

  updateProgress();
  window.addEventListener('scroll', updateProgress);

  var offset = 150;
  var duration = 550;
  window.addEventListener('scroll', function () {
    if (window.scrollY > offset) {
      document.querySelector('.progress-wrap').classList.add('active-progress');
    } else {
      document.querySelector('.progress-wrap').classList.remove('active-progress');
    }
  });

  document.querySelector('.progress-wrap').addEventListener('click', function (event) {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return false;
  });

  // End

  //Dashboard Menu
  $(function () {
    var $nav = $('nav.greedy');
    var $btn = $('nav.greedy button');
    var $vlinks = $('nav.greedy .links');
    var $hlinks = $('nav.greedy .hidden-links');

    var numOfItems = 0;
    var totalSpace = 0;
    var breakWidths = [];

    // Get initial state
    $vlinks.children().outerWidth(function (i, w) {
      totalSpace += w;
      numOfItems += 1;
      breakWidths.push(totalSpace);
    });

    var availableSpace, numOfVisibleItems, requiredSpace;

    function check() {

      // Get instant state
      availableSpace = $vlinks.width() - 10;
      numOfVisibleItems = $vlinks.children().length;
      requiredSpace = breakWidths[numOfVisibleItems - 1];

      // There is not enought space
      if (requiredSpace > availableSpace) {
        $vlinks.children().last().prependTo($hlinks);
        numOfVisibleItems -= 1;
        check();
        // There is more than enough space
      } else if (availableSpace > breakWidths[numOfVisibleItems]) {
        $hlinks.children().first().appendTo($vlinks);
        numOfVisibleItems += 1;
      }
      // Update the button accordingly
      $btn.attr("count", numOfItems - numOfVisibleItems);
      if (numOfVisibleItems === numOfItems) {
        $btn.addClass('hidden');
      } else $btn.removeClass('hidden');
    }

    // Window listeners
    $(window).resize(function () {
      check();
    });

    $btn.on('click', function () {
      $hlinks.toggleClass('hidden');
    });

    check();

  });



  // $('.box-loader').fadeOut('slow');

  var Wheight = $(window).height();
  var Hheight = $('#header').outerHeight();
  var Fheight = $('.footer_wrapper').outerHeight();

  var Innheight = Wheight - (Fheight + Hheight);

  $('.cms_section').css('min-height', Innheight);
});