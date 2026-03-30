<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$flash = client_area_get_flash();
$client = null;
$setupError = null;
$isAjaxRequest = (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

$respond = static function (string $type, string $message, int $statusCode = 200) use ($isAjaxRequest): never {
  if ($isAjaxRequest) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
      'type' => $type,
      'message' => $message,
    ]);
    exit;
  }

  client_area_set_flash($type, $message);
  client_area_redirect('/account/');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!client_area_verify_csrf($_POST['csrf_token'] ?? null)) {
    $respond('error', 'Your session expired. Please refresh the page and try again.', 419);
    }

    if (client_area_is_rate_limited()) {
    $respond('error', 'Please wait a moment before requesting another link.', 429);
    }

    client_area_touch_rate_limit();

    if (!client_area_is_configured()) {
    $respond('error', 'Client area setup is incomplete.', 503);
    }

    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $respond('error', 'Enter a valid email address.', 422);
    }

    try {
        $pdo = client_area_db();
        client_area_cleanup_expired_tokens($pdo);
        $matchedClient = client_area_find_client_by_email($pdo, $email);

        if ($matchedClient !== null) {
            $token = client_area_issue_magic_link($pdo, $matchedClient);
            client_area_send_magic_link($matchedClient, $token);
        }

        $respond('success', 'Email sent! Check your inbox for a link.');
    } catch (Throwable $throwable) {
        $respond('error', 'We could not send a sign-in link right now.', 500);
    }
}

if (!client_area_is_configured()) {
    $setupError = 'Add config/client-area.php with your database and mail settings to enable the client area.';
} else {
    try {
        $currentClientId = client_area_client_id();

        if ($currentClientId !== null) {
            $client = client_area_find_client_by_id(client_area_db(), $currentClientId);

            if ($client === null) {
                client_area_logout();
                $flash = [
                    'type' => 'error',
                    'message' => 'Your session has expired. Please request a new sign-in link.',
                ];
            }
        }
    } catch (Throwable $throwable) {
        $setupError = 'The client area could not connect to the database.';
    }
}

$hasHosting = false;
$hasMaintenance = false;
$hostingDetails = [];
$maintenanceDetails = [];

