<?php
// login_helpers.php

function getFailureDetails(string $code): array {
    // Lista de códigos válidos (agregamos 'user_blocked')
    $validCodes = [
        'user_not_found',
        'invalid_password',
        'account_inactive',
        'too_many_attempts',
        'ip_blocked',
        'invalid_email_format',
        'missing_fields',
        'user_blocked' // ✅ Agregado
    ];

    return [
        'code'   => in_array($code, $validCodes) ? $code : 'unknown_failure',
        'reason' => $code
    ];
}
