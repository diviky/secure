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

namespace Diviky\Secure\Tests\Unit;

use Diviky\Secure\Obfuscator\NameGenerator\IdentifierNameGenerator;
use PHPUnit\Framework\TestCase;

class IdentifierNameGeneratorTest extends TestCase
{
    private IdentifierNameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new IdentifierNameGenerator;
    }

    public function test_generate_returns_valid_identifier(): void
    {
        $this->generator->configure(['scramble_mode' => 'identifier', 'scramble_length' => 8]);

        $name = $this->generator->generate();

        $this->assertIsString($name);
        $this->assertEquals(8, strlen($name));
        $this->assertMatchesRegularExpression('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
    }

    public function test_generate_hexadecimal(): void
    {
        $this->generator->configure(['scramble_mode' => 'hexadecimal', 'scramble_length' => 8]);

        $name = $this->generator->generate();

        $this->assertIsString($name);
        $this->assertEquals(9, strlen($name)); // 'h' prefix + 8 chars
        $this->assertMatchesRegularExpression('/^h[0-9a-f]+$/', $name);
    }

    public function test_generate_numeric(): void
    {
        $this->generator->configure(['scramble_mode' => 'numeric', 'scramble_length' => 6]);

        $name = $this->generator->generate();

        $this->assertIsString($name);
        $this->assertEquals(7, strlen($name)); // 'n' prefix + 6 chars
        $this->assertMatchesRegularExpression('/^n[0-9]+$/', $name);
    }

    public function test_configure_sets_properties(): void
    {
        $config = [
            'scramble_mode' => 'identifier',
            'scramble_length' => 12,
        ];

        $this->generator->configure($config);

        $name = $this->generator->generate();
        $this->assertEquals(12, strlen($name));
    }

    public function test_generate_creates_unique_names(): void
    {
        $this->generator->configure(['scramble_mode' => 'identifier', 'scramble_length' => 8]);

        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = $this->generator->generate();
        }

        $uniqueNames = array_unique($names);
        $this->assertGreaterThan(90, count($uniqueNames)); // Allow some collisions but not many
    }
}
