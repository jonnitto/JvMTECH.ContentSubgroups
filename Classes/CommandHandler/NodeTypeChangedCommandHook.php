<?php
declare(strict_types=1);

namespace JvMTECH\ContentSubgroups\CommandHandler;

use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\EventStore\PublishedEvents;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesForName;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Command\ChangeNodeAggregateType;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Dto\NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\Neos\Domain\SubtreeTagging\NeosVisibilityConstraints;
use Neos\Utility\Arrays;

class NodeTypeChangedCommandHook implements CommandHookInterface
{

    public function __construct(
        protected NodeTypeManager $nodeTypeManager,
        protected ContentGraphReadModelInterface $contentGraphReadModel,
    )
    {
    }

    public function onBeforeHandle(CommandInterface $command): CommandInterface
    {
        return $command;
    }

    public function onAfterHandle(CommandInterface $command, PublishedEvents $events): Commands
    {

        if (
            !$command instanceof SetNodeProperties
            || $command->propertyValues->isEmpty()
            || !array_key_exists('targetNodeTypeName', $command->propertyValues->values)
        ) {
            return Commands::createEmpty();
        }

        $contentGraph = $this->contentGraphReadModel->getContentGraph($command->workspaceName);
        $newNodeType = $this->nodeTypeManager->getNodeType($command->propertyValues->values['targetNodeTypeName']);

        $nodeTypeCommands = Commands::create(
            ChangeNodeAggregateType::create(
                $command->workspaceName,
                $command->nodeAggregateId,
                $newNodeType->name,
                NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy::STRATEGY_DELETE,
            ),
        );
        $referenceCommands = Commands::createEmpty();

        if (empty($newNodeType->getProperties()['targetNodeTypeName'])) {
            return $nodeTypeCommands;
        }

        $nodeAggregate = $contentGraph->findNodeAggregateById($command->nodeAggregateId);
        $oldNodeTypeName = $nodeAggregate->nodeTypeName;

        $propertyMigrations = Arrays::getValueByPath($newNodeType->getOptions(), ['contentSubgroup', 'propertyMigrationFrom', $oldNodeTypeName->value]) ?: [];

        if (empty($propertyMigrations)) {
            return $nodeTypeCommands;
        }

        $nodes = Nodes::createEmpty();
        foreach ($nodeAggregate->coveredDimensionSpacePoints as $dimensionSpacePoint) {
            $subgraph = $contentGraph->getSubgraph(
                $dimensionSpacePoint,
                NeosVisibilityConstraints::excludeRemoved(),
            );
            $node = $subgraph->findNodeById($command->nodeAggregateId);
            if ($node instanceof Node) {
               $nodes = $nodes->append($node);
            }
        }

        foreach ($propertyMigrations as $oldPropertyName => $newPropertyName) {
            foreach ($nodes as $node) {
                if ($node->hasProperty($oldPropertyName)) {
                    $nodeTypeCommands = $nodeTypeCommands->append(SetNodeProperties::create(
                        $command->workspaceName,
                        $node->aggregateId,
                        $node->originDimensionSpacePoint,
                        PropertyValuesToWrite::fromArray([
                            $newPropertyName => $node->getProperty($oldPropertyName),
                        ])
                    ));
                }
                $references = $subgraph->findReferences(
                    $node->aggregateId,
                    FindReferencesFilter::create(referenceName: $oldPropertyName)
                );
                if ($references->count() > 0) {
                    $nodeTypeCommands = $nodeTypeCommands->append(SetNodeReferences::create(
                        $command->workspaceName,
                        $node->aggregateId,
                        $node->originDimensionSpacePoint,
                        NodeReferencesToWrite::fromArray([
                            NodeReferencesForName::fromTargets(
                                ReferenceName::fromString($newPropertyName),
                                NodeAggregateIds::fromArray($references->getNodes()->map(fn(Node $referenceNode) => $referenceNode->aggregateId)),
                            )]),
                    ));
                }
            }
            foreach ($nodes as $node) {
                $references = $subgraph->findReferences(
                    $node->aggregateId,
                    FindReferencesFilter::create(referenceName: $oldPropertyName)
                );
                if ($references->count() > 0) {
                    $referenceCommands = $referenceCommands->append(SetNodeReferences::create(
                        $command->workspaceName,
                        $node->aggregateId,
                        $node->originDimensionSpacePoint,
                        NodeReferencesToWrite::fromArray([
                            NodeReferencesForName::createEmpty(ReferenceName::fromString($oldPropertyName))
                        ])
                    ));
                }
            }
        }
        foreach ($nodeTypeCommands as $nodeTypeCommand) {
            $referenceCommands = $referenceCommands->append($nodeTypeCommand);
        }
        return $referenceCommands;
    }
}
