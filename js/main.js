// If using a <script> tag for Accordion.js, ensure it is loaded before this file.
// Usage: const acc = new window.Accordion(element);

// --- Pixel Grid Control ---
let pixelGridAnimationHandles = [];

function clearPixelGrids() {
  // Stop all running animation timeouts/intervals
  pixelGridAnimationHandles.forEach((handle) => clearTimeout(handle));
  pixelGridAnimationHandles = [];
  // Clear all canvases
  const canvases = document.querySelectorAll('.pixel-grid');
  canvases.forEach((canvas) => {
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  });
}
/**
 * Form logic
 */
const form = document.getElementById("contact-form");
form.addEventListener("submit", formSubmit);

function formSubmit(e) {
  e.preventDefault();

  form.classList.add("submitting");

  document.querySelector("button").innerHTML = "Sending...";

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
    }),
  })
    .then(function (response) {
      console.log(response);
      document.querySelector("button").innerHTML = "Sent!";
      form.classList.remove("submitting");
      form.classList.add("submitted");
    })
    .catch(function (error) {
      console.error(error);
      document.querySelector("button").innerHTML = "Send";
      form.classList.remove("submitting");
    });
}

/**
 * Dividers
 *
 * Pixel Grid Animation
 */


// Pixel grid config (responsive, multi-canvas)
const PIXEL_SIZE = 30;
const ROWS = 5;

function randomColor() {
  // HSL: hue 220-280, sat 60-90%, light 40-60%
  const h = 220 + Math.random() * 60;
  const s = 60 + Math.random() * 30;
  const l = 40 + Math.random() * 20;
  return `hsl(${h},${s}%,${l}%)`;
}

function generateGrid(cols, direction = 'ltr') {
  const grid = [];
  const center = (cols - 1) / 2;
  for (let y = 0; y < ROWS; y++) {
    const row = [];
    for (let x = 0; x < cols; x++) {
      let blankProb;
      if (direction === 'center') {
        // More pixels in center, sparser toward edges
        blankProb = Math.pow(Math.abs(x - center) / center, 1.5) * 0.7 + 0.1; // 0.1 center, up to 0.8 edges
      } else {
        blankProb = 0.5 + Math.pow(x / (cols - 1), 1.5) * 0.5; // 0.5 left, up to 1.0 right
      }
      if (Math.random() > blankProb) {
        row.push({ visible: 1, color: null });
      } else {
        row.push({ visible: 0, color: null });
      }
    }
    grid.push(row);
  }
  return grid;
}

function setupRevealOrder(grid, cols, direction = 'ltr') {
  // direction: 'ltr', 'rtl', 'center'
  let revealOrder = [];
  let colIndices = Array.from({length: cols}, (_, i) => i);
  if (direction === 'rtl') colIndices = colIndices.reverse();
  else if (direction === 'center') {
    // Center-out: sort by distance from center
    const center = (cols - 1) / 2;
    colIndices.sort((a, b) => Math.abs(a - center) - Math.abs(b - center));
  }
  for (const x of colIndices) {
    const colPixels = [];
    for (let y = 0; y < ROWS; y++) {
      if (grid[y][x].visible) {
        colPixels.push([y, x]);
      }
    }
    // Shuffle pixels within this column
    for (let i = colPixels.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [colPixels[i], colPixels[j]] = [colPixels[j], colPixels[i]];
    }
    revealOrder.push(...colPixels);
  }
  return revealOrder;
}

function drawGrid(canvas, grid, revealOrder, revealedCount, cols) {
  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  for (let i = 0; i < revealOrder.length; i++) {
    if (i >= revealedCount) break;
    const [y, x] = revealOrder[i];
    const cell = grid[y][x];
    if (cell.visible) {
      if (!cell.color) cell.color = randomColor();
      ctx.fillStyle = cell.color;
      ctx.fillRect(x * PIXEL_SIZE, y * PIXEL_SIZE, PIXEL_SIZE, PIXEL_SIZE);
    }
  }
}

function animatePixelGrid(canvas, grid, revealOrder, cols) {
  let revealedCount = 0;
  let animating = true;
  const revealTime = 3000;
  const totalToReveal = revealOrder.length;
  const interval = totalToReveal > 0 ? Math.max(1, Math.floor(revealTime / totalToReveal)) : 1;
  let timeoutHandle;
  function step() {
    if (revealedCount < totalToReveal) {
      revealedCount++;
      drawGrid(canvas, grid, revealOrder, revealedCount, cols);
      timeoutHandle = setTimeout(step, interval);
      pixelGridAnimationHandles.push(timeoutHandle);
    } else {
      drawGrid(canvas, grid, revealOrder, totalToReveal, cols);
      animating = false;
    }
  }
  step();
}


