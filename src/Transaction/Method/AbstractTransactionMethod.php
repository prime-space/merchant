<?php namespace App\Transaction\Method;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Translation\TranslatorInterface;

class AbstractTransactionMethod
{
    /** @var RepositoryProvider */
    protected $repositoryProvider;
    /** @var TranslatorInterface */
    protected $translator;

    public function setRepositoryProvider(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function isShowInWallet(): bool
    {
        return true;
    }
}
