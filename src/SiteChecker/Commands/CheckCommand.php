<?php

namespace SiteChecker\Commands;

use SiteChecker\SiteChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

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
          ->setDefinition(array(
            new InputArgument('site', InputArgument::REQUIRED),
            new InputOption('check-external', 'ce', InputOption::VALUE_NONE,
              'Site to crawl and check'),
          ))
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
        $siteChecker = SiteChecker::create();
        $siteChecker->check($site);
        $messages = $siteChecker->getMessages();
        foreach ($messages as $message) {
            $output->writeln($message);
        }

        $output->writeln('<header>Site = ' . $site . ' </header>');
    }
}
