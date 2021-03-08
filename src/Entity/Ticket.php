<?php namespace App\Entity;

use App\AdminApi;
use App\VueViewCompiler;
use Ewll\DBBundle\Annotation as Db;

class Ticket
{
    /** @Db\IntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\VarcharType(length = 256) */
    public $subject;
    /** @Db\BoolType */
    public $hasUnreadMessage = false;
    /** @Db\TimestampType */
    public $lastMessageTs;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($userId, $subject): self
    {
        $item = new self();
        $item->userId = $userId;
        $item->subject = $subject;

        return $item;
    }

    public function compileView(): array
    {
        $view = [
            'id' => $this->id,
            'userId' => $this->userId,
            'subject' => $this->subject,
            'lastMessageTs' => $this->lastMessageTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
            'hasUnreadMessage' => $this->hasUnreadMessage,
        ];

        return $view;
    }

    public function compileAdminApiFinderView(): array
    {
        $view = [
            'id' => $this->id,
            'type' => 'Ticket',
            'info' => $this->subject,
            'date' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }
}
