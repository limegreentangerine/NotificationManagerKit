<?php

namespace NotificationManagerKit\Entity;

use Concrete\Core\Page\Page;
use Doctrine\ORM\Mapping as ORM;
use Concrete\Core\Entity\File\File;
use Concrete\Core\Editor\LinkAbstractor;
use ClassKit\Entity\Core\UpdatedGuidEntity;
use NotificationManagerKit\Support\{LinkResolver, Sanitizer};

/**
 * One locale's worth of text (and popup image) for a notification.
 *
 * @phpstan-consistent-constructor
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

    /**
     * @ORM\Column(type="string", length=16, nullable=true, options={"comment": "Link type: none, page, file, url, phone or email. Null on rows saved before link types existed"})
     */
    protected $linkType = null;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true, options={"comment": "Typed link target for the url, phone and email types"})
     */
    protected $linkValue = null;

    /**
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\File\File")
     * @ORM\JoinColumn(name="linkFID", referencedColumnName="fID", onDelete="SET NULL", nullable=true)
     */
    protected $linkFile = null;

    /**
     * An unsaved copy of this locale's content. Fields are copied as stored (already sanitised, links still in
     * their abstract form), so nothing is re-processed; the image and linked file are shared references.
     */
    public function duplicate(): static
    {
        $copy = new static();

        $copy->language = $this->language;
        $copy->name = $this->name;
        $copy->fID = $this->fID;
        $copy->content = $this->content;
        $copy->linkText = $this->linkText;
        $copy->linkCID = $this->linkCID;
        $copy->linkType = $this->linkType;
        $copy->linkValue = $this->linkValue;
        $copy->linkFile = $this->linkFile;

        return $copy;
    }

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

    /**
     * Rows saved before link types existed only have a page, so a missing type is read as "page" or "none".
     */
    public function getLinkType(): string
    {
        if ($this->linkType === null || !LinkResolver::isValidType($this->linkType)) {
            return $this->linkCID !== null ? LinkResolver::TYPE_PAGE : LinkResolver::TYPE_NONE;
        }

        return $this->linkType;
    }

    public function setLinkType(?string $linkType): self
    {
        $this->linkType = LinkResolver::isValidType((string) $linkType) ? $linkType : LinkResolver::TYPE_NONE;

        return $this;
    }

    public function getLinkValue(): string
    {
        return (string) $this->linkValue;
    }

    public function setLinkValue(?string $linkValue): self
    {
        $linkValue = trim((string) $linkValue);
        $this->linkValue = $linkValue === '' ? null : mb_substr($linkValue, 0, 2000);

        return $this;
    }

    public function getLinkFile(): File|false
    {
        if ($this->linkFile === null) {
            return false;
        }

        $file = $this->linkFile instanceof File ? $this->linkFile : \Concrete\Core\File\File::getByID((int) $this->linkFile);

        return ($file instanceof File && !$file->isError()) ? $file : false;
    }

    public function setLinkFile(?File $file): self
    {
        $this->linkFile = $file;

        return $this;
    }

    /**
     * Whether what was entered for the chosen type can produce a link. A type of "none" is always fine.
     * Page and file links need the picker to have supplied something; the typed types must pass LinkResolver.
     */
    public function hasValidLink(): bool
    {
        return match ($this->getLinkType()) {
            LinkResolver::TYPE_NONE => true,
            LinkResolver::TYPE_PAGE => $this->linkCID !== null,
            LinkResolver::TYPE_FILE => $this->linkFile !== null,
            default => LinkResolver::href($this->getLinkType(), $this->linkValue) !== null,
        };
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
