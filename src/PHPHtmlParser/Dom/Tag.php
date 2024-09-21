<?php

declare(strict_types=1);

namespace PHPHtmlParser\Dom;

use PHPHtmlParser\DTO\Tag\AttributeDTO;
use PHPHtmlParser\Exceptions\Tag\AttributeNotFoundException;
use stringEncode\Encode;

/**
 * Class Tag.
 */
class Tag
{
    /**
     * The name of the tag.
     *
     * @var string
     */
    protected $name;

    /**
     * The attributes of the tag.
     *
     * @var AttributeDTO[]
     */
    protected $attr = [];

    /**
     * Is this tag self closing.
     *
     * @var bool
     */
    protected $selfClosing = false;

    /**
     * If self-closing, will this use a trailing slash. />.
     *
     * @var bool
     */
    protected $trailingSlash = true;

    /**
     * Tag noise.
     */
    protected $noise = '';

    /**
     * The encoding class to... encode the tags.
     *
     * @var Encode|null
     */
    protected $encode;

    /**
     * @var bool
     */
    private $HtmlSpecialCharsDecode = false;

    /**
     * What the opening of this tag will be.
     *
     * @var string
     */
    private $opening = '<';

    /**
     * What the closing tag for self-closing elements should be.
     *
     * @var string
     */
    private $closing = ' />';

    /**
     * Sets up the tag with a name.
     *
     * @param $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name of this tag.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Sets the tag to be self closing.
     */
    public function selfClosing(): Tag
    {
        $this->selfClosing = true;

        return clone $this;
    }

    public function setOpening(string $opening): Tag
    {
        $this->opening = $opening;

        return clone $this;
    }

    public function setClosing(string $closing): Tag
    {
        $this->closing = $closing;

        return clone $this;
    }

    /**
     * Sets the tag to not use a trailing slash.
     */
    public function noTrailingSlash(): Tag
    {
        $this->trailingSlash = false;

        return clone $this;
    }

    private $html5VoidElements = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr'
    ];
    public function isComment(): bool
    {
        return strpos($this->name, '!--') === 0;
    }

    public function isSelfClosing(): bool
    {
        return $this->selfClosing || in_array(strtolower($this->name), $this->html5VoidElements);
    }

    /**
     * Sets the encoding type to be used.
     */
    public function setEncoding(Encode $encode): void
    {
        $this->encode = $encode;
    }

    /**
     * @param bool $htmlSpecialCharsDecode
     */
    public function setHtmlSpecialCharsDecode($htmlSpecialCharsDecode = false): void
    {
        $this->HtmlSpecialCharsDecode = $htmlSpecialCharsDecode;
    }

    /**
     * Sets the noise for this tag (if any).
     */
    public function noise(string $noise): Tag
    {
        $this->noise = $noise;

        return clone $this;
    }

    /**
     * Sets the attribute to the given value.
     *
     * @param string $key
     * @param string|bool|null $attributeValue
     * @param bool $doubleQuote
     * @return Tag
     */
    public function setAttribute(string $key, $value): void
    {
        // Handle data-* attributes
        if (strpos($key, 'data-') === 0) {
            $this->attr[$key] = AttributeDTO::makeFromPrimitives($value);
        } else {
            $this->attr[strtolower($key)] = AttributeDTO::makeFromPrimitives($value);
        }
    }

    /**
     * Set inline style attribute value.
     *
     * @param mixed $attr_key
     * @param mixed $attr_value
     */
    public function setStyleAttributeValue($attr_key, $attr_value): void
    {
        $style_array = $this->getStyleAttributeArray();
        $style_array[$attr_key] = $attr_value;

        $style_string = '';
        foreach ($style_array as $key => $value) {
            $style_string .= $key . ':' . $value . ';';
        }

        $this->setAttribute('style', $style_string);
    }

    /**
     * Get style attribute in array.
     */
    public function getStyleAttributeArray(): array
    {
        try {
            $value = $this->getAttribute('style')->getValue();
            if (\is_null($value)) {
                return [];
            }
            $value = \explode(';', \substr(\trim($value), 0, -1));
            $result = [];
            foreach ($value as $attr) {
                $attr = \explode(':', $attr);
                $result[$attr[0]] = $attr[1];
            }

            return $result;
        } catch (AttributeNotFoundException $e) {
            unset($e);

            return [];
        }
    }

    /**
     * Removes an attribute from this tag.
     *
     * @param mixed $key
     *
     * @return void
     */
    public function removeAttribute($key)
    {
        $key = \strtolower($key);
        unset($this->attr[$key]);
    }

    /**
     * Removes all attributes on this tag.
     *
     * @return void
     */
    public function removeAllAttributes()
    {
        $this->attr = [];
    }

    /**
     * Sets the attributes for this tag.
     *
     * @return $this
     */
    public function setAttributes(array $attr)
    {
        foreach ($attr as $key => $info) {
            if (\is_array($info)) {
                $this->setAttribute($key, $info['value'], $info['doubleQuote']);
            } else {
                $this->setAttribute($key, $info);
            }
        }

        return $this;
    }

    /**
     * Returns all attributes of this tag.
     *
     * @throws \stringEncode\Exception
     *
     * @return AttributeDTO[]
     */
    public function getAttributes(): array
    {
        $return = [];
        foreach (\array_keys($this->attr) as $attr) {
            try {
                $return[$attr] = $this->getAttribute($attr);
            } catch (AttributeNotFoundException $e) {
                // attribute that was in the array was not found in the array....
                unset($e);
            }
        }

        return $return;
    }

   /**
 * Returns an attribute by the key.
 *
 * @throws AttributeNotFoundException
 * @throws \stringEncode\Exception
 */
