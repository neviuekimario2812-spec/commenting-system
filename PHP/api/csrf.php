<?php
// Gives the browser a CSRF token to include in forms.

require_once __DIR__ . '/../includes/functions.php';

json_response(['csrf_token' => generate_csrf_token()]);