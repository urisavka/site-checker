<?php

namespace SiteChecker;


use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ConsoleObserver
 * @package SiteChecker
 */
class ConsoleObserver implements SiteCheckObserver
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
     * @param $response
     * @return mixed
     */
    public function pageChecked(Asset $asset, ResponseInterface $response = null)
    {
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
        $this->sendEmailResults($assets);
    }

    /**
     * Show results.
     *
     * @param Asset[] $assets
     */
    protected function sendEmailResults($assets)
    {
        if (!empty($this->config->reportEmail)) {
            $countFailed = 0;
            $messages = [];
            foreach ($assets as $asset) {
                if ($asset->isError()) {
                    $countFailed++;
                    $message = $asset->getURL();
                    if ($asset->getParentPage() instanceof Asset) {
                        $message .= ' on ' . $asset->getParentPage()->getURL();
                    }
                    $messages[] = $message;
                }
            }
            if (empty($messages)) {
                return;
            }
            $mail = new \PHPMailer();
            $mailFrom = !empty($this->config->reportEmailFrom) ?
              $this->config->reportEmailFrom : $this->config->reportEmail;
            $mail->setFrom($mailFrom, 'Site Checker');
            if (stristr($this->config->reportEmail, ',')) {
                $emailAdresses = explode(',', $this->config->reportEmail);
                foreach ($emailAdresses as $emailAdress) {
                    $mail->addAddress($emailAdress);
                }
            } else {
                $mail->addAddress($this->config->reportEmail);
            }
            $mail->Subject = 'SiteChecker report';
            $mail->Body = "Hi, here are some broken links on your website:\n\n";
            $mail->Body .= implode('<br>', $messages);

            if (!$mail->send()) {
                $this->logger->error('Message could not be sent.');
                $this->logger->error('Mailer Error: ' . $mail->ErrorInfo);
            } else {
                $this->logger->info('Message has been sent');
            }
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
        if ($this->config->showFullTags && $html = $asset->getFullHtml()) {
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
