<?php namespace App\Entity;

use App\AdminApi;
use App\VueViewCompiler;
use Ewll\DBBundle\Annotation as Db;
use Symfony\Component\Translation\TranslatorInterface;

class Notification
{
    const STATUS_ID_SENT = 1;
    const STATUS_ID_ERROR = 2;

    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $paymentId;
    /** @Db\TinyIntType */
    public $statusId;
    /** @Db\JsonType */
    public $data;
    /** @Db\TextType */
    public $result;
    /** @Db\IntType */
    public $httpCode;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $paymentId,
        $statusId,
        $data,
        $result,
        $httpCode
    ): self {
        $item = new self();
        $item->paymentId = $paymentId;
        $item->statusId = $statusId;
        $item->data = $data;
        $item->result = $result;
        $item->httpCode = $httpCode;

        return $item;
    }

    public function compileAdminApiView(): array
    {
        $view = [
            'id' => $this->id,
            'paymentId' => $this->paymentId,
            'status' => $this->statusId === Notification::STATUS_ID_SENT ? 'Sent' : 'Error',
            'result' => $this->result,
            'httpCode' => $this->httpCode,
            'created' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }

    public function compileAdminView(TranslatorInterface $translator): array
    {
        $view = [
            'id' => $this->id,
            'status' => $translator->trans("status.{$this->statusId}", [], 'notification'),
            'result' => $this->result,
            'httpCode' => $this->httpCode,
            'created' => $this->createdTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
        ];

        return $view;
    }
}
