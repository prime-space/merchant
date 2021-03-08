<?php namespace App;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaProvider
{
    const CHARACTERS_IN_IMAGE = 5;

    private $repositoryProvider;
    private $sessionRegistry;
    private $requestStack;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        SessionRegistry $sessionRegistry,
        RequestStack $requestStack
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->sessionRegistry = $sessionRegistry;
        $this->requestStack = $requestStack;
    }
    public function getImage(): string
    {
        $image = imagecreatetruecolor(140, 50);
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 200, 50, $backgroundColor);

        $lineColor = imagecolorallocate($image, 64, 64, 64);
        $numberOfLines = rand(3, 7);

        for ($i = 0; $i < $numberOfLines; $i++) {
            imageline($image, 0, rand() % 50, 250, rand() % 50, $lineColor);
        }

        $pixel = imagecolorallocate($image, 0, 0, 255);
        for ($i = 0; $i < 500; $i++) {
            imagesetpixel($image, rand() % 200, rand() % 50, $pixel);
        }

        $allowedLetters = 'ABCDEFGHKMNPQRSTUVWXYZ23456789';
        $length = strlen($allowedLetters);
        $word = '';
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $capLength = self::CHARACTERS_IN_IMAGE;
        for ($i = 0; $i < $capLength; $i++) {
            $letter = $allowedLetters[rand(0, $length - 1)];
            imagestring($image, 5, 5 + ($i * 30), 20, $letter, $textColor);
            $word .= $letter;
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $content = ob_get_contents();
        ob_end_clean();
        $this->sessionRegistry->set('captcha', $word);

        return $content;
    }

    public function isCaptchaValid(string $captcha): bool
    {
        $word = $this->sessionRegistry->get('captcha');
        if ($word === null) {
            return false;
        } else {
            $this->sessionRegistry->delete('captcha');
            return $word === $captcha;
        }
    }
}
