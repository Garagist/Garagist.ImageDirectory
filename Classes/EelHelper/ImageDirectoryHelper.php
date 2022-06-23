<?php

namespace Garagist\ImageDirectory\EelHelper;

use Flowpack\EntityUsage\Service\EntityUsageService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Media\Domain\Repository\AssetRepository;

use function Neos\Flow\var_dump;

class ImageDirectoryHelper implements ProtectedContextAwareInterface
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
     * Get list of assets
     *
     * @param NodeInterface $site the site node
     * @return array
     */
    public function list(NodeInterface $site): array
    {
        $dimensions = json_encode($site->getDimensions());
        $workspace = $site->getWorkspace()->getName();
        $context = $site->getContext();
        $entries = [];

        foreach ($this->assetUsageService->getAllUsages() as $entityUsage) {
            $metadata = $entityUsage->getMetadata();
            $nodeIdentifier = $metadata['nodeIdentifier'];
            if ($metadata['workspace'] != $workspace || $metadata['dimensions'] != $dimensions || !$nodeIdentifier) {
                continue;
            }

            $node = $context->getNodeByIdentifier($nodeIdentifier);
            if (!isset($node)) {
                continue;
            }

            $flowQuery = new FlowQuery([$node]);
            $documentNode = $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);

            if (!isset($documentNode)) {
                continue;
            }

            $id = $entityUsage->getEntityId();
            $documentNodeIdentifier = $documentNode->getIdentifier();
            $isContentNode = $documentNodeIdentifier != $nodeIdentifier;

            /** @var AssetInterface $asset */
            $asset = $this->assetRepository->findByIdentifier($id);

            if ($isContentNode) {
                $contentNodeEntry = [
                    'node' => $node,
                    'nodeType' => $metadata['nodeType'],
                ];
            }

            // Set Document Nodetype entry
            if (!isset($entries[$id]) || !isset($entries[$id]['documents'][$documentNodeIdentifier])) {
                $documentNodeEntry = [
                    'node' => $documentNode,
                    'title' => $documentNode->getProperty('title'),
                    'label' => $documentNode->getLabel(),
                    'nodeType' => $documentNode->getNodeType()->getName(),
                    'content' => [],
                ];
                if ($isContentNode) {
                    $documentNodeEntry['content'][$nodeIdentifier] = $contentNodeEntry;
                }
            }
            // Asset is already used and on the same page
            if (isset($entries[$id])) {

                // Already on the same page, push to document Node
                if (isset($entries[$id]['documents'][$documentNodeIdentifier])) {
                    if ($isContentNode && !isset($entries[$id]['documents'][$documentNodeIdentifier]['content'][$nodeIdentifier])) {
                        $entries[$id]['documents'][$documentNodeIdentifier]['content'][$nodeIdentifier] = $contentNodeEntry;
                    }
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

        return $entries;
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
