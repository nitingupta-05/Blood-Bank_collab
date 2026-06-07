<?php
/**
 * Decide where to send a user after login based on their role.
 */
function redirect_after_login(string $role): string {
    return match ($role) {
        'donor'    => 'home',
        'patient'  => 'find-blood',
        'hospital' => 'emergency',
        default    => 'dashboard',
    };
}
