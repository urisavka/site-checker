<?php

namespace SiteChecker\Commands;

use Psr\Log\LogLevel;
use SiteChecker\Asset;
use SiteChecker\Config;
use SiteChecker\ConsoleObserver;
use SiteChecker\SiteChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

define('CONFIG_PATH', '/../../../config/app.json');

/**
 * Class CheckCommand
 * @package SiteChecker\Commands
 */
class CheckCommand extends Command
{

    protected function configure()
    {
        $this->setName("site-checker:check")
            ->setDescription("Display the fibonacci numbers between 2 given numbers")
            ->setDefinition([
                new InputArgument('site', InputArgument::REQUIRED),
                new InputOption('check-external', 'e', InputOption::VALUE_NONE,
                    'Check external links'),
                new InputOption('log-success', 's', InputOption::VALUE_NONE,
                    'Log successful page loads'),
                new InputOption('full-html', 'f', InputOption::VALUE_NONE,
                    'Show full html tag of element in log'),
            ])
            ->setHelp(<<<EOT
Checks a site for broken links and missing files (CSS, js, images)

Usage:

<info> sitechecker http://site.url</info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $headerStyle = new OutputFormatterStyle('white', 'green',
            array('bold'));
        $output->getFormatter()->setStyle('header', $headerStyle);

        $site = $input->getArgument('site');
        $output->writeln('<header>Checking ' . $site . '... </header>');
        $site = new Asset($site);

        $verbosityLevelMap = [];
        if ($input->getOption('log-success')) {
            $verbosityLevelMap = array(
                LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            );
        }

        $logger = new ConsoleLogger($output, $verbosityLevelMap);
        $observer = new ConsoleObserver($logger);
        $siteChecker = SiteChecker::create($observer);

        if ($input->getOptions()) {
            $config = new Config();

            // Load configuration from file if any
            $conf = json_decode(file_get_contents(__DIR__ . CONFIG_PATH));
            $siteConf = isset($conf->{$site->host}) ? $conf->{$site->host} : null;

            if (!is_null($siteConf) || !is_null($conf)) {
                foreach ((array)$config as $key => $value) {
                    // First use general values
                    if (!empty($conf->{$key})) {
                        $config->{$key} = $conf->{$key};
                    }
                    // Then rewrite them with site-specific
                    if (!empty($siteConf->{$key})) {
                        $config->{$key} = $siteConf->{$key};
                    }
                }
            }

            if ($input->getOption('log-success')) {
                $config->showOnlyProblems = false;
            }

            if ($input->getOption('check-external')) {
                $config->checkExternal = true;
            }

            if ($input->getOption('full-html')) {
                $config->showFullTags = true;
            }
            $siteChecker->setConfig($config);
            $observer->setConfig($config);
        }
        $siteChecker->check($site);
    }
}
