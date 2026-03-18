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
 * functions/pagination.php — Pagination helpers
 */

declare(strict_types=1);

/**
 * Return paginated results.
 *
 * @param string $sql     Base SQL without LIMIT/OFFSET
 * @param array  $params
 * @param int    $page
 * @param int    $perPage
 * @return array{rows: array, total: int, pages: int, page: int}
 */
function paginate(string $sql, array $params, int $page = 1, int $perPage = 20): array
{
    $page    = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset  = ($page - 1) * $perPage;

    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count_q';
    $total    = (int) db_val($countSql, $params);
    $pages    = (int) ceil($total / $perPage);

    $rows = db_query($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

    return compact('rows', 'total', 'pages', 'page');
}

/**
 * Render pagination links.
 */
function pagination_links(int $current, int $total, string $baseUrl): string
{
    if ($total <= 1) {
        return '';
    }

    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $total; $i++) {
        $active = ($i === $current) ? ' active' : '';
        $sep    = str_contains($baseUrl, '?') ? '&' : '?';
        $html  .= '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '" class="page-link' . $active . '">' . $i . '</a> ';
    }
    $html .= '</nav>';
    return $html;
}
