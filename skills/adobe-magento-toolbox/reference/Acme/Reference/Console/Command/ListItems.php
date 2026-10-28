<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Console\Command;

use Acme\Reference\Api\Data\ItemInterface;
use Acme\Reference\Api\ItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento acme:reference:items:list [--status=1]
 *
 * Pattern: registered in etc/di.xml (CommandListInterface); heavy dependencies arrive as \Proxy
 * (every bin/magento call builds every command, so constructors must stay cheap); return
 * Command::SUCCESS or Command::FAILURE.
 */
class ListItems extends Command
{
    private const OPTION_STATUS = 'status';

    /**
     * @param ItemRepositoryInterface $itemRepository injected as Proxy (etc/di.xml)
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('acme:reference:items:list')
            ->setDescription('Lists the reference items')
            ->addOption(self::OPTION_STATUS, null, InputOption::VALUE_REQUIRED, 'Only items with this status (0 or 1)');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $input->getOption(self::OPTION_STATUS);
        if ($status !== null) {
            if (!in_array($status, ['0', '1'], true)) {
                $output->writeln('<error>--status must be 0 or 1</error>');
                return Command::FAILURE;
            }
            $this->searchCriteriaBuilder->addFilter(ItemInterface::STATUS, (int)$status);
        }
        $items = $this->itemRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Code', 'Name', 'Status']);
        foreach ($items as $item) {
            $table->addRow([$item->getId(), $item->getCode(), $item->getName(), $item->getStatus()]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}