function generatePixelGrids() {
  const canvases = document.querySelectorAll(".pixel-grid");
  canvases.forEach((canvas) => {
    // Get container width (parent of canvas)
    const container = canvas.parentElement;
    const width = Math.floor(container.offsetWidth / PIXEL_SIZE) * PIXEL_SIZE;
    canvas.width = width;
    canvas.height = ROWS * PIXEL_SIZE;
    const cols = Math.floor(width / PIXEL_SIZE);
    const directionAttr = canvas.getAttribute('data-direction');
    let direction = 'ltr';
    if (directionAttr === 'rtl') direction = 'rtl';
    else if (directionAttr === 'center') direction = 'center';
    const grid = generateGrid(cols, direction);
    const revealOrder = setupRevealOrder(grid, cols, direction);
    drawGrid(canvas, grid, revealOrder, 0, cols);
    // Only animate when in viewport
    if (!canvas._pixelGridObserver) {
      canvas._pixelGridObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting && !canvas._pixelGridAnimated) {
            animatePixelGrid(canvas, grid, revealOrder, cols);
            canvas._pixelGridAnimated = true;
            observer.unobserve(canvas);
          }
        });
      }, { threshold: 0.1 });
      canvas._pixelGridAnimated = false;
      canvas._pixelGridObserver.observe(canvas);
    }
  });
}

let resizeTimeout;

// Only regenerate pixel grids if width changes
let lastPixelGridWidth = null;
window.addEventListener("resize", () => {
  clearTimeout(resizeTimeout);
  const canvases = document.querySelectorAll(".pixel-grid");
  if (canvases.length === 0) return;
  // Use first canvas's parent as reference
  const container = canvases[0].parentElement;
  const width = Math.floor(container.offsetWidth / PIXEL_SIZE) * PIXEL_SIZE;
  if (width !== lastPixelGridWidth) {
    lastPixelGridWidth = width;
    clearPixelGrids();
    resizeTimeout = setTimeout(generatePixelGrids, 250);
  }
});

if (document.readyState !== "loading") generatePixelGrids();



/* Tech table */
const techs = document.getElementsByClassName("tech-block");
const containerWidth = document.getElementById("tech-chart").clientWidth;

for (tech of techs) {
  // calculate width of element and starting x
  const start = tech.getAttribute("data-start");
  let end = tech.getAttribute("data-end");

  const currentYear = new Date().getFullYear();
  const totalYears = currentYear - 2001;

  // if end isn't provided, get the current year
  if (end === "") {
    end = currentYear;
  }

  const startPerc = 100 - Math.round(((end - 2001) / totalYears) * 100);
  tech.style.right = startPerc + "%";

  const widthPerc = Math.round(((end - start) / totalYears) * 100);
  tech.style.width = widthPerc + "%";
}

/**
 * Animate elements when they enter the screen
 */
const intersectionCallback = (entries) => {
  for (const entry of entries) {
    // Loop over all elements that either enter or exit the view.
    if (entry.isIntersecting) {
      // This is true when the element is in view.
      entry.target.classList.add("show"); // Add a class.
    }
  }
};

const init = () => {
  const observer = new IntersectionObserver(intersectionCallback, {
    root: null, // avoiding 'root' or setting it to 'null' sets it to default value: viewport
    rootMargin: "0px",
    threshold: 0,
  });

  const items = document.querySelectorAll(".tech-block");
  for (const item of items) {
    observer.observe(item);
  }
};

// Initialise accordions
document.querySelectorAll('details').forEach((el) => {
  new Accordion(el);
});


// check if dom is loaded
if (document.readyState !== "loading") {
  init();
} else {
  document.addEventListener("DOMContentLoaded", function () {
    init();
  });
}

// Scroll tech-chart to the right on mobile
function scrollTechChartToRightIfMobile() {
  const chart = document.getElementById("tech-chart");
  if (!chart) return;
  // Match CSS mobile breakpoint (less than 740px)
  if (window.innerWidth < 740) {
    // Wait for layout
    setTimeout(() => {
      chart.scrollLeft = chart.scrollWidth;
    }, 0);
  }
}

if (document.readyState !== "loading") {
  scrollTechChartToRightIfMobile();
} else {
  document.addEventListener("DOMContentLoaded", scrollTechChartToRightIfMobile);
}

// --- Stuttery Scroll for # Links ---
function steppedScrollTo(targetY, steps = 8, stepTime = 50) {
  const startY = window.scrollY;
  const distance = targetY - startY;
  let currentStep = 0;
  function step() {
    currentStep++;
    const progress = currentStep / steps;
    // Ease: linear for retro effect
    const y = startY + distance * progress;
    window.scrollTo({ top: y });
    if (currentStep < steps) {
      setTimeout(step, stepTime);
    }
  }
  step();
}

document.addEventListener('click', function(e) {
  const a = e.target.closest('a[href^="#"]');
  if (!a) return;
  const href = a.getAttribute('href');
  if (href.length > 1) {
    const id = href.slice(1);
    const target = document.getElementById(id);
    if (target) {
      e.preventDefault();
      // Find target position (account for fixed headers if needed)
      const rect = target.getBoundingClientRect();
      const targetY = window.scrollY + rect.top;
      steppedScrollTo(targetY);
    }
  }
});
