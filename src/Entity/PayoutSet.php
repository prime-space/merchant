<?php namespace App\Entity;

use App\VueViewCompiler;
use Ewll\DBBundle\Annotation as Db;
use Symfony\Component\Translation\TranslatorInterface;

class PayoutSet
{
    const STATUS_ID_NEW = 1;
    const STATUS_ID_SUCCESS = 2;
    const STATUS_ID_PART = 3;
    const STATUS_ID_ERROR = 4;

    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $payoutMethodId;
    /** @Db\IntType */
    public $userId;
    /** @Db\IntType */
    public $internalUsersId;
    /** @Db\IntType */
    public $accountId;
    /** @Db\TinyIntType */
    public $paymentSystemId;
    /** @Db\IntType */
    public $paymentAccountId;
    /** @Db\VarcharType(length = 64) */
    public $receiver;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\DecimalType */
    public $transferredAmount = 0;
    /** @Db\DecimalType */
    public $fee;
    /** @Db\DecimalType */
    public $credit;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\IntType */
    public $chunkNum;
    /** @Db\IntType */
    public $chunkProcessedNum = 0;
    /** @Db\IntType */
    public $chunkSuccessNum = 0;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $payoutMethodId,
        $userId,
        $internalUsersId,
        $accountId,
        $paymentSystemId,
        $receiver,
        $amount,
        $fee,
        $credit,
        $chunkNum
    ): self {
        $item = new self();
        $item->payoutMethodId = $payoutMethodId;
        $item->userId = $userId;
        $item->internalUsersId = $internalUsersId;
        $item->accountId = $accountId;
        $item->paymentSystemId = $paymentSystemId;
        $item->receiver = $receiver;
        $item->amount = $amount;
        $item->fee = $fee;
        $item->credit = $credit;
        $item->chunkNum = $chunkNum;

        return $item;
    }

    public function compileClientApiInfoView()
    {
        $statuses = [
            self::STATUS_ID_NEW => 2,
            self::STATUS_ID_SUCCESS => 3,
            self::STATUS_ID_PART => 4,
            self::STATUS_ID_ERROR => 4,
        ];
        $status = $statuses[$this->statusId];
        $isPartially = $this->chunkNum > 1;

        return [
            'id' => $this->id,
            'statusId' => $status,
            'isPartially' => $isPartially,
            'transferredAmount' => $this->transferredAmount,
        ];
    }

    public function compileAdminApiView(
        TranslatorInterface $translator,
        array $userAccountIndexedById,
        array $payoutMethods
    ) {
        $view = $this->compileTableView($translator, $userAccountIndexedById, $payoutMethods);
        $view['userId'] = $this->userId;
        $view['actions'] = [
            ['icon' => 'wheelchair-accessibility', 'type' => 'entity', 'entity' => 'user', 'entityId' => $this->userId],
        ];

        return $view;
    }

    public function compileTableView(
        TranslatorInterface $translator,
        array $userAccountIndexedById,
        array $payoutMethods
    ) {
        $currencyId = $userAccountIndexedById[$this->accountId]->currencyId;
        $currencySign = $translator->trans("currency.$currencyId.sign", [], 'payment');
        $amount = bcmul($this->amount, 1, 2);
        $transferredAmount = bcmul($this->transferredAmount, 1, 2);
        $fee = bcmul($this->fee, 1, 2);
        $view = [
            'id' => $this->id,
            'internalUsersId' => $this->internalUsersId,
            'payoutMethodName' => $translator->trans(
                "method.name.{$payoutMethods[$this->payoutMethodId]->name}",
                [],
                'payout'
            ),
            'receiver' => $this->receiver,
            'amount' => "$transferredAmount/$amount $currencySign",
            'fee' => "$fee $currencySign",
            'status' => $translator->trans("set.status.{$this->statusId}", [], 'payout'),
            'chunks' => "$this->chunkSuccessNum/$this->chunkProcessedNum/$this->chunkNum",
            'created' => $this->createdTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
        ];

        return $view;
    }
}
