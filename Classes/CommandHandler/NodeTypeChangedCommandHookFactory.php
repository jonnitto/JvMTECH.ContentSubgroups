<?php
declare(strict_types=1);

namespace JvMTECH\ContentSubgroups\CommandHandler;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactoryDependencies;

#[Flow\Proxy(false)]
class NodeTypeChangedCommandHookFactory implements CommandHookFactoryInterface
{
    public function build(CommandHooksFactoryDependencies $commandHooksFactoryDependencies,): CommandHookInterface
    {
        return new NodeTypeChangedCommandHook(
            $commandHooksFactoryDependencies->nodeTypeManager,
            $commandHooksFactoryDependencies->contentGraphReadModel,
        );
    }
}