if ($client !== null) {
  $hostingDetails = [
    'Location' => trim((string) ($client['hosting_location'] ?? '')),
    'Storage' => !empty($client['hosting_storage_gb']) ? ((string) $client['hosting_storage_gb'] . ' GB') : '',
    'CPU' => !empty($client['hosting_cpu_cores']) ? ((string) $client['hosting_cpu_cores'] . ' cores') : '',
    'Memory' => !empty($client['hosting_memory_gb']) ? ((string) $client['hosting_memory_gb'] . ' GB') : '',
    'Payment cycle' => trim((string) ($client['hosting_payment_cycle'] ?? '')),
    'Cost' => trim((string) ($client['hosting_cost'] ?? '')),
    'Last payment date' => trim((string) ($client['hosting_last_payment_date'] ?? '')),
    'Custom notes' => trim((string) ($client['hosting_custom_text'] ?? '')),
  ];

  $maintenanceDetails = [
    'Type' => trim((string) ($client['maintenance_type'] ?? '')),
    'Cost' => trim((string) ($client['maintenance_cost'] ?? '')),
    'Last payment date' => trim((string) ($client['maintenance_last_payment_date'] ?? '')),
    'Billing frequency' => trim((string) ($client['maintenance_billing_frequency'] ?? '')),
  ];

  foreach ($hostingDetails as $value) {
    if ($value !== '') {
      $hasHosting = true;
      break;
    }
  }

  foreach ($maintenanceDetails as $value) {
    if ($value !== '') {
      $hasMaintenance = true;
      break;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Customer Area | Rohan Latimer</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta
      name="description"
      content="Customer area"
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Lato:wght@300;900&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/main.css?v=1.2" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
  </head>
  <body>
    <header class="main-header boxed">
      <div class="title">
        <div><a href="/">Rohan Latimer</a></div>
        <div role="doc-subtitle" class="subtitle">Software Developer</div>
      </div>
      <div class="header-links">
        <nav aria-label="Primary">
          <a href="/account/logout.php">Log out</a>
        </nav>
        <div class="github">
          <a href="https://github.com/hcw-rohan" target="_blank" rel="noopener"
            ><img src="../img/github.png" width="32" height="32" alt="GitHub"
          /></a>
        </div>
      </div>
    </header>

    <main class="client-area boxed">
      <header class="client-area-header">
        <h1>Customer area</h1>
      </header>

      <?php if ($setupError !== null): ?>
      <div class="notice notice-error"><?= client_area_h($setupError) ?></div>
      <?php endif; ?>

      <?php if ($flash !== null): ?>
      <div class="notice notice-<?= client_area_h($flash['type']) ?>">
        <?= client_area_h($flash['message']) ?>
      </div>
      <?php endif; ?>

      <div id="account-notice" aria-live="polite"></div>

      <?php if ($client === null): ?>
      <section class="client-panel">
        <h2>Email Sign-In</h2>
        <p>Enter your email address and a one-time login link will be sent to you.</p>
        <form method="post" class="client-login-form" id="account-login-form">
          <input type="hidden" name="csrf_token" value="<?= client_area_h(client_area_csrf_token()) ?>" />
          <label for="client-email">Email</label>
          <input
            type="email"
            id="client-email"
            name="email"
            required
            autocomplete="email"
          />
          <button class="button" type="submit"<?= $setupError !== null ? ' disabled' : '' ?>>Send Magic Link</button>
        </form>
      </section>
      <?php else: ?>
      <section class="client-panel">
        <div class="client-meta">
          <h2><?= client_area_h($client['name'] ?: 'Account Overview') ?></h2>
          <p><?= client_area_h($client['email']) ?></p>
        </div>

        <?php if (!$hasHosting && !$hasMaintenance): ?>
        <div class="plan-card plan-card-empty">
          <h3>No Active Services</h3>
          <p>There is currently no hosting or maintenance service assigned to this account.</p>
        </div>
        <?php else: ?>
        <div class="plan-grid">
          <?php if ($hasHosting): ?>
          <article class="plan-card">
            <h3>Hosting</h3>
            <dl class="service-details">
              <?php foreach ($hostingDetails as $label => $value): ?>
              <?php if ($value !== ''): ?>
              <div class="service-row">
                <dt><?= client_area_h($label) ?></dt>
                <dd><?= client_area_h($value) ?></dd>
              </div>
              <?php endif; ?>
              <?php endforeach; ?>
            </dl>
          </article>
          <?php endif; ?>

          <?php if ($hasMaintenance): ?>
          <article class="plan-card">
            <h3>Maintenance</h3>
            <dl class="service-details">
              <?php foreach ($maintenanceDetails as $label => $value): ?>
              <?php if ($value !== ''): ?>
              <div class="service-row">
                <dt><?= client_area_h($label) ?></dt>
                <dd><?= client_area_h($value) ?></dd>
              </div>
              <?php endif; ?>
              <?php endforeach; ?>
            </dl>

            <h4 class="maintenance-heading">Typical cycle tasks</h4>
            <ul class="maintenance-tasks">
              <li>Core and plugin updates</li>
              <li>Uptime and basic performance checks</li>
              <li>Security monitoring and remediation</li>
              <li>Backup verification and restore spot checks</li>
              <li>Priority support for minor changes and fixes</li>
            </ul>
          </article>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <details class="contact-accordion" id="account-contact-accordion">
          <summary><h3>Issues? Get in touch</h3></summary>
          <div class="content">
            <form id="account-contact-form" class="account-contact-form">
              <label for="contact-name">Name</label>
              <input type="text" id="contact-name" name="name" required value="<?= client_area_h($client['name'] ?? '') ?>" />
              <label for="contact-email">Email</label>
              <input type="email" id="contact-email" name="email" required value="<?= client_area_h($client['email'] ?? '') ?>" />
              <input type="checkbox" name="phone" style="display:none" tabindex="-1" autocomplete="off" />
              <label for="contact-message">Message</label>
              <textarea id="contact-message" name="message" required rows="4"></textarea>
              <button type="submit">Send</button>
              <div id="contact-notice" aria-live="polite" class="contact-notice"></div>
            </form>
          </div>
        </details>
      </section>
      <?php endif; ?>
    </main>
    <script src="../js/Accordion.js"></script>
    <script>
      (function () {
        const form = document.getElementById("account-login-form");
        if (!form) {
          return;
        }

        const notice = document.getElementById("account-notice");
        const submitButton = form.querySelector('button[type="submit"]');
        const defaultButtonText = submitButton ? submitButton.textContent : "Send Magic Link";

        function renderNotice(type, message) {
          if (!notice) {
            return;
          }

          notice.className = "notice notice-" + (type === "success" ? "success" : "error");
          notice.textContent = message;
        }

        form.addEventListener("submit", async function (event) {
          event.preventDefault();

          if (!submitButton) {
            return;
          }

          submitButton.disabled = true;
          submitButton.textContent = "Sending...";

          try {
            const response = await fetch(form.getAttribute("action") || window.location.pathname, {
              method: "POST",
              headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
              },
              body: new FormData(form)
            });

            let payload = null;
            try {
              payload = await response.json();
            } catch (error) {
              payload = null;
            }

            const type = payload && payload.type ? payload.type : (response.ok ? "success" : "error");
            const message = payload && payload.message
              ? payload.message
              : "Something went wrong. Please try again.";

            renderNotice(type, message);

            if (type === "success") {
              form.reset();
            }
          } catch (error) {
            renderNotice("error", "We could not submit your request. Please try again.");
          } finally {
            submitButton.disabled = false;
            submitButton.textContent = defaultButtonText;
          }
});
      })();

      // Contact accordion
      const contactAccordion = document.getElementById("account-contact-accordion");
      if (contactAccordion) {
        new Accordion(contactAccordion);
      }

      // Contact form submission
      (function () {
        const contactForm = document.getElementById("account-contact-form");
        if (!contactForm) return;

        const contactNotice = document.getElementById("contact-notice");
        const contactButton = contactForm.querySelector('button[type="submit"]');

        contactForm.addEventListener("submit", async function (e) {
          e.preventDefault();

          contactForm.classList.add("submitting");
          if (contactButton) contactButton.textContent = "Sending...";

          try {
            const response = await fetch("https://submit-form.com/4e6uud2z", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
              },
              body: JSON.stringify({
                name: contactForm.querySelector('[name="name"]').value,
                email: contactForm.querySelector('[name="email"]').value,
                message: contactForm.querySelector('[name="message"]').value,
              }),
            });

            if (response.ok) {
              if (contactButton) contactButton.textContent = "Sent!";
              contactForm.classList.remove("submitting");
              contactForm.classList.add("submitted");
              if (contactNotice) {
                contactNotice.className = "contact-notice notice notice-success";
                contactNotice.textContent = "Message sent successfully.";
              }
            } else {
              throw new Error("Non-OK response");
            }
          } catch (error) {
            if (contactButton) contactButton.textContent = "Send";
            contactForm.classList.remove("submitting");
            if (contactNotice) {
              contactNotice.className = "contact-notice notice notice-error";
              contactNotice.textContent = "Could not send your message. Please try again.";
            }
          }
        });
      })();
    </script>
  </body>
</html>
