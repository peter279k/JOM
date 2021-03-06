<?php declare(strict_types=1);

namespace DaveRandom\Jom;

use DaveRandom\Jom\Exceptions\InvalidNodeValueException;
use DaveRandom\Jom\Exceptions\InvalidSubjectNodeException;
use DaveRandom\Jom\Exceptions\ParseFailureException;
use DaveRandom\Jom\Exceptions\WriteOperationForbiddenException;
use ExceptionalJSON\DecodeErrorException;

final class Document implements \JsonSerializable, Taggable
{
    use TagData;

    public const IGNORE_INVALID_VALUES = Node::IGNORE_INVALID_VALUES;

    /** @var NodeFactory */
    private static $nodeFactory;

    /** @var Node */
    private $rootNode;

    /** @uses __init() */
    private static function __init(): void
    {
        self::$nodeFactory = new SafeNodeFactory();
    }

    /**
     * @throws InvalidNodeValueException
     * @throws InvalidSubjectNodeException
     * @throws WriteOperationForbiddenException
     */
    private function importVectorNode(VectorNode $node): VectorNode
    {
        if (!\in_array(\get_class($node), [ArrayNode::class, ObjectNode::class])) {
            throw new InvalidSubjectNodeException('Source node is of unknown type: ' . \get_class($node));
        }

        $newNode = new $node(null, $this);

        foreach ($node as $key => $value) {
            $newNode[$key] = $this->import($value);
        }

        return $newNode;
    }

    /**
     * @throws InvalidSubjectNodeException
     */
    private function importScalarNode(Node $node): Node
    {
        if (!\in_array(\get_class($node), [BooleanNode::class, NumberNode::class, StringNode::class])) {
            throw new InvalidSubjectNodeException('Source node is of unknown type: ' . \get_class($node));
        }

        try {
            return Node::createFromValue($node->getValue(), $this);
        //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw unexpected($e);
        }
        //@codeCoverageIgnoreEnd
    }

    private function __construct() { }

    public function __clone()
    {
        try {
            $this->rootNode = $this->import($this->rootNode);
        //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw unexpected($e);
        }
        //@codeCoverageIgnoreEnd
    }

    /**
     * @throws ParseFailureException
     */
    public static function parse(string $json, ?int $depthLimit = 512, ?int $options = 0): Document
    {
        $depthLimit = $depthLimit ?? 512;
        $options = ($options ?? 0) & ~\JSON_OBJECT_AS_ARRAY;

        try {
            $data = \ExceptionalJSON\decode($json, false, $depthLimit, $options);

            $doc = new self();
            $doc->rootNode = self::$nodeFactory->createNodeFromValue($data, $doc, 0);

            return $doc;
        } catch (DecodeErrorException $e) {
            throw new ParseFailureException("Decoding JSON string failed: {$e->getMessage()}", $e);
        //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw unexpected($e);
        }
        //@codeCoverageIgnoreEnd
    }

    /**
     * @param bool|int|float|string|array|object|null A value that can be encoded as JSON
     * @throws InvalidNodeValueException
     */
    public static function createFromValue($value, ?int $flags = 0): Document
    {
        try {
            $doc = new self();
            $doc->rootNode = Node::createFromValue($value, $doc, $flags);

            return $doc;
        } catch (InvalidNodeValueException $e) {
            throw $e;
        //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw unexpected($e);
        }
        //@codeCoverageIgnoreEnd
    }

    public static function createFromNode(Node $node): Document
    {
        try {
            $doc = new self();
            $doc->rootNode = $doc->import($node);

            return $doc;
        //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw unexpected($e);
        }
        //@codeCoverageIgnoreEnd
    }

    public function getRootNode(): Node
    {
        return $this->rootNode;
    }

    /**
     * @throws InvalidSubjectNodeException
     * @throws WriteOperationForbiddenException
     * @throws InvalidNodeValueException
     * @throws InvalidSubjectNodeException
     */
    public function import(Node $node): Node
    {
        if ($node->getOwnerDocument() === $this) {
            throw new InvalidSubjectNodeException('Cannot import tne supplied node, already owned by this document');
        }

        if ($node instanceof NullNode) {
            return new NullNode($this);
        }

        return $node instanceof VectorNode
            ? $this->importVectorNode($node)
            : $this->importScalarNode($node);
    }

    public function jsonSerialize()
    {
        return $this->rootNode !== null
            ? $this->rootNode->jsonSerialize()
            : null;
    }
}

\DaveRandom\Jom\initialize(Document::class);
