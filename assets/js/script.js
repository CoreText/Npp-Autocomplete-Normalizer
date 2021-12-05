
const links = document.querySelectorAll('.scroll-to, .other-diff');

for (const link of links) {
  link.addEventListener('click', clickHandler);
}

function clickHandler(e) {
  e.preventDefault();
  const href = this.getAttribute('href');
  const offsetTop = document.querySelector(href).offsetTop;

  scroll({
    top: offsetTop,
    behavior: 'smooth'
  });
}

var targets = document.querySelectorAll('.sticky-header, .scroll-to-top-btn');

;(function (headers) {
  var stickyFor = document.querySelector('.other-diff'),
      stickyForOffset = stickyFor.offsetTop

  window.onscroll = function () {
    myStickyHeaders()
  };

  function myStickyHeaders() {
    if (!'IntersectionObserver' in window) {
      for (var header of Array.from(headers)) {
        myStickyHeader(header);
      }
    }
    else {
      const observer = new IntersectionObserver(handleIntersection);
      observer.observe(document.getElementById('first'));
    }
  }

  // Add the sticky class to the header when you reach its scroll position.
  // Remove "sticky" when you leave the scroll position
  function myStickyHeader(header) {
    var sticky = header.offsetTop

    if (window.pageYOffset > sticky &&
      window.pageYOffset > stickyForOffset) {
      header.classList.add('sticky')
    }
    else {
      header.classList.remove('sticky')
    }
  }

  function handleIntersection(entries) {
    const headers = document.querySelectorAll('.other-diff .sticky-header, .scroll-to-top-btn')
    entries.map((entry) => {
      for (var header of Array.from(headers)) {
        if (entry.isIntersecting) {
          header.classList.remove('sticky')
        }
        else {
          header.classList.add('sticky')
        }
        
      }
    });
  }
})(targets);


/**
 * @details uses diff js library
 */
function launch() {
  var text1 = document.getElementById('left').value;
  var text2 = document.getElementById('right').value;
  
  var dmp = new diff_match_patch();
  dmp.Diff_Timeout = 0;

  // No warmup loop since it risks triggering an 'unresponsive script' dialog
  // in client-side JavaScript
  var ms_start = (new Date()).getTime();
  var d = dmp.diff_main(text1, text2, false);
  var ms_end = (new Date()).getTime();

  var ds = dmp.diff_prettyHtml(d);
  document.getElementById('outputdiv').innerHTML = ds + '<br>Time: ' + (ms_end - ms_start) / 1000 + 's';
}

/*
hljs.initHighlightingOnLoad();
if ( typeof oldIE === 'undefined' && Object.keys && typeof hljs !== 'undefined') {
  hljs.initHighlighting();
}
document.querySelectorAll('.code').forEach(el => {
  hljs.highlightElement(el);
});
*/
