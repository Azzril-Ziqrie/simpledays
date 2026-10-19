<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Contact Us',
    'description' => 'Get in touch with ' . BRAND_NAME . ' about an order, sizing or anything else.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-contact', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <header class="doc-head reveal">
        <p class="doc-eyebrow">Contact</p>
        <h1 class="doc-title">Contact Us</h1>
      </header>

      <p class="doc-text reveal">For any questions or concerns regarding your privacy, you may contact us using the following details:</p>
      <p class="doc-text reveal">Simple Days<br><a href="mailto:contact@thesimpledays.co">contact@thesimpledays.co</a></p>
      <p class="doc-text reveal">It all begins with an idea. Maybe you want to launch a business. Maybe you want to turn a hobby into something more. Or maybe you have a creative project to share with the world. Whatever it is, the way you tell your story online can make all the difference.</p>
      <p class="doc-text reveal">Don’t worry about sounding professional. Sound like you. There are over 1.5 billion websites out there, but your story is what’s going to separate this one from the rest. If you read the words back and don’t hear your own voice in your head, that’s a good sign you still have more work to do.</p>
      <p class="doc-text reveal">Be clear, be confident and don’t overthink it. The beauty of your story is that it’s going to continue to evolve and your site can evolve with it. Your goal should be to make it feel right for right now. Later will take care of itself. It always does.</p>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
