<?php

declare(strict_types=1);

const APP_VERSION = '1.0.0';
const DEBUG_MODE = true;

function calculateTotal(array $items): float
{
    $total = 0.0;
    $taxRate = 0.1;

    foreach ($items as $item) {
        $itemPrice = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $subtotal = $itemPrice * $quantity;
        $total += $subtotal;
    }

    return applyTax($total, $taxRate);
}

function applyTax(float $amount, float $rate): float
{
    return $amount * (1 + $rate);
}

function formatCurrency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function validateEmail(string $email): bool
{
    $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    return preg_match($pattern, $email) === 1;
}

function generateRandomString(int $length = 10): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $randomString;
}

function logMessage(string $message, string $level = 'info'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}";

    // In a real application, this would write to a log file
    echo $logEntry . PHP_EOL;
}
