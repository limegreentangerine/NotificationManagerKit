<?php

namespace NotificationManagerKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use ClassKit\Entity\Core\UpdatedGuidEntity;

/**
 * A page a notification with `specific` scope is shown on.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="nmNotificationPages",
 *      indexes={
 *          @ORM\Index(name="idx_nm_cid", columns={"cID"})
 *      },
 *      options={"comment": "Pages a notification is targeted at"}
 * )
 */
class NotificationPage extends UpdatedGuidEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="NotificationManagerKit\Entity\AbstractNotification", inversedBy="pages")
     * @ORM\JoinColumn(name="notificationId", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $notification;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"comment": "Page cID"})
     */
    protected $cID;

    public function getNotification(): ?AbstractNotification
    {
        return $this->notification;
    }

    public function setNotification(?AbstractNotification $notification): self
    {
        $this->notification = $notification;

        return $this;
    }

    public function getCID(): int
    {
        return (int) $this->cID;
    }

    public function setCID(int $cID): self
    {
        $this->cID = $cID;

        return $this;
    }
}
