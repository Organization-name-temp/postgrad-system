<?php

// Authentication lines. `failed` backs the login 422; the Accept-Language
// middleware selects this file (en) or lang/ar/auth.php (ar) per request.
return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'session_required' => 'A first-party SPA session is required.',
];
