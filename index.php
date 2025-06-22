<?php
/**
 * File di protezione directory
 * Previene l'accesso diretto alle directory del plugin
 */

// Previene accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Redirect sicuro alla homepage
wp_safe_redirect(home_url());
exit;