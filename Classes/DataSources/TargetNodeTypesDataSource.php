<?php
declare(strict_types=1);

namespace JvMTECH\ContentSubgroups\DataSources;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\Factory\NodeTypeManager\NodeTypeManagerFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\Neos\Service\IconNameMappingService;
use Neos\Utility\Arrays;

class TargetNodeTypesDataSource extends AbstractDataSource
{
    /** @var string */
    protected static $identifier = 'jvmtech-contentsubgroups-target-nodetypes';
    #[Flow\Inject]
    protected NodeTypeManagerFactoryInterface $nodeTypeManagerFactory;
    #[Flow\Inject]
    protected TranslationHelper $translationHelper;
    #[Flow\Inject]
    protected IconNameMappingService $iconNameMappingService;

    public function getData(?Node $node = null, array $arguments = []): array
    {
        if (empty($node)) {
            return [];
        }

        $nodeTypeManager = $this->nodeTypeManagerFactory->build($node->contentRepositoryId, []);
        $baseTag = Arrays::getValueByPath($arguments, 'contentSubgroup');

        $nodeTypes = $nodeTypeManager->getNodeTypes(false);

        // Search for NodeType Groups...

        /** @var NodeType[] $groupNodeTypes */
        $groupNodeTypes = array_filter($nodeTypes, function (NodeType $nodeType) {
            return !!Arrays::getValueByPath(
                $nodeType->getProperties(),
                'targetNodeTypeName.ui.inspector.editorOptions.dataSourceAdditionalData.contentSubgroup',
            );
        });

        $groupNodeTypes = array_map(function (NodeType $nodeType) {
            $array = [
                'label' => $this->translationHelper->translate($nodeType->getLabel()) ?: $nodeType->getLabel(),
                'tag' => Arrays::getValueByPath(
                    $nodeType->getProperties(),
                    'targetNodeTypeName.ui.inspector.editorOptions.dataSourceAdditionalData.contentSubgroup',
                ),
            ];
            return $array;
        }, $groupNodeTypes);

        // Use tag as array key...

        $groups = array_combine(array_column($groupNodeTypes, 'tag'), $groupNodeTypes);
        // Search for NodeType Subgroups...

        $subNodeTypes = array_filter($nodeTypes, function (NodeType $nodeType) use ($baseTag) {
            $tags = Arrays::getValueByPath($nodeType->getOptions(), 'contentSubgroup.tags');
            if (!$tags) {
                return false;
            }

            return !$baseTag || in_array($baseTag, $tags);
        });

        $subNodeTypes = array_map(function (NodeType $nodeType) {

            return [
                'label' => $nodeType->getLabel(),
                'value' => $nodeType->name->value,
                'tags' => Arrays::getValueByPath($nodeType->getOptions(), 'contentSubgroup.tags') ?: [],
                'icon' => $this->iconNameMappingService->convert($nodeType->getConfiguration('ui.icon')),
            ];
        }, $subNodeTypes);

        // Create select options...

        $groupedOptions = [];
        foreach ($subNodeTypes as $subNodeType) {
            foreach ($subNodeType['tags'] as $tag) {
                $groupedOptions[] = [
                    'label' => $subNodeType['label'],
                    'value' => $subNodeType['value'],
                    'icon' => $subNodeType['icon'],
                    'group' => isset($groups[$tag]['label']) ? $groups[$tag]['label'] : null,
                ];
            }
        }

        return $groupedOptions;
    }
}
