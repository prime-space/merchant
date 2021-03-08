<?php namespace App\Command;

use App\Entity\Currency;
use App\Exception\CannotGetCurrencyException;
use App\Exception\CannotUpdateCurrencyException;
use App\Logger\LogExtraDataKeeper;
use Ewll\DBBundle\Repository\Repository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCurrencyRatesCommand extends AbstractCommand
{
    const URL_CBR = 'https://www.cbr-xml-daily.ru/daily_utf8.xml';
    const URL_BLOCKCHAIN = 'https://blockchain.info/ticker';
    const COMMAND_NAME = 'currency:sync';

    protected $client;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
    }

    public function __construct(
        Logger $logger,
        Client $client
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->client = $client;
    }

    /** @throws CannotGetCurrencyException */
    private function makeRequest(string $url): Response
    {
        $this->logger->info("Attempt request to $url");
        foreach (range(1, 5) as $iteration) {
            try {
                $response = $this->client->request('GET', $url, [
                    'connect_timeout' => 5,
                    'timeout' => 5
                ]);
                $this->logger->info('Successfully got currency');

                return $response;
            } catch (RequestException $exception) {
                $this->logger->error('Error while trying to get currency');
                sleep(10);
            }
        }

        throw new CannotGetCurrencyException('Cannot get currency');
    }

    private function isCbrDataValid($data): bool
    {
        if (!$data instanceof SimpleXMLElement) {
            return false;
        }
        foreach ($data as $currency) {
            if (!isset($currency->CharCode) || !isset($currency->Value) || !isset($currency->Nominal)) {
                return false;
            }
        }

        return true;
    }

    private function isCurrencyRateValid(string $rate): bool
    {
        preg_match('/^\d{1,8}(\.\d{1,4})?$/', $rate, $match);

        return isset($match[0]) && (bccomp($match[0], 0) === 1);
    }

    private function getCbrCurrencyObject(SimpleXMLElement $objectsArray, string $currencyCharCode): ?SimpleXMLElement
    {
        foreach ($objectsArray as $currencyObject) {
            if ((string)$currencyObject->CharCode === strtoupper($currencyCharCode)) {
                return $currencyObject;
            }
        }

        return null;
    }

    private function isCurrencyNominalValid(string $nominal): bool
    {
        if (preg_match('/^[1-9]\d*$/', $nominal) === 1) {
            return true;
        }

        return false;
    }

    private function isRateAndNominalValid(string $rate, string $nominal): bool
    {
        return $this->isCurrencyRateValid($rate) && $this->isCurrencyNominalValid($nominal);
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        bcscale(4);
        /** @var Repository $currencyRepository */
        $currencyRepository = $this->repositoryProvider->get(Currency::class);

        try {
            $cbrResponse = $this->makeRequest(self::URL_CBR);
            $cbrData = simplexml_load_string($cbrResponse->getBody()->getContents());
            if (!$this->isCbrDataValid($cbrData)) {
                $error = 'Cbr data not valid';
                $this->logger->critical($error);

                throw new RuntimeException($error);
            }
            $blockchainResponse = $this->makeRequest(self::URL_BLOCKCHAIN);
            $blockchainData = json_decode($blockchainResponse->getBody()->getContents(), true);
            $rubBtcRate = $blockchainData['RUB']['last'] ?? '';
            if (!$this->isCurrencyRateValid($rubBtcRate)) {
                $error = 'Blockchain data not valid';
                $this->logger->critical($error);

                throw new RuntimeException($error);
            }
            if (bccomp('100000', $rubBtcRate, 0) === 1) {
                $error = 'Low bound of btc rate reached';
                $this->logger->critical($error);

                throw new RuntimeException($error);
            }
            //Inside rate -10k
            $rubBtcRate = bcsub($rubBtcRate, '10000', 4);
        } catch (CannotGetCurrencyException $e) {
            $this->logger->critical($e->getMessage());

            throw new RuntimeException($e->getMessage());
        }

        /** @var Currency[] $currencies */
        $currencies = $currencyRepository->findAll();

        foreach ($currencies as $currency) {
            try {
                if ($currency->name === Currency::NAME_RUB) {
                    $currency->rate = '1';
                } elseif ($currency->name === Currency::NAME_BTC) {
                    $currency->rate = $rubBtcRate;
                } else {
                    $cbrCurrencyObject = $this->getCbrCurrencyObject($cbrData, $currency->name);
                    if (null === $cbrCurrencyObject) {
                        throw new CannotUpdateCurrencyException("No such currency in response - {$currency->name}");
                    }

                    $newCurrencyRate = str_replace(',', '.', $cbrCurrencyObject->Value);
                    if ($this->isRateAndNominalValid($newCurrencyRate, $cbrCurrencyObject->Nominal)) {
                        $currency->rate = bcdiv($newCurrencyRate, $cbrCurrencyObject->Nominal);
                    } else {
                        throw new CannotUpdateCurrencyException("Cannot update currency value for {$currency->name}");
                    }
                }
                $currencyRepository->update($currency);
                $this->logger->info("Currency rate for {$currency->name} was successfully updated");
            } catch (CannotUpdateCurrencyException $e) {
                $this->logger->critical($e->getMessage());

                throw $e;
            }
        }
    }
}
