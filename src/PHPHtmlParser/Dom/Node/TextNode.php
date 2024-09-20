<?php

declare(strict_types=1);

namespace PHPHtmlParser\Dom\Node;

use PHPHtmlParser\Dom\Tag;
use PHPHtmlParser\Exceptions\LogicalException;

/**
 * Class TextNode.
 *
 * @property-read string    $outerhtml
 * @property-read string    $innerhtml
 * @property-read string    $innerText
 * @property-read string    $text
 * @property-read Tag       $tag
 * @property-read InnerNode $parent
 */
class TextNode extends LeafNode
{
    /**
     * This is a text node.
     *
     * @var Tag
     */
    protected $tag;

    /**
     * This is the text in this node.
     *
     * @var string
     */
    protected $text;

    /**
     * This is the converted version of the text.
     *
     * @var ?string
     */
    protected $convertedText;

    /**
     * Sets the text for this node.
     *
     * @param bool $removeDoubleSpace
     */
    public function __construct(string $text, $removeDoubleSpace = true)
    {
        if ($removeDoubleSpace) {
            // remove double spaces
            $replacedText = \mb_ereg_replace('\s+', ' ', $text);
            if ($replacedText === false) {
                throw new LogicalException('mb_ereg_replace returns false when attempting to clean white space from "' . $text . '".');
            }
            $text = $replacedText;
        }

        // restore line breaks
        $text = \str_replace('&#10;', "\n", $text);

        $this->text = $text;
        $this->tag = new Tag('text');
        parent::__construct();
    }

    
    /**
     * @param bool $htmlSpecialCharsDecode
     */
    public function setHtmlSpecialCharsDecode($htmlSpecialCharsDecode = false): void
    {
        parent::setHtmlSpecialCharsDecode($htmlSpecialCharsDecode);
        $this->tag->setHtmlSpecialCharsDecode($htmlSpecialCharsDecode);
    }

    /**
     * Returns the text of this node.
     */
    public function text(): string
    {
        if ($this->tag->name() !== 'text') {
            $text = '';
            $relevantAttributes = ['value', 'placeholder', 'title', 'alt', 'aria-label'];
            foreach ($relevantAttributes as $attr) {
                $attrValue = $this->tag->getAttribute($attr);
                if ($attrValue !== null) {
                    $text .= $attrValue->getValue() . ' ';
                }
            }
            // Add the actual text content
            $text .= $this->text;
            return trim($text);
        }

        // Original text() method for regular text nodes
        if ($this->htmlSpecialCharsDecode) {
            $text = \htmlspecialchars_decode($this->text);
        } else {
            $text = $this->text;
        }

        // Convert charset if needed
        if (!\is_null($this->encode)) {
            if (!\is_null($this->convertedText)) {
                return $this->convertedText;
            }
            $text = $this->encode->convert($text);
            $this->convertedText = $text;
        }

        return $text;
    }

   
    /**
     * Sets the text for this node.
     *
     * @var string
     */
    public function setText(string $text): void
    {
        if ($this->tag->name() !== 'text') {
            $relevantAttributes = ['value', 'placeholder', 'title', 'alt', 'aria-label'];
            $parts = explode(' ', $text, count($relevantAttributes) + 1);
            foreach ($relevantAttributes as $index => $attr) {
                if (isset($parts[$index])) {
                    $this->tag->setAttribute($attr, $parts[$index]);
                }
            }
            // Set remaining text as content
            $this->text = implode(' ', array_slice($parts, count($relevantAttributes)));
        } else {
            $this->text = $text;
        }

        if (!\is_null($this->encode)) {
            $encodedText = $this->encode->convert($text);
            $this->convertedText = $encodedText;
        }
    
    }

    /**
     * This node has no html, just return the text.
     *
     * @uses $this->text()
     */
    public function innerHtml(): string
    {
        return $this->text();
    }

    /**
     * This node has no html, just return the text.
     *
     * @uses $this->text()
     */
    public function outerHtml(): string
    {
        return $this->text();
    }

    /**
     * Checks if the current node is a text node.
     */
    public function isTextNode(): bool
    {
        return true;
    }

    /**
     * Call this when something in the node tree has changed. Like a child has been added
     * or a parent has been changed.
     */
    protected function clear(): void
    {
        $this->convertedText = null;
    }

     /**
 * Get all data-* attributes of the node.
 */
public function getDataAttributes(): array
{
    $dataAttributes = [];
    foreach ($this->tag->getAttributes() as $key => $value) {
        if (is_string($key) && strpos($key, 'data-') === 0) {
            $dataAttributes[$key] = $value->getValue();
        }
    }
    return $dataAttributes;
}
}