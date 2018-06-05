<?php declare(strict_types=1);

namespace DaveRandom\Jom;

final class SafeNodeFactory extends NodeFactory
{
    /**
     * @inheritdoc
     */
    protected function createNodeFromArrayValue(array $array, ?Document $ownerDoc, int $flags): VectorNode
    {
        return $this->createArrayNodeFromPackedArray($array, $ownerDoc, $flags);
    }

    /**
     * @inheritdoc
     */
    protected function createNodeFromObjectValue(object $object, ?Document $ownerDoc, int $flags): ?Node
    {
        return $this->createObjectNodeFromPropertyMap($object, $ownerDoc, $flags);
    }
}
