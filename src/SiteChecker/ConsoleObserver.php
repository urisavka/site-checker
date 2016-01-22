<?php

namespace SiteChecker;


use Psr\Log\LoggerInterface;
use SiteChecker\Interfaces\SiteCheckObserverInterface;

/**
 * Class ConsoleObserver
 * @package SiteChecker
 */
class ConsoleObserver implements SiteCheckObserverInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * SiteChecker constructor.
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * No additional checks here for now.
     *
     * @param \SiteChecker\Asset $url
     * @return bool
     */
    public function pageToCheck(Asset $url)
    {
        return true;
    }


    /**
     * Log page to console.
     *
     * @param \SiteChecker\Asset $asset
     * @return mixed
     */
    public function pageChecked(
        Asset $asset
    ) {
        $this->logResult($asset);
    }


    /**
     * Show results.
     *
     * @param Asset[] $assets
     */
    public function receiveResults(array $assets)
    {
        $this->showResults($assets);
        if (!empty($this->config->reportEmail)) {
            $this->sendEmailResults($assets);
        }
    }

    /**
     * Show results.
     *
     * @param Asset[] $assets
     */
    protected function sendEmailResults($assets)
    {
        $countFailed = 0;
        $messages = [];
        $assets = array_filter(
            $assets,
            function(Asset $asset) {
                return $asset->isError();
            }
        );
        /** @var Asset $asset */
        foreach ($assets as $asset) {
            $countFailed++;
            $message = ' * ' . $asset->getURL();
            if ($asset->getParentPage() instanceof Asset) {
                $message .= ' on ' . $asset->getParentPage()->getURL();
            }
            $messages[] = $message;

        }
        if (empty($messages)) {
            return;
        }

        $mail = new \PHPMailer();
        $mailFrom = $this->config->getMailFrom();
        $mail->setFrom($mailFrom, 'Site Checker');
        $addresses = $this->config->getReportEmailAddresses();
        foreach ($addresses as $emailAddress) {
            $mail->addAddress($emailAddress);
        }

        $mail->Subject = 'SiteChecker report';
        $mail->Body = "Hi, here are some broken links on your website:\n\n";
        $mail->Body .= implode(PHP_EOL, $messages);

        if (!$mail->send()) {
            $this->logger->error('Message could not be sent.');
            $this->logger->error('Mailer Error: ' . $mail->ErrorInfo);
        } else {
            $this->logger->info('Message has been sent');
        }
    }

    /**
     * Called when the checker has checked the given page.
     *
     * @param Asset $asset
     */
    public function logResult($asset)
    {
        $code = ($asset instanceof Asset) ? $asset->getResponseCode() : Asset::CODE_ERROR;
        $messageParts = ['Checking'];
        $messageParts[] = 'asset: ' . $asset->getURL();
        if ($parent = $asset->getParentPage()) {
            $messageParts[] = 'on a page: ' . $parent->getURL() . '.';
        }
        if ($this->config->showFullTags && $html = $asset->getHtmlTag()) {
            $messageParts[] = 'Full html of it is: ' . $html . '.';
        }
        $messageParts[] = 'Received code: ' . $code;
        $message = implode(' ', $messageParts);

        if ($asset->isError()) {
            $this->logger->error($message);
        } elseif ($asset->isWarning()) {
            $this->logger->warning($message);
        } else {
            $this->logger->info($message);
        }
    }

    /**
     * Called when the check was ended.
     *
     * @param Asset[] $assets
     */
    public function showResults(array $assets)
    {
        $this->logger->info("Check is finished. Here are the results:");
        $successCount = 0;
        $failedCount = 0;

        foreach ($assets as $asset) {
            if ($asset->isSuccessful()) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }
        if ($successCount) {
            $this->logger->info('Successful: ' . $successCount);
        }
        if ($failedCount) {
            $this->logger->error('Failed: ' . $failedCount);
        }
    }
}
