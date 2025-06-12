<?php
declare(strict_types=1);

namespace JvMTECH\ContentSubgroups\DataSources;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Service\IconNameMappingService;
use Neos\Utility\Arrays;
use Neos\Utility\PositionalArraySorter;

class TargetNodeTypesDataSource extends AbstractDataSource
{
    /**
     * @var string
     */
    protected static $identifier = 'jvmtech-contentsubgroups-target-nodetypes';

    /**
     * @var NodeTypeManager
     * @Flow\Inject
     */
    protected NodeTypeManager $nodeTypeManager;

    /**
     * @var TranslationHelper
     * @Flow\Inject
     */
    protected TranslationHelper $translationHelper;

    /**
     * @Flow\Inject
     * @var IconNameMappingService
     */
    protected $iconNameMappingService;

    /**
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return array
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $baseTag = Arrays::getValueByPath($arguments, 'contentSubgroup');

        $contentCollectionNode = $this->getClosestContentCollection($node);

        $nodeTypes = $this->nodeTypeManager->getNodeTypes(false);

        // Search for NodeType Groups...

        $groupNodeTypes = array_filter($nodeTypes, function (NodeType $nodeType) use ($baseTag) {
            $tag = Arrays::getValueByPath($nodeType->getProperties(), 'targetNodeTypeName.ui.inspector.editorOptions.dataSourceAdditionalData.contentSubgroup');
            if (!$tag) {
                return false;
            }

            return true;
        });

        $groupNodeTypes = array_map(function (NodeType $nodeType) {
            return [
                'label' => $this->translationHelper->translate($nodeType->getLabel()) ?: $nodeType->getLabel(),
                'tag' => Arrays::getValueByPath($nodeType->getProperties(), 'targetNodeTypeName.ui.inspector.editorOptions.dataSourceAdditionalData.contentSubgroup'),
                'position' => $nodeType->getConfiguration('ui.position'),
            ];
        }, $groupNodeTypes);
        $sorterGroup = new PositionalArraySorter($groupNodeTypes);
        $groupNodeTypes = $sorterGroup->toArray();

        // Use tag as array key...

        $groups = array_combine(array_column($groupNodeTypes, 'tag'), $groupNodeTypes);

        // Search for NodeType Subgroups...

        $subNodeTypes = array_filter($nodeTypes, function (NodeType $nodeType) use ($baseTag, $contentCollectionNode) {
            // if (!$contentCollectionNode->isNodeTypeAllowedAsChildNode($nodeType)) {
            //     return false;
            // }

            $tags = Arrays::getValueByPath($nodeType->getOptions(), 'contentSubgroup.tags');
            if (!$tags) {
                return false;
            }

            return $baseTag ? in_array($baseTag, $tags) : true;
        });

        $subNodeTypes = array_map(function (NodeType $nodeType) {
            return [
                'label' => $nodeType->getLabel(),
                'value' => $nodeType->getName(),
                'tags' => Arrays::getValueByPath($nodeType->getOptions(), 'contentSubgroup.tags') ?: [],
                'icon' => $this->iconNameMappingService->convert($nodeType->getConfiguration('ui.icon')),
                'position' => $nodeType->getConfiguration('ui.position'),
            ];
        }, $subNodeTypes);
        // Sort subNodeTypes by position
        $sorterSubNodeTypes = new PositionalArraySorter($subNodeTypes);
        $subNodeTypes = $sorterSubNodeTypes->toArray();

        // Create groups with placeholder if no group is set
        // Create groups to have to order of the groups correctly
        foreach ($groups as $tag => $group) {
            $groupedOptions[$tag] = [];
        }
        foreach ($subNodeTypes as $subNodeType) {
            foreach ($subNodeType['tags'] as $tag) {
                $groupedOptions[$tag][] = [
                    'label' => $subNodeType['label'],
                    'value' => $subNodeType['value'],
                    'icon' => $subNodeType['icon'],
                    'group' => isset($groups[$tag]['label']) ? $groups[$tag]['label'] : null,
                ];
            }
        }

        // Create select options by extracting sub elements to the parent level
        $resultArray= [];
        foreach ($groupedOptions as $groupedOption) {
            foreach ($groupedOption as $option) {
                $resultArray[] = $option;
            }
        }
        return $resultArray;
    }

    /**
     * @param NodeInterface $node
     * @return NodeInterface
     */
    protected function getClosestContentCollection(NodeInterface $node)
    {
        do {
            if ($node->getNodeType()->isOfType('Neos.Neos:ContentCollection') && !$node->getNodeType()->isOfType('Neos.Neos:Content')) {
                break;
            }
        } while ($node = $node->findParentNode());

        return $node;
    }
}
