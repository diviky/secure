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

namespace Diviky\Secure\Console;

use Diviky\Secure\Console\Command\AnalyzeCommand;
use Diviky\Secure\Console\Command\InitCommand;
use Diviky\Secure\Console\Command\ObfuscateCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    private const VERSION = '3.0.0';

    public function __construct()
    {
        parent::__construct('Diviky Secure', self::VERSION);

        $this->addCommands([
            new InitCommand,
            new AnalyzeCommand,
            new ObfuscateCommand,
        ]);
    }
}
