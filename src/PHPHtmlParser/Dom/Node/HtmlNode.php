<?php

declare(strict_types=1);

namespace PHPHtmlParser\Dom\Node;

use PHPHtmlParser\Dom\Tag;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\UnknownChildTypeException;
use PHPHtmlParser\Exceptions\Tag\AttributeNotFoundException;

/**
 * Class HtmlNode.
 *
 * @property-read string    $outerhtml
 * @property-read string    $innerhtml
 * @property-read string    $innerText
 * @property-read string    $text
 * @property-read Tag       $tag
 * @property-read InnerNode $parent
 */
class HtmlNode extends InnerNode
{
    protected ?string $innerHtml = null;
    protected ?string $outerHtml = null;
    protected ?string $innerText = null;
    protected ?string $text = null;
    protected ?string $textWithChildren = null;
  
    private $html5Elements = [
        'article', 'aside', 'audio', 'bdi', 'canvas', 'data', 'datalist',
        'details', 'figcaption', 'figure', 'footer', 'header', 'main',
        'mark', 'meter', 'nav', 'output', 'picture', 'progress', 'section',
        'summary', 'template', 'time', 'video'
    ];

    private $html5InputTypes = [
        'color', 'date', 'datetime-local', 'email', 'month', 'number', 'range',
        'search', 'tel', 'time', 'url', 'week'
    ];
    /**
     * Sets up the tag of this node.
     *
     * @param string|Tag $tag
     */
    public function __construct($tag)
    {
        if (!$tag instanceof Tag) {
            $tag = new Tag($tag);
        }
        $this->tag = $tag;
        parent::__construct();
    }

    public function setHtmlSpecialCharsDecode($htmlSpecialCharsDecode = false): void
    {
        parent::setHtmlSpecialCharsDecode($htmlSpecialCharsDecode);
        $this->tag->setHtmlSpecialCharsDecode($htmlSpecialCharsDecode);
    }

    public function isHtml5Element(): bool
    {
        return in_array(strtolower($this->tag->name()), $this->html5Elements);
    }

    /**
     * Gets the inner html of this node.
     *
     * @throws ChildNotFoundException
     * @throws UnknownChildTypeException
     */
    public function innerHtml(): string
    {
        if (!$this->hasChildren()) {
            return '';
        }

        if ($this->innerHtml !== null) {
            return $this->innerHtml;
        }

        $string = '';
        $child = $this->firstChild();

        while ($child !== null) {
            if ($child instanceof TextNode) {
                $string .= $child->text();
            } elseif ($child instanceof HtmlNode) {
                $string .= $child->outerHtml();
            } else {
                throw new UnknownChildTypeException('Unknown child type "' . \get_class($child) . '" found in node');
            }

            try {
                $child = $this->nextChild($child->id());
            } catch (ChildNotFoundException $e) {
                $child = null;
            }
        }

        $this->innerHtml = $string;
        return $string;
    }

    /**
     * Gets the inner text of this node.
     *
     * @throws ChildNotFoundException
     * @throws UnknownChildTypeException
     */
    public function innerText(): string
    {
        if ($this->innerText === null) {
            $this->innerText = \strip_tags($this->innerHtml());
        }

        return $this->innerText;
    }

    /**
     * Gets the html of this node, including its own tag.
     *
     * @throws ChildNotFoundException
     * @throws UnknownChildTypeException
     */
    public function outerHtml(): string
    {
        if ($this->tag->name() == 'root') {
            return $this->innerHtml();
        }

        if ($this->outerHtml !== null) {
            return $this->outerHtml;
        }

        $return = $this->tag->makeOpeningTag();
        if ($this->tag->isSelfClosing() || $this->isHtml5InputType()) {
            // For HTML5 elements, we'll always use the self-closing syntax without a trailing slash
            $return = rtrim($return, '/>') . '>';
            return $return;
        }

        $return .= $this->innerHtml();
        $return .= $this->tag->makeClosingTag();

        $this->outerHtml = $return;
        return $return;
    }

    /**
     * Gets the text of this node (if there is any text). Or get all the text
     * in this node, including children.
     */
    public function text(bool $lookInChildren = false): string
    {
        if ($lookInChildren) {
            if ($this->textWithChildren !== null) {
                return $this->textWithChildren;
            }
        } elseif ($this->text !== null) {
            return $this->text;
        }

        $text = '';
        foreach ($this->children as $child) {
            /** @var AbstractNode $node */
            $node = $child['node'];
            if ($node instanceof TextNode) {
                $text .= $child['node']->text;
            } elseif ($lookInChildren && $node instanceof HtmlNode) {
                $text .= $node->text($lookInChildren);
            }
        }

        if ($lookInChildren) {
            $this->textWithChildren = $text;
        } else {
            $this->text = $text;
        }

        return $text;
    }

    /**
     * Call this when something in the node tree has changed. Like a child has been added
     * or a parent has been changed.
     */
    protected function clear(): void
    {
        $this->innerHtml = null;
        $this->outerHtml = null;
        $this->text = null;
        $this->textWithChildren = null;

        if ($this->parent !== null) {
            $this->parent->clear();
        }
    }

    /**
     * Returns all children of this html node.
     */
    protected function getIteratorArray(): array
    {
        return $this->getChildren();
    }

    public function isHtml5InputType(): bool
    {
        if (strtolower($this->tag->name()) !== 'input') {
            return false;
        }

        $typeAttribute = $this->tag->getAttribute('type');
        if ($typeAttribute === null) {
            return false;
        }

        $type = $typeAttribute->getValue();
        return in_array(strtolower($type), $this->html5InputTypes);
    }

  /**
 * Gets the value of a data attribute or all data attributes.
 * @throws AttributeNotFoundException
 * @param string|null $name The name of the data attribute (without 'data-' prefix), or null to get all data attributes
 * @return string|array<string, string>|null The value of the specified data attribute, all data attributes as an array, or null if not found
 */
public function getData(?string $name = null): string|array|null
{
    if ($name === null) {
        return $this->tag->getDataAttributes();
    }

    try {
        $attribute = $this->tag->getAttribute('data-' . $name);
        return $attribute->getValue();
    } catch (AttributeNotFoundException $e) {
        return null;
    }
}

   /**
 * Sets an attribute on this node.
 *
 * @param string $key
 * @param string|bool|null $value
 * @param bool $doubleQuote
 * @return HtmlNode
 */
public function setAttribute(string $key, $value, bool $doubleQuote = true): HtmlNode
{
    // Handle boolean attributes
    if (is_bool($value)) {
        if ($value === true) {
            $value = $key; // Set the value to the key name for true boolean attributes
        } else {
            // If the value is false, we remove the attribute
            $this->tag->removeAttribute($key);
            $this->clear();
            return $this;
        }
    }

    // Cast to string if the value is not null (null is handled by Tag::setAttribute)
    $stringValue = $value !== null ? (string)$value : null;

    $this->tag->setAttribute($key, $stringValue, $doubleQuote);

    // Clear any cache
    $this->clear();

    return $this;
}
}