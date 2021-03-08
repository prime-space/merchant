<?php namespace App;

use App\Entity\Letter;
use App\Entity\User;
use App\Exception\CannotSendEmailException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Translation\TranslatorInterface;
use Twig_Environment;

class Mailer
{
    const LETTER_NAME_CONFIRMATION = 'emailConfirmation';
    const LETTER_NAME_TICKET_ANSWER = 'ticketAnswer';
    const LETTER_NAME_HELP = 'help';
    const LETTER_NAME_PAYMENT_DAY_LIMIT_EXCEEDED = 'paymentDayLimitExceeded';
    const LETTER_PAYMENT_REFUND = 'paymentRefund';

    private $messageBroker;
    private $phpMailer;
    private $repositoryProvider;
    private $translator;
    private $templating;
    private $logger;

    public function __construct(
        MessageBroker $messageBroker,
        PHPMailer $phpMailer,
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        Twig_Environment $templating,
        Logger $logger,
        string $mailerHost,
        int $mailerPort,
        string $mailerSecure,
        string $mailerUser,
        string $mailerPass,
        bool $mailerSmtpAuth,
        string $mailerSenderEmail,
        string $mailerSenderName
    ) {
        $this->messageBroker = $messageBroker;
        $this->repositoryProvider = $repositoryProvider;
        $this->translator = $translator;
        $this->templating = $templating;
        $this->phpMailer = $phpMailer;
        $this->logger = $logger;
        $this->phpMailer->Host = $mailerHost;
        $this->phpMailer->Port = $mailerPort;
        $this->phpMailer->SMTPAuth = $mailerSmtpAuth;
        $this->phpMailer->Username = $mailerUser;
        $this->phpMailer->Password = $mailerPass;
        $this->phpMailer->Mailer = $mailerSmtpAuth ? 'smtp' : 'mail';
        $this->phpMailer->SMTPSecure = $mailerSecure;
        $this->phpMailer->From = $mailerSenderEmail;
        $this->phpMailer->FromName = $mailerSenderName;
        $this->phpMailer->ContentType = 'text/html';
        $this->phpMailer->CharSet = 'UTF-8';
        $this->phpMailer->Timeout = 5;
        $this->phpMailer->SMTPDebug = 4;
        $this->phpMailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    // Creates any letter for confirmed email and confirmation letter only for unconfirmed email
    public function createForUser(
        int $userId,
        string $templateName,
        array $templateData
    ): void {
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($userId);
        if (!$user->isEmailConfirmed && $templateName !== self::LETTER_NAME_CONFIRMATION) {
            $this->logger->critical(
                'Letter is not created! Only confirmation letters for unconfirmed emails are allowed'
            );

            return;
        }
        $letter = Letter::create(
            $userId,
            $user->email,
            $this->translator->trans("letter-subject-{$templateName}", [], 'site'),
            $this->templating->render(
                "letter/{$templateName}.html.twig",
                $templateData
            )
        );
        $this->repositoryProvider->get(Letter::class)->create($letter);
        $this->toQueue($letter->id);
    }

    public function create(
        string $email,
        string $templateName,
        array $templateData
    ): void {
        $letter = Letter::create(
            null,
            $email,
            $this->translator->trans("letter-subject-{$templateName}", [], 'site'),
            $this->templating->render(
                "letter/{$templateName}.html.twig",
                $templateData
            )
        );
        $this->repositoryProvider->get(Letter::class)->create($letter);
        $this->toQueue($letter->id);
    }

    public function toQueue(int $letterId, int $try = 1, int $delay = 0): void
    {
        $this->messageBroker->createMessage(MessageBroker::QUEUE_MAIL_NAME, [
            'letterId' => $letterId,
            'try' => $try,
        ], $delay);
    }

    /** @throws CannotSendEmailException */
    public function send(Letter $letter): void
    {
        $errorMessage = '';
        ob_start();
        $this->phpMailer->ClearAddresses();
        $this->phpMailer->ClearAttachments();
        $this->phpMailer->msgHTML($letter->body);
        $this->phpMailer->Subject = $letter->subject;
        $this->phpMailer->AddAddress($letter->email, '');

        try {
            $isSent = $this->phpMailer->send();
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $isSent = false;
        }
        $debug = ob_get_contents();
        ob_end_clean();

        if (!$isSent) {
            $debug = explode("\n", $debug);
            throw new CannotSendEmailException($errorMessage, $debug);
        }
    }
}
