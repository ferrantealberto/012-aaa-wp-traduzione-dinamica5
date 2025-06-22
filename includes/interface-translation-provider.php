<?php
/**
 * Interface for translation providers
 */

if (!defined('ABSPATH')) {
    exit;
}

interface DPT_Translation_Provider_Interface {
    public function translate($content, $source_lang = 'auto', $target_lang = 'en');
    public function detect_language($content);
    public function get_supported_languages();
    public function test_connection();
    public function get_usage_info();
    public function translate_batch($contents, $source_lang = 'auto', $target_lang = 'en');
}
