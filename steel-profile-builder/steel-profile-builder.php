<?php
/**
 * Plugin Name: Steel Profile Builder
 * Plugin URI: https://steel.ee
 * Description: Administ muudetav plekiprofiilide kalkulaator SVG visualiseerimise ja m²-põhise hinnastusega.
 * Version: 0.1.0
 * Author: Steel.ee
 */

if (!defined('ABSPATH')) {
  exit;
}

class Steel_Profile_Builder {
  const CPT = 'spb_profile';

  public function __construct() {
    add_action('init', [$this, 'register_cpt']);
  }

  public function register_cpt() {
    register_post_type(self::CPT, [
      'labels' => [
        'name' => 'Steel Profiilid',
        'singular_name' => 'Steel Profiil',
        'add_new' => 'Lisa uus',
        'add_new_item' => 'Lisa uus profiil',
        'edit_item' => 'Muuda profiili',
        'new_item' => 'Uus profiil',
        'view_item' => 'Vaata profiili',
        'search_items' => 'Otsi profiile',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'menu_icon' => 'dashicons-editor-kitchensink',
      'supports' => ['title'],
    ]);
  }
}

new Steel_Profile_Builder();
