<?php
/*
 * Private Community Website Software
 * Copyright (c) 2026 Ole Rasmussen
 *
 * Free to use, copy, modify, fork, and distribute.
 *
 * NOT allowed:
 * - Selling this software
 * - Redistributing it for profit
 *
 * Provided "AS IS" without warranty.
 */
/**
 * functions/cache.php — Session-based flash message helpers
 */

declare(strict_types=1);

/**
 * Flash messages stored in session.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $messages;
}

function flash_render(): string
{
    $messages = flash_get();
    if (empty($messages)) {
        return '';
    }
    $html = '';
    foreach ($messages as $msg) {
        $type = in_array($msg['type'], ['success', 'error', 'info', 'warning'], true) ? $msg['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '">' . e($msg['message']) . '</div>';
    }
    return $html;
}
