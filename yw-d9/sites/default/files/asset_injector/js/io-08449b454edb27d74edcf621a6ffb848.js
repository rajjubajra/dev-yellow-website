const watch = document.getElementById("watch");
const arrow = document.getElementById("arrow");
const scrollToTop = document.getElementById("scroll-to-top");

const options = {
  root: null, // it is a viewport
  threshold: 0,
  rootMargin: '0px 0px -300px 0px',
}

const observer = new IntersectionObserver(function(entries, observer){
  
  entries[0].isIntersecting ? arrow.classList.add('hidden') : arrow.classList.remove("hidden");
  entries[0].isIntersecting ? scrollToTop.classList.remove('hidden') : scrollToTop.classList.add('hidden');
  
  
}, options);

observer.observe(watch);
