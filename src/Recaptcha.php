<?php namespace App;

use App\Exception\CannotCheckRecaptchaException;
use App\Exception\ItIsNotHumanException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bridge\Monolog\Logger;

class Recaptcha
{
    private const URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const HUMAN_SCORE = 0.3;

    private $guzzle;
    private $recaptchaKey2;
    private $logger;

    public function __construct(
        Client $guzzle,
        string $recaptchaKey2,
        Logger $logger
    ) {
        $this->guzzle = $guzzle;
        $this->recaptchaKey2 = $recaptchaKey2;
        $this->logger = $logger;
    }

    /**
     * @throws CannotCheckRecaptchaException
     * @throws ItIsNotHumanException
     */
    public function check(string $auth, string $token, string $ip): void
    {
        $logData = ['auth' => $auth, 'ip' => $ip];
        try {
            $request = $this->guzzle->post(self::URL, [
                'timeout' => 6,
                'connect_timeout' => 6,
                'form_params' => [
                    'secret' => $this->recaptchaKey2,
                    'response' => $token,
                    'remoteip' => $ip,
                ]
            ]);
            $result = json_decode($request->getBody()->getContents(), true);
            $logData['score'] = $result['score'];
            if ($result['score'] >= self::HUMAN_SCORE) {
                $this->logger->info('Checking success', $logData);
            } else {
                $this->logger->error('Checking fault', $logData);

                throw new ItIsNotHumanException();
            }
        } catch (RequestException $e) {
            $logData['error'] = $e->getMessage();
            $this->logger->crit('Checking request exception', $logData);

            throw new CannotCheckRecaptchaException();
        }
    }
}
