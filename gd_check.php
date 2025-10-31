<?php
header('Content-Type: text/plain; charset=utf-8');

echo "GD loaded: " . (extension_loaded('gd') ? "YES" : "NO") . PHP_EOL;
echo "Imagick loaded: " . (extension_loaded('imagick') ? "YES" : "NO") . PHP_EOL;

if (function_exists('gd_info')) {
    print_r(gd_info()); // details (version, formats, etc.)
} else {
    echo "gd_info() unavailable (GD not loaded)".PHP_EOL;
}
