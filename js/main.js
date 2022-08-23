const form = document.getElementById("contact-form");

form.addEventListener("submit", formSubmit);

function formSubmit(e) {
    e.preventDefault()

    form.classList.add("submitting");

    document.querySelector('button').innerHTML = "Sending...";

    fetch("https://submit-form.com/4e6uud2z", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify({
            name: document.querySelector('input[name="name"]').value,
            email: document.querySelector('input[name="email"]').value,
            message: document.querySelector('textarea[name="message"]').value,
            "g-recaptcha-response": grecaptcha.getResponse()
        }),
    })
        .then(function (response) {
            console.log(response);
            document.querySelector('button').innerHTML = "Sent!";
            form.classList.remove("submitting");
            form.classList.add("submitted");
        })
        .catch(function (error) {
            console.error(error);
            document.querySelector('button').innerHTML = "Send";
            form.classList.remove("submitting");
        });
}

/* Tech table */
const techs = document.getElementsByClassName("tech-block");
const containerWidth = document.getElementById("tech-chart").clientWidth;

for (tech of techs) {
    // calculate width of element and starting x
    const start = tech.getAttribute('data-start');
    let end = tech.getAttribute('data-end');

    const currentYear = new Date().getFullYear();
    const totalYears = currentYear - 2001;

    // if end isn't provided, get the current year
    if (end === "") {
        end = currentYear;
    }

    const startPerc = 100 - Math.round(((end - 2001) / totalYears) * 100);
    tech.style.left = startPerc + "%";

    const widthPerc = Math.round(((end - start) / totalYears) * 100);
    tech.style.width = widthPerc + "%";
}

/**
 * Animate elements when they enter the screen
 */
const intersectionCallback = (entries) => {
    for (const entry of entries) { // Loop over all elements that either enter or exit the view.
        if (entry.isIntersecting) { // This is true when the element is in view.
            entry.target.classList.add('show'); // Add a class.
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const observer = new IntersectionObserver(intersectionCallback, {
        root: null, // avoiding 'root' or setting it to 'null' sets it to default value: viewport
        rootMargin: '0px',
        threshold: 0
    });

    const items = document.querySelectorAll('.tech-block');
    for (const item of items) {
        observer.observe(item);
    }
});