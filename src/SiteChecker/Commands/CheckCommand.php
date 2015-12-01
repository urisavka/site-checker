<?php

namespace SiteChecker\Commands;

use Psr\Log\LogLevel;
use SiteChecker\Config;
use SiteChecker\SiteChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

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
Checks a site for broken links

Usage:

<info> app/console site-checker:check http://site.url</info>
EOT
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $header_style = new OutputFormatterStyle('white', 'green',
          array('bold'));
        $output->getFormatter()->setStyle('header', $header_style);

        $site = $input->getArgument('site');
        $output->writeln('<header>Checking ' . $site . '... </header>');

        $verbosityLevelMap = [];
        if ($input->getOption('log-success')) {
            $verbosityLevelMap = array(
              LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            );
        }

        $logger = new ConsoleLogger($output, $verbosityLevelMap);
        $siteChecker = SiteChecker::create($logger);

        if ($input->getOptions()) {
            $config = new Config();

            if ($input->getOption('log-success')) {
                $config->showOnlyProblems = false;
            }

            if ($input->getOption('check-external')) {
                $config->checkExternal = false;
            }

            if ($input->getOption('full-html')) {
                $config->showFullTags = true;
            }
            $siteChecker->setConfig($config);
        }
        $siteChecker->check($site);
    }
}
