<?php

namespace NotificationManagerKit\Entity;

use Concrete\Core\Page\Page;
use Doctrine\ORM\Mapping as ORM;
use Concrete\Core\Entity\File\File;
use Concrete\Core\Editor\LinkAbstractor;
use ClassKit\Entity\Core\UpdatedGuidEntity;
use NotificationManagerKit\Support\Sanitizer;

/**
 * One locale's worth of text (and popup image) for a notification.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="nmNotificationContents",
 *      indexes={
 *          @ORM\Index(name="idx_nm_fid", columns={"fID"}),
 *          @ORM\Index(name="idx_nm_language", columns={"language"})
 *      },
 *      options={"comment": "Per-locale content for notifications"}
 * )
 */
class NotificationContent extends UpdatedGuidEntity
{
    /**
     * @ORM\Column(type="string", length=32, nullable=false, options={"comment": "Locale language code"})
     */
    protected $language;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default": "", "comment": "Name / heading"})
     */
    protected $name = '';

    /**
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\File\File")
     * @ORM\JoinColumn(name="fID", referencedColumnName="fID", onDelete="SET NULL", nullable=true)
     */
    protected $fID = null;

    /**
     * @ORM\Column(type="text", nullable=false, options={"comment": "Rich text content, links stored in Concrete's abstract form"})
     */
    protected $content = '';

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default": "", "comment": "Link text"})
     */
    protected $linkText = '';

    /**
     * @ORM\Column(type="integer", nullable=true, options={"comment": "Linked page cID"})
     */
    protected $linkCID = null;

    public function getLanguage(): string
    {
        return (string) $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = Sanitizer::text($name);

        return $this;
    }

    /**
     * Rich text. $editMode returns the editor form; otherwise the front end form, sanitised again on the way out.
     */
    public function getContent(bool $editMode = false): string
    {
        if ($editMode) {
            return LinkAbstractor::translateFromEditMode((string) $this->content);
        }

        return Sanitizer::html(LinkAbstractor::translateFrom((string) $this->content));
    }

    /**
     * Takes the editor's HTML; stored sanitised, in Concrete's abstract link form.
     */
    public function setContent(?string $content): self
    {
        $this->content = Sanitizer::html(LinkAbstractor::translateTo((string) $content));

        return $this;
    }

    public function getLinkText(): string
    {
        return (string) $this->linkText;
    }

    public function setLinkText(?string $linkText): self
    {
        $this->linkText = Sanitizer::text($linkText);

        return $this;
    }

    public function getLinkCID(): ?int
    {
        return $this->linkCID === null ? null : (int) $this->linkCID;
    }

    /**
     * A blank or non-positive value clears the link (the column is nullable, so '' never reaches the integer column).
     */
    public function setLinkCID($linkCID): self
    {
        $linkCID = (int) $linkCID;
        $this->linkCID = $linkCID > 0 ? $linkCID : null;

        return $this;
    }

    public function getPage(): Page|false
    {
        if ($this->linkCID === null) {
            return false;
        }

        $page = Page::getByID((int) $this->linkCID);

        return !$page->isError() ? $page : false;
    }

    public function getImage(): File|false
    {
        if ($this->fID === null) {
            return false;
        }

        $file = $this->fID instanceof File ? $this->fID : \Concrete\Core\File\File::getByID((int) $this->fID);

        return ($file instanceof File && !$file->isError()) ? $file : false;
    }

    public function setImage(?File $file): self
    {
        $this->fID = $file;

        return $this;
    }
}
