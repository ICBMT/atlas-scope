<?php

declare(strict_types=1);

namespace Atlas\Scope\Support;

use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * php-parser, whatever generation is installed.
 *
 * The parser is what reads a scanned project's PHP — routes, migrations, class
 * declarations — so the package cannot be picky about which version of it a host
 * has. php-parser 5 renamed its entry point (`createForNewestSupportedVersion`)
 * and dropped the old constants; 4.x spells it `create(ParserFactory::PREFER_PHP7)`.
 * Both are supported, so a Laravel 10-era application with the older parser
 * installed works exactly like a new one.
 */
final class Ast
{
    public static function parser(): Parser
    {
        $factory = new ParserFactory;

        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            return $factory->createForNewestSupportedVersion();
        }

        // php-parser 4. The constant does not exist in 5.x — which is fine,
        // because this branch never runs when 5.x is installed.
        return $factory->create(ParserFactory::PREFER_PHP7);
    }
}
