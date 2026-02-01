
<?php

namespace raum51\ContaoZoteroBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use raum51\ContaoZoteroBundle\Service\ZoteroSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'raum51:zotero:sync', description: 'Synchronize all enabled Zotero libraries into Contao tables')]
class ZoteroSyncCommand extends Command
{
    public function __construct(
        private ContaoFramework $framework,
        private ZoteroSynchronizer $synchronizer
    ){
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        $output->writeln('<info>Starting Zotero sync...</info>');
        $this->synchronizer->syncAll();
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }
}
