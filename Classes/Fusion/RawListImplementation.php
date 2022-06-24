<?php

namespace Garagist\ImageDirectory\Fusion;

use Flowpack\EntityUsage\Service\EntityUsageService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Exception as FusionException;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Repository\AssetRepository;
use function json_encode;

/**
 * Returns the list of assets used on a site
 */
class RawListImplementation extends AbstractFusionObject
{
    /**
     * @Flow\Inject(name="Flowpack.Neos.AssetUsage:AssetUsageService")
     * @var EntityUsageService
     */
    protected $assetUsageService;

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * An internal cache for the built nodes items array.
     *
     * @var array
     */
    protected $items;

    /**
     * An internal cache for the instance types for assets
     *
     * @var array
     */
    protected $instances;

    /**
     * Internal cache for the renderHiddenInIndex property.
     *
     * @var boolean
     */
    protected $renderHiddenInIndex;

    /**
     * Internal cache for the startingPoint Value.
     *
     * @var NodeInterface
     */
    protected $startingPoint;

    /**
     * Internal cache for the asset usage.
     *
     * @var array
     */
    protected $assetUsage;

    /**
     * Internal cache for the used dimensions
     *
     * @var string
     */
    protected $dimensions;

    /**
     * Internal cache for the used workspace
     *
     * @var array
     */
    protected $workspace;

    /**
     * Internal cache for the context
     *
     * @var array
     */
    protected $context;

    /**
     * Returns the items as result of the fusion object.
     *
     * @return array
     */
    public function evaluate(): array
    {
        $fusionContext = $this->runtime->getCurrentContext();
        $startingPoint = $this->fusionValue('startingPoint');
        $startingPoint = $startingPoint ?: $fusionContext['site'];
        $instances = $this->fusionValue('instances');
        if ($instances && is_string($instances)) {
            $this->instances = explode(',', $instances);
        }
        $this->assetUsage = $this->assetUsageService->getAllUsages();
        $this->dimensions = json_encode($startingPoint->getDimensions());
        $this->workspace = $startingPoint->getWorkspace()->getName();
        $this->startingPoint = $startingPoint;
        $this->context = $startingPoint->getContext();
        return $this->getDocumentTree();
    }

    /**
     * Method which sends the to-be-rendered data
     *
     * @return array
     * @throws FusionException
     */
    public function getDocumentTree(): array
    {
        if (isset($this->items)) {
            return $this->items;
        }

        $this->buildDocumentLevelRecursive([$this->startingPoint]);
        if (!isset($this->items)) {
            return [];
        }
        return $this->items;
    }

    /**
     * Should nodes that have "hiddenInIndex" set still be visible
     *
     * @return boolean
     */
    public function getRenderHiddenInIndex()
    {
        if ($this->renderHiddenInIndex === null) {
            $this->renderHiddenInIndex = (bool)$this->fusionValue('renderHiddenInIndex');
        }

        return $this->renderHiddenInIndex;
    }

    /**
     * NodeType filter for nodes
     *
     * @return string
     */
    public function getFilter()
    {
        $filter = $this->fusionValue('filter');
        if ($filter === null) {
            $filter = 'Neos.Neos:Document';
        }
        return $filter;
    }

    /**
     * @param array $nodes
     * @return void
     */
    protected function buildDocumentLevelRecursive(array $nodes): void
    {
        foreach ($nodes as $currentNode) {
            $this->buildDocumentItemRecursive($currentNode);
        }
    }

    /**
     * Prepare the item and sub items
     *
     * @param NodeInterface $currentNode
     * @return void
     */
    protected function buildDocumentItemRecursive(NodeInterface $currentNode): void
    {
        // Is the node hidden, return null
        if ($currentNode->isVisible() === false || ($this->getRenderHiddenInIndex() === false && $currentNode->isHiddenInIndex() === true) || $currentNode->isAccessible() === false) {
            return;
        }
        $currentNodeIdentifier = $currentNode->getIdentifier();
        $assetEntries = [];
        foreach ($this->assetUsage as $entityUsage) {
            $metadata = $entityUsage->getMetadata();
            $nodeIdentifier = $metadata['nodeIdentifier'];
            if (
                !$nodeIdentifier ||
                $metadata['workspace'] != $this->workspace ||
                $metadata['dimensions'] != $this->dimensions
            ) {
                continue;
            }
            $node = $this->context->getNodeByIdentifier($nodeIdentifier);
            if (!isset($node)) {
                continue;
            }
            $flowQuery = new FlowQuery([$node]);
            $documentNode = $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);
            if (!isset($documentNode)) {
                continue;
            }
            $documentNodeIdentifier = $documentNode->getIdentifier();
            if ($currentNodeIdentifier != $documentNodeIdentifier) {
                continue;
            }
            $id = $entityUsage->getEntityId();
            if (isset($assetEntries[$id])) {
                continue;
            }

            /** @var AssetInterface $asset */
            $asset = $this->assetRepository->findByIdentifier($id);

            if ($this->assetHasCorrectInstance($asset)) {
                $assetEntries[$id] = $asset;
            }
        }

        if (count($assetEntries)) {
            $this->items[] = [
                'node' => $currentNode,
                'identifier' => $currentNodeIdentifier,
                'label' => $currentNode->getLabel(),
                'title' => $currentNode->getProperty('title'),
                'nodeType' => $currentNode->getNodeType()->getName(),
                'assets' => $assetEntries,
            ];
        }

        $this->buildDocumentLevelRecursive($currentNode->getChildNodes($this->getFilter()));
    }

    /**
     * Check if the asset as the correct type
     *
     * @param AssetInterface $asset
     * @return boolean
     */
    protected function assetHasCorrectInstance(?AssetInterface $asset): bool
    {
        if (!isset($asset)) {
            return false;
        }

        if (!isset($this->instances)) {
            return true;
        }

        foreach ($this->instances as $expectedObjectType) {
            if ($asset instanceof $expectedObjectType) {
                return true;
            }
        }

        return false;
    }
}
