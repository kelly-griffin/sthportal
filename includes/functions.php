<?php
// Creates a Link to Admin Button
function goToAdminBtn($label = 'Go to Admin') {
    // Detect protocol
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    
    // Figure out base path from current script dir
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    
    // Force it to point to /admin/ in that base
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/admin/';
    
    // Add icon before label
    $icon = '⚙'; // replace with <img> or SVG if desired
    
    // Return styled button
    return '<div style="margin-top:1rem">'
         . '<a class="btn" href="' . $url . '">'
         . $icon . ' ' . htmlspecialchars($label)
         . '</a>'
         . '</div>';
}