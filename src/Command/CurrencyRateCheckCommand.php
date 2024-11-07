<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:currency-rate-check',
    description: 'Checks currency rates from PrivatBank and Monobank, sends notifications if changes exceed threshold.'
)]
class CurrencyRateCheckCommand extends Command
{
    private HttpClientInterface $httpClient;
    private MailerInterface $mailer;
    private float $threshold;

    public function __construct(HttpClientInterface $httpClient, MailerInterface $mailer, float $threshold = 1.5)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
        $this->mailer = $mailer;
        $this->threshold = $threshold;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rates = $this->fetchRates();
        $rateDifferences = $this->calculateRateDifferences($rates);

        if (!empty($rateDifferences)) {
            $this->notifyUser($rateDifferences);
            $output->writeln('Notification sent due to significant rate changes.');
        } else {
            $output->writeln('No significant changes detected in currency rates.');
        }

        return Command::SUCCESS;
    }

    private function fetchRates(): array
    {
        $bankUrls = [
            'privatbank' => 'https://api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5',
            'monobank' => 'https://api.monobank.ua/bank/currency',
        ];

        $currencyRates = [];

        foreach ($bankUrls as $bank => $url) {
            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            if ($bank === 'privatbank') {
                foreach ($data as $rate) {
                    if ($rate['ccy'] === 'USD' && $rate['base_ccy'] === 'UAH') {
                        $currencyRates['privatbank'] = (float) $rate['sale'];
                    }
                }
            } elseif ($bank === 'monobank') {
                foreach ($data as $rate) {
                    if ($rate['currencyCodeA'] === 840 && $rate['currencyCodeB'] === 980) { // USD-UAH
                        $currencyRates['monobank'] = (float) $rate['rateSell'];
                    }
                }
            }
        }

        return $currencyRates;
    }

    private function calculateRateDifferences(array $rates): array
    {
        $rateChanges = [];

        if (isset($rates['privatbank'], $rates['monobank'])) {
            $privatRate = $rates['privatbank'];
            $monoRate = $rates['monobank'];
            $difference = abs($privatRate - $monoRate);

            if ($difference >= $this->threshold) {
                $rateChanges['USD-UAH'] = [
                    'privatbank' => $privatRate,
                    'monobank' => $monoRate,
                    'difference' => $difference
                ];
            }
        }

        return $rateChanges;
    }

    private function notifyUser(array $changes): void
    {
        $notificationText = "Currency rate alert:\n";

        foreach ($changes as $currency => $details) {
            $notificationText .= sprintf(
                "%s - PrivatBank: %.2f, Monobank: %.2f, Difference: %.2f\n",
                $currency,
                $details['privatbank'],
                $details['monobank'],
                $details['difference']
            );
        }

        $email = (new Email())
            ->from('alert@example.com')
            ->to('user@example.com')
            ->subject('Currency Rate Alert')
            ->text($notificationText);

        $this->mailer->send($email);
    }
}
