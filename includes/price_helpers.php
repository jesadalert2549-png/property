<?php
// ฟังก์ชันช่วยเรนเดอร์ราคาพร้อมขีดฆ่าและป้ายส่วนลด (Price Strikethrough & Discount Badge)
function render_price_badge(int $price, ?int $originalPrice, string $type = 'sale', string $size = 'normal'): string
{
    $isRent = $type === 'rent';
    $unit = is_en() ? ($isRent ? '/mo' : '') : ($isRent ? '/เดือน' : '');
    $formattedCurrent = '฿' . number_format($price) . $unit;

    // มีราคาเดิม และราคาเดิมไม่เท่ากับราคาปัจจุบัน (ไม่ว่าจะลดลง หรือปรับขึ้น ให้ขีดฆ่าราคาเก่า)
    if ($originalPrice !== null && $originalPrice > 0 && $originalPrice !== $price) {
        $formattedOrig = '฿' . number_format($originalPrice) . $unit;
        $isDiscount = $originalPrice > $price;
        $diff = abs($originalPrice - $price);
        $diffPct = round(($diff / $originalPrice) * 100);

        if ($isDiscount) {
            $badgeHtml = '<span class="price-discount-pill">-' . $diffPct . '% ' . e(t('ลดราคา', 'OFF')) . '</span>';
            $badgeMini = '<span class="price-discount-badge">-' . $diffPct . '%</span>';
        } else {
            $badgeHtml = '<span class="price-updated-pill">+' . $diffPct . '% ' . e(t('ปรับราคาใหม่', 'Updated')) . '</span>';
            $badgeMini = '<span class="price-updated-badge">' . e(t('ราคาใหม่', 'New Price')) . '</span>';
        }

        if ($size === 'large') {
            return '<div class="price-strikethrough-wrap large">'
                . '<div class="price-old-row">'
                . '<span class="price-orig-label">' . e(t('ราคาเดิม:', 'Orig:')) . '</span> '
                . '<span class="price-orig-strikethrough">' . e($formattedOrig) . '</span>'
                . $badgeHtml
                . '</div>'
                . '<div class="price-current-large">' . e($formattedCurrent) . '</div>'
                . '</div>';
        }

        return '<div class="price-strikethrough-wrap">'
            . '<span class="price-current">' . e($formattedCurrent) . '</span>'
            . '<span class="price-orig-strikethrough">' . e($formattedOrig) . '</span>'
            . $badgeMini
            . '</div>';
    }

    if ($size === 'large') {
        return '<div class="price-current-large">' . e($formattedCurrent) . '</div>';
    }
    return '<span class="price-current">' . e($formattedCurrent) . '</span>';
}


// ฟังก์ชันช่วยเรนเดอร์ชิปแสดงยอดวิวและยอดคลิก (Card Stats Pill)
function render_card_stats_badge(int $views, int $clicks, string $position = 'card'): string
{
    $formattedViews = number_format($views);
    $formattedClicks = number_format($clicks);

    if ($position === 'detail') {
        return '<div class="property-stats-detail-pill">'
            . '<span class="stat-pill-item" title="' . e(t('ยอดเปิดดูประกาศนี้', 'Total Views')) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> ' . $formattedViews . ' ' . e(t('ดู', 'views')) . '</span>'
            . '<span class="stat-pill-divider">·</span>'
            . '<span class="stat-pill-item" title="' . e(t('ยอดคลิกเข้าชม', 'Total Clicks')) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m13 2 9 9-4 4-5-5-4 4-3-3 7-9Z"/></svg> ' . $formattedClicks . ' ' . e(t('คลิก', 'clicks')) . '</span>'
            . '</div>';
    }

    return '<div class="property-card-stats-badge" title="' . e(t('ดู ' . $formattedViews . ' ครั้ง · คลิก ' . $formattedClicks . ' ครั้ง', 'Views: ' . $formattedViews . ' · Clicks: ' . $formattedClicks)) . '">'
        . '<span class="stat-badge-views"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> ' . $formattedViews . '</span>'
        . '<span class="stat-badge-clicks"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m13 2 9 9-4 4-5-5-4 4-3-3 7-9Z"/></svg> ' . $formattedClicks . '</span>'
        . '</div>';
}
