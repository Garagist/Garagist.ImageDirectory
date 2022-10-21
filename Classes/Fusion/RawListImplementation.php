<?php

namespace Garagist\ImageDirectory\Fusion;

use Flowpack\EntityUsage\Service\EntityUsageService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Exception as FusionException;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Repository\AssetRepository;
use function json_encode;
use function strtolower;
use function str_starts_with;

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
     * An internal cache for the media types for assets
     *
     * @var array
     */
    protected $mediaTypes;

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
        $mediaTypes = $this->fusionValue('mediaTypes');
        if ($mediaTypes && is_string($mediaTypes)) {
            $this->mediaTypes = explode(',', strtolower(str_replace('/*', '/', $mediaTypes)));
        }
        $this->assetUsage = $this->assetUsageService->getAllUsages();
        $this->dimensions = json_encode($startingPoint->getDimensions());
        $this->workspace = $startingPoint->getWorkspace()->getName();
        $this->startingPoint = $startingPoint;
        $this->context = $startingPoint->getContext();
        if (isset($this->items)) {
            return $this->items;
        }
        if ($this->fusionValue('sortBy') == 'document') {
            return $this->getDocumentTree();
        }
        return $this->getAssetTree();
    }

    /**
     * Method which sends the to-be-rendered data, sorted by asset
     *
     * @return array
     * @throws FusionException
     */
    public function getAssetTree(): array
    {
        $this->buildAssetTree();
        if (!isset($this->items)) {
            return [];
        }
        return $this->items;
    }

    /**
     * Method which sends the to-be-rendered data, sorted by document
     *
     * @return array
     * @throws FusionException
     */
    public function getDocumentTree(): array
    {
        $this->buildDocumentLevelRecursive([$this->startingPoint]);
        return $this->items ?? [];
    }

    /**
     * Builds the asset tree
     *
     * @return void
     * @throws FusionException
     */
    public function buildAssetTree(): void
    {
        $entries = [];
        foreach ($this->assetUsage as $entityUsage) {
            $entity = $this->entityUsage($entityUsage);
            if ($entity == null) {
                continue;
            }
            $id = $entity['id'];
            $asset =  $entity['asset'];
            $documentNode =  $entity['documentNode'];
            $documentNodeIdentifier =  $entity['documentNodeIdentifier'];

            // Set Document Nodetype entry
            if (!isset($entries[$id]) || !isset($entries[$id]['documents'][$documentNodeIdentifier])) {
                $documentNodeEntry = [
                    'node' => $documentNode,
                    'identifier' => $documentNodeIdentifier,
                    'label' => $documentNode->getLabel(),
                    'title' => $documentNode->getProperty('title'),
                    'nodeType' => $documentNode->getNodeType()->getName(),
                ];
            }
            // Asset is already used and on the same page
            if (isset($entries[$id])) {

                // Already on the same page, push to document Node
                if (isset($entries[$id]['documents'][$documentNodeIdentifier])) {
                    continue;
                }

                $entries[$id]['documents'][$documentNodeIdentifier] = $documentNodeEntry;
                continue;
            }

            $entries[$id] = [
                'asset' => $asset,
                'documents' => [
                    $documentNodeIdentifier => $documentNodeEntry
                ]
            ];
        }

        $this->items = $entries;
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
            $entity = $this->entityUsage($entityUsage);
            if ($entity == null) {
                continue;
            }
            $id = $entity['id'];
            $asset =  $entity['asset'];
            $documentNode =  $entity['documentNode'];
            $documentNodeIdentifier =  $entity['documentNodeIdentifier'];

            if ($currentNodeIdentifier != $documentNodeIdentifier) {
                continue;
            }

            if (isset($assetEntries[$id])) {
                continue;
            }

            $assetEntries[$id] = $asset;
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
    protected function assetHasCorrectMediaType(?AssetInterface $asset): bool
    {
        if (!isset($asset)) {
            return false;
        }

        if (!isset($this->mediaTypes)) {
            return true;
        }

        $mediaType = strtolower($asset->getMediaType());
        foreach ($this->mediaTypes as $expectedType) {
            if (str_starts_with($mediaType, $expectedType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $entityUsage
     * @return array|null
     */
    protected function entityUsage($entityUsage): ?array
    {
        $metadata = $entityUsage->getMetadata();
        $nodeIdentifier = $metadata['nodeIdentifier'];
        if (
            !$nodeIdentifier ||
            $metadata['workspace'] != $this->workspace ||
            $metadata['dimensions'] != $this->dimensions
        ) {
            return null;
        }

        $node = $this->context->getNodeByIdentifier($nodeIdentifier);

        if (!isset($node)) {
            return null;
        }

        $flowQuery = new FlowQuery([$node]);
        $documentNode = $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);

        if (!isset($documentNode)) {
            return null;
        }

        $id = $entityUsage->getEntityId();
        $documentNodeIdentifier = $documentNode->getIdentifier();

        /** @var AssetInterface $asset */
        $asset = $this->assetRepository->findByIdentifier($id);

        if (!$this->assetHasCorrectMediaType($asset)) {
            return null;
        }

        return [
            'id' => $id,
            'asset' => $asset,
            'documentNode' => $documentNode,
            'documentNodeIdentifier' => $documentNodeIdentifier,
        ];
    }
}
