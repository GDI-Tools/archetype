<?php

namespace Archetype\Vendor\Doctrine\DBAL\Tools\Console;

use Archetype\Vendor\Composer\InstalledVersions;
use Archetype\Vendor\Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Archetype\Vendor\Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Exception;
use Archetype\Vendor\Symfony\Component\Console\Application;
use Archetype\Vendor\Symfony\Component\Console\Command\Command;
use function assert;
/**
 * Handles running the Console Tools inside Symfony Console context.
 *
 * @deprecated Use Symfony Console documentation to bootstrap a command-line application.
 */
class ConsoleRunner
{
    /**
     * Runs console with the given connection provider.
     *
     * @param Command[] $commands
     *
     * @return void
     *
     * @throws Exception
     */
    public static function run(ConnectionProvider $connectionProvider, $commands = [])
    {
        $version = InstalledVersions::getVersion('doctrine/dbal');
        assert($version !== null);
        $cli = new Application('Doctrine Command Line Interface', $version);
        $cli->setCatchExceptions(\true);
        self::addCommands($cli, $connectionProvider);
        $cli->addCommands($commands);
        $cli->run();
    }
    /** @return void */
    public static function addCommands(Application $cli, ConnectionProvider $connectionProvider)
    {
        $cli->addCommands([new RunSqlCommand($connectionProvider), new ReservedWordsCommand($connectionProvider)]);
    }
    /**
     * Prints the instructions to create a configuration file
     *
     * @deprecated This method will be removed without replacement.
     *
     * @return void
     */
    public static function printCliConfigTemplate()
    {
        echo <<<'HELP'
You are missing a "cli-config.php" or "config/cli-config.php" file in your
project, which is required to get the Doctrine-DBAL Console working. You can use the
following sample as a template:

<?php
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;

// You can append new commands to $commands array, if needed

// replace with the mechanism to retrieve DBAL connection(s) in your app
// and return a Doctrine\DBAL\Tools\Console\ConnectionProvider instance.
$connection = getDBALConnection();

// in case you have a single connection you can use SingleConnectionProvider
// otherwise you need to implement the Doctrine\DBAL\Tools\Console\ConnectionProvider interface with your custom logic
return new SingleConnectionProvider($connection);

HELP;
    }
}
