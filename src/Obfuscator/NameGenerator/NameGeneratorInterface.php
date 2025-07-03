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

interface NameGeneratorInterface
{
    /**
     * Configure the name generator with settings
     */
    public function configure(array $config): void;

    /**
     * Generate a new obfuscated name
     */
    public function generate(): string;

    /**
     * Reset the generator state
     */
    public function reset(): void;
}
