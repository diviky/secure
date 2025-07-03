<?php

declare(strict_types=1);

/*
 * This file is part of the Diviky Secure package.
 *
 * (c) Sankar <sankar@diviky.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Diviky\Secure\Obfuscator\NameGenerator;

class IdentifierNameGenerator implements NameGeneratorInterface
{
    private string $mode = 'identifier';

    private int $length = 8;

    private int $counter = 0;

    private const IDENTIFIER_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';

    private const IDENTIFIER_CHARS_EXTENDED = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    private const HEX_CHARS = '0123456789abcdef';

    private const NUMERIC_CHARS = '0123456789';

    public function configure(array $config): void
    {
        $this->mode = $config['scramble_mode'] ?? 'identifier';
        $this->length = $config['scramble_length'] ?? 8;
        $this->counter = 0;
    }

    public function generate(): string
    {
        return match ($this->mode) {
            'identifier' => $this->generateIdentifier(),
            'hexadecimal' => $this->generateHexadecimal(),
            'numeric' => $this->generateNumeric(),
            default => $this->generateIdentifier()
        };
    }

    public function reset(): void
    {
        $this->counter = 0;
    }

    private function generateIdentifier(): string
    {
        $name = '';

        // First character must be a letter or underscore (not a number)
        $firstChars = str_replace(range('0', '9'), '', self::IDENTIFIER_CHARS);
        $name .= $firstChars[random_int(0, strlen($firstChars) - 1)];

        // Remaining characters can include numbers
        for ($i = 1; $i < $this->length; $i++) {
            $name .= self::IDENTIFIER_CHARS_EXTENDED[random_int(0, strlen(self::IDENTIFIER_CHARS_EXTENDED) - 1)];
        }

        return $name;
    }

    private function generateHexadecimal(): string
    {
        $name = '';
        for ($i = 0; $i < $this->length; $i++) {
            $name .= self::HEX_CHARS[random_int(0, strlen(self::HEX_CHARS) - 1)];
        }

        return 'h'.$name; // Prefix with 'h' to make it a valid identifier
    }

    private function generateNumeric(): string
    {
        $name = '';
        // First digit cannot be 0 for a proper number
        $name .= self::NUMERIC_CHARS[random_int(1, strlen(self::NUMERIC_CHARS) - 1)];

        for ($i = 1; $i < $this->length; $i++) {
            $name .= self::NUMERIC_CHARS[random_int(0, strlen(self::NUMERIC_CHARS) - 1)];
        }

        return 'n'.$name; // Prefix with 'n' to make it a valid identifier
    }
}
