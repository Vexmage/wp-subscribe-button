<?php
/**
 * Plugin Name: ColorBliss Subscribe
 * Description: A lightweight subscription button with REST handler, honeypot, and rate limits. Adds shortcode [cb_subscribe].
 * Version: 1.0.0
 * Author: Joel Southall
 * License: MIT
 * Text Domain: colorbliss-subscribe
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

define( 'CB_SUBSCRIBE_VER', '1.0.0' );
define( 'CB_SUBSCRIBE_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_SUBSCRIBE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Filters so you don’t hard-code emails in your repo.
 *
 * cb_subscribe_to_email  -> destination mailbox
 * cb_subscribe_bcc_email -> optional Bcc for your own copy
 * cb_subscribe_from_name -> the "From" display name
 */
function cb_subscribe_get_to_email() {
  $default = 'replace-me@example.com'; // <-- safe placeholder for GitHub
  return apply_filters( 'cb_subscribe_to_email', $default );
}
function cb_subscribe_get_bcc_email() {
  $default = ''; // empty by default
  return apply_filters( 'cb_subscribe_bcc_email', $default );
}
function cb_subscribe_get_from_name() {
  $default = 'ColorBliss';
  return apply_filters( 'cb_subscribe_from_name', $default );
}

/**
 * Assets
 */
add_action( 'wp_enqueue_scripts', function() {
  // Only enqueue when shortcode is present.
  if ( ! is_singular() ) return;

  global $post;
  if ( $post && has_shortcode( $post->post_content, 'cb_subscribe' ) ) {
    wp_enqueue_style( 'cb-subscribe-style', CB_SUBSCRIBE_URL . 'assets/style.css', [], CB_SUBSCRIBE_VER );
    wp_enqueue_script( 'cb-subscribe-script', CB_SUBSCRIBE_URL . 'assets/script.js', [], CB_SUBSCRIBE_VER, true );

    // Pass REST URL to JS
    wp_add_inline_script(
      'cb-subscribe-script',
      'window.CB_SUBSCRIBE_ENDPOINT = ' . wp_json_encode( rest_url( 'colorbliss/v1/subscribe' ) ) . ';',
      'before'
    );
  }
} );

/**
 * Shortcode: [cb_subscribe]
 * Renders your star button + form + modal markup.
 */
add_shortcode( 'cb_subscribe', function( $atts ) {
  ob_start(); ?>
  <div id="cb-subscribe-scope" class="cb-inline" aria-expanded="false">
    <div class="cb-star" id="cb-star"
        role="button" tabindex="0"
        aria-controls="cb-subscribe-card" aria-expanded="false">
      <span class="cb-label">
        YOUR<br>FREE GIFT<br>HERE
      </span>

      <div class="cb-card-inner" id="cb-subscribe-card" aria-hidden="true">
        <div class="cb-form">
          <div class="cb-heading">Claim Your Gift — Subscribe Now!</div>
          <input type="text"  name="NAME"  placeholder="Your name"  aria-label="Name" required>
          <input type="email" name="EMAIL" placeholder="Your email" aria-label="Email address" required>
          <!-- honeypot (hidden) -->
          <input type="text" name="hp" id="cb-hp" autocomplete="off" tabindex="-1"
                style="position:absolute; left:-9999px; opacity:0;">
          <button type="submit" id="cb-submit">Go</button>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div class="cb-thanks-backdrop" id="cb-thanks" role="dialog" aria-modal="true" aria-hidden="true">
      <div class="cb-thanks-star">
        <button class="cb-thanks-close" type="button" aria-label="Close">×</button>
        <div class="cb-thanks-inner">
          <div class="cb-thanks-title">Thank you!</div>
          <div class="cb-thanks-msg">You’re on the list.</div>
        </div>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
} );

/**
 * REST endpoint (POST /wp-json/colorbliss/v1/subscribe)
 */
add_action( 'rest_api_init', function () {
  register_rest_route( 'colorbliss/v1', '/subscribe', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'args' => [
      'name'  => ['required' => true],
      'email' => ['required' => true],
      'hp'    => ['required' => false],
      'ts'    => ['required' => false], // hidden timestamp (ms since epoch)
    ],
    'callback' => function( WP_REST_Request $req ) {

      // --- 0) quick body-size guard (10KB) ---
      if ( isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 10 * 1024 ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'Payload too large.'], 413);
      }

      // --- 1) sanitize inputs ---
      $name  = sanitize_text_field( (string) $req->get_param('name') );
      $email = sanitize_email( (string) $req->get_param('email') );
      $hp    = sanitize_text_field( (string) $req->get_param('hp') );
      $ts    = preg_replace('/[^0-9]/', '', (string) $req->get_param('ts') );

      // --- 2) validate basic fields ---
      if ( $name === '' || ! is_email($email) ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'Invalid name or email.'], 400);
      }

      // --- 3) honeypot: if filled, pretend success (don’t reveal) ---
      if ( $hp !== '' ) {
        return new WP_REST_Response(['ok'=>true], 200);
      }

      // --- 4) minimal fill time (1.5s) to foil fast bots ---
      if ( $ts !== '' ) {
        $now_ms = (int) ( microtime(true) * 1000 );
        if ( ($now_ms - (int)$ts) < 1500 ) {
          return new WP_REST_Response(['ok'=>true], 200);
        }
      }

      // --- 5) rate limiting (per IP + per email) ---
      $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
      $email_key = 'cb_sub_em_' . md5(strtolower($email));
      $ip_key    = 'cb_sub_ip_' . md5($ip);

      // allow 1 every 30s per email, 10 per hour per IP
      if ( get_transient($email_key) ) {
        return new WP_REST_Response(['ok'=>true], 200);
      }
      $ip_count = (int) get_transient($ip_key);
      if ( $ip_count >= 10 ) {
        return new WP_REST_Response(['ok'=>true], 200);
      }

      // set/advance limits
      set_transient($email_key, 1, 30);           // 30s
      set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);

      // --- 6) build mail safely (strip CRLF from header fields) ---
      $clean_name  = trim(preg_replace("/[\r\n]+/", ' ', $name));
      $clean_email = trim(preg_replace("/[\r\n]+/", '', $email));

      $to       = cb_subscribe_get_to_email();
      $bcc      = cb_subscribe_get_bcc_email();
      $fromName = cb_subscribe_get_from_name();
      $subject  = 'New subscription';
      $body     = "Name: {$clean_name}\nEmail: {$clean_email}\nSite: " . home_url() . "\nTime: " . current_time('mysql') . "\n";

      $from_email = 'no-reply@' . parse_url(home_url(), PHP_URL_HOST);
      $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $clean_name . ' <' . $clean_email . '>',
        'From: ' . $fromName . ' <' . $from_email . '>',
      ];
      if ( $bcc ) {
        $headers[] = 'Bcc: ' . $bcc;
      }

      if ( ! wp_mail( $to, $subject, $body, $headers ) ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'Mail failed to send.'], 500);
      }

      return new WP_REST_Response(['ok'=>true], 200);
    }
  ]);
} );