public function getAttribute(string $key): AttributeDTO
{
    $key = \strtolower($key);
    if (!isset($this->attr[$key])) {
        throw new AttributeNotFoundException('Attribute with key "' . $key . '" not found.');
    }
    $attributeDTO = $this->attr[$key];
    if (!\is_null($this->encode)) {
        // convert charset
        $attributeDTO->encodeValue($this->encode);
    }

    // Ensure the value is always a string
    $value = $attributeDTO->getValue();
    if (!is_null($value) && !is_string($value)) {
        // Create a new AttributeDTO with the string value
        return AttributeDTO::makeFromPrimitives((string)$value, $attributeDTO->isDoubleQuote());
    }

    return $attributeDTO;
}
/**
 * Returns all data attributes.
 *
 * @return array<string, string>
 */
public function getDataAttributes(): array
{
    $dataAttributes = [];
    foreach ($this->attr as $key => $attributeDTO) {
        if (is_string($key) && strpos($key, 'data-') === 0) {
            $dataKey = substr($key, 5);
            if ($dataKey !== false) {
                $value = $attributeDTO->getValue();
                $dataAttributes[$dataKey] = $value !== null ? (string)$value : '';
            }
        }
    }
    return $dataAttributes;
}


   /**
 * Checks if the attribute is a boolean attribute.
 *
 * @param string $key
 * @return bool
 */
private function isBooleanAttribute(string $key): bool
{
    $booleanAttributes = [
        'allowfullscreen', 'allowpaymentrequest', 'async', 'autofocus',
        'autoplay', 'checked', 'controls', 'default', 'defer', 'disabled',
        'formnovalidate', 'hidden', 'ismap', 'itemscope', 'loop', 'multiple',
        'muted', 'nomodule', 'novalidate', 'open', 'readonly', 'required',
        'reversed', 'selected', 'typemustmatch'
    ];

    return in_array(strtolower($key), $booleanAttributes);
}

    /**
     * Returns TRUE if node has attribute.
     *
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return isset($this->attr[\strtolower($key)]);
    }

    /**
     * Generates the opening tag for this object.
     *
     * @return string
     */
    
    public function makeOpeningTag(): string
    {
        if ($this->isComment()) {
            return $this->opening . substr($this->name, 3);
        }

        $return = '<'.$this->name;

        // handle attributes
        foreach ($this->attr as $key => $attributeDTO) {
            $return .= ' '.$key;
            
            // Handle boolean attributes
            if ($attributeDTO->getValue() === true) {
                continue;
            }
            
            if ($attributeDTO->getValue() !== null && $attributeDTO->getValue() !== '') {
                $return .= '='.$attributeDTO->getOpeningQuote().$attributeDTO->getValue().$attributeDTO->getClosingQuote();
            }
        }

        // handle void elements
        if ($this->isSelfClosing()) {
            $return .= ' /';
        }

        $return .= '>';

        return $return;
    }

    /**
     * Generates the closing tag for this object.
     *
     * @return string
     */
    public function makeClosingTag()
    {
        if ($this->isComment()) {
            return $this->closing;
        }
        
        if ($this->selfClosing) {
            return '';
        }

        return '</' . $this->name . '>';
    }
}