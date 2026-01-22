<?php
/**
 * Minimal autoloader for Steel Profile Builder
 * - No Composer needed
 * - No output
 * - Safe includes only if file exists
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Optional: map class names to files.
 * If you later move classes into /includes, add mappings here.
 *
 * Example:
 *  'SPB_Pdf_Generator' => __DIR__ . '/../includes/class-spb-pdf-generator.php',
 */
$SPB_CLASSMAP = [
  // 'ClassName' => __DIR__ . '/../includes/class-classname.php',
];

/**
 * PSR-4 style autoload for the "SPB_" prefix (optional).
 * If you create classes like SPB_Pdf_Generator, this can auto-map:
 *  SPB_Pdf_Generator -> /includes/class-spb-pdf-generator.php
 *
 * Rules:
 * - Class must start with "SPB_"
 * - File name becomes: /includes/class-spb-pdf-generator.php
 */
spl_autoload_register(function ($class) use ($SPB_CLASSMAP) {

  // 1) Explicit classmap first
  if (isset($SPB_CLASSMAP[$class])) {
    $file = $SPB_CLASSMAP[$class];
    if (is_string($file) && file_exists($file)) {
      require_once $file;
    }
    return;
  }

  // 2) Prefix-based autoload (SPB_)
  if (strpos($class, 'SPB_') !== 0) return;

  // Convert SPB_Pdf_Generator -> spb-pdf-generator
  $slug = strtolower(str_replace('_', '-', $class));
  $file = __DIR__ . '/../includes/class-' . $slug . '.php';

  if (file_exists($file)) {
    require_once $file;
  }
}, true, true);

