function menuMobile() {
    var menu = document.getElementById("myLinks");
    var button = document.querySelector(".header-mobile .icon");
    var isOpen = menu.style.display === "block";

    menu.style.display = isOpen ? "none" : "block";
    menu.style.transition = "0.1s linear";
    if (button) button.setAttribute("aria-expanded", String(!isOpen));
}


var acc = document.getElementsByClassName("accordion");
var i;

for (i = 0; i < acc.length; i++) {
  acc[i].addEventListener("click", function() {
    this.classList.toggle("active");
    this.setAttribute("aria-expanded", String(this.classList.contains("active")));
    var panel = this.nextElementSibling;
    if (panel.style.maxHeight) {
      panel.style.maxHeight = null;
    } else {
      panel.style.maxHeight = panel.scrollHeight + "px";
    } 
  });
}
