

class Accordion {
  constructor(el) {
    // Store the <details> element
    this.el = el;
    // Store the <summary> element
    this.summary = el.querySelector('summary');
    // Store the <div class="content"> element
    this.content = el.querySelector('.content');

    // Store the animation object (so we can cancel it if needed)
    this.animation = null;
    // Store if the element is closing
    this.isClosing = false;
    // Store if the element is expanding
    this.isExpanding = false;
    // Detect user clicks on the summary element
    this.summary.addEventListener('click', (e) => this.onClick(e));
  }

  onClick(e) {
    // Stop default behaviour from the browser
    e.preventDefault();
    // Add an overflow on the <details> to avoid content overflowing
    this.el.style.overflow = 'hidden';
    // Check if the element is being closed or is already closed
    if (this.isClosing || !this.el.open) {
      this.open();
    // Check if the element is being openned or is already open
    } else if (this.isExpanding || this.el.open) {
      this.shrink();
    }
  }

  shrink() {
    this.isClosing = true;
    const startHeight = this.el.offsetHeight;
    const endHeight = this.summary.offsetHeight;
    const steps = 8;
    const stepTime = 50;
    let currentStep = 0;
    if (this.animation) {
      clearInterval(this.animation);
    }
    const stepFn = () => {
      currentStep++;
      const progress = currentStep / steps;
      const height = startHeight + (endHeight - startHeight) * progress;
      this.el.style.height = `${height}px`;
      if (currentStep >= steps) {
        clearInterval(this.animation);
        this.onAnimationFinish(false);
      }
    };
    this.animation = setInterval(stepFn, stepTime);
    stepFn();
  }

  open() {
    // Apply a fixed height on the element
    this.el.style.height = `${this.el.offsetHeight}px`;
    // Force the [open] attribute on the details element
    this.el.open = true;
    // Wait for the next frame to call the expand function
    window.requestAnimationFrame(() => this.expand());
  }

  expand() {
    this.isExpanding = true;
    const startHeight = this.el.offsetHeight;
    const endHeight = this.summary.offsetHeight + this.content.offsetHeight;
    const steps = 8;
    const stepTime = 50;
    let currentStep = 0;
    if (this.animation) {
      clearInterval(this.animation);
    }
    const stepFn = () => {
      currentStep++;
      const progress = currentStep / steps;
      const height = startHeight + (endHeight - startHeight) * progress;
      this.el.style.height = `${height}px`;
      if (currentStep >= steps) {
        clearInterval(this.animation);
        this.onAnimationFinish(true);
      }
    };
    this.animation = setInterval(stepFn, stepTime);
    stepFn();
  }

  onAnimationFinish(open) {
    this.el.open = open;
    this.animation = null;
    this.isClosing = false;
    this.isExpanding = false;
    this.el.style.height = '';
    this.el.style.overflow = '';
  }
}