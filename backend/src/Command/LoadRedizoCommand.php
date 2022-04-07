<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use App\Service\RedIzoService;
use App\Entity;

#[AsCommand(
    name: 'app:load-redizo',
    description: 'loads redizo data from local json file',
)]
class LoadRedizoCommand extends Command
{
    /** @var EntityManagerInterface */
    protected $em;

    /** {@inheritdoc} */
    public function __construct(RedIzoService $redIzo, ManagerRegistry $managerRegistry)
    {
        parent::__construct();
        $this->redIzoService = $redIzo;
        $this->em = $managerRegistry->getManager('default');
    }


    protected function configure(): void
    {
        $this
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //$io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        $list = $this->redIzoService->getRedIzoList();
        $counter = 0; 
        foreach ($list as $redIzo) {
            $detail = $this->redIzoService->getRedIzoDetail($redIzo);
            $entity = $this->em->getRepository(Entity\Reditelstvi::class)->findOneBy(array('redIzo' => $redIzo));
            if ($entity) {
                $io->write('existing');
            } else {
                $io->write('new');
                $entity = new Entity\Reditelstvi();
                $this->formatReditelstvi($entity, $detail);
                $this->em->persist($entity);
                $this->em->flush();
                $io->success('created: '.$detail['name']);
                $counter++;
                if ($counter > 50) break;
            }
        }
        return Command::SUCCESS;
    }

    protected function loadSupportedTypes(): array
    {
        $result = array();
        $list = $this->em->getRepository(Entity\TypZarizeni::class)->findBy(array('aktivni' => true));
        foreach ($list as $value) {
            if ($value->getIdMsmt()) {
                $result[$value->getIdMsmt()] = $value;
            }
        }

        return $result;
    }

    protected function formatReditelstvi(Entity\Reditelstvi $entity, array $detail):void
    {
        $okresRepository = $this->em->getRepository(Entity\Okres::class);
        $types = $this->loadSupportedTypes();

        $entity->setRedIzo($detail['redIzo'])
            ->setRedPlnyNazev($detail['name'])
            ->setRedRuianKod($detail['address']['ruainCode'])
            ->setIdOrp(str_replace('CZ0', '', $detail['orp']))
            ->setIdOkres($okresRepository->findOneBy(array('idNuts2' => $detail['okres'])))
            ->setRedAdresa1($detail['address']['line1'])
            ->setRedAdresa2($detail['address']['line2'])
            ->setRedAdresa3($detail['address']['line3'])
                ;
        foreach ($detail['schools'] as $subDetail) {
            if (!array_key_exists($subDetail['type'], $types)) { // only whitelisted types
                echo "skiping ".$subDetail['type'].$subDetail['name']."\n";
                continue;
            }
            $zarizeni = new Entity\Zarizeni();
            $this->formatZarizeni($zarizeni, $subDetail, $types);
            $entity->addZarizeni($zarizeni);
            $this->em->persist($zarizeni);
        }
    }

    protected function formatZarizeni(Entity\Zarizeni $izo, array $data, array &$types): void
    {
        $jazykRepository = $this->em->getRepository(Entity\JazykVyuky::class);
            $izo->setIzo($data['izo'])
                ->setSkolaPlnyNazev($data['name'])
                ->setSkolaKapacita($data['capacity'])
                ->setAktivni(true)
                ->setIdJazyk($jazykRepository->findOneBy(array('jmeno' => $data['language'])))
                ->setIdSkolaTyp($types[$data['type']])
                ->setMistoAdresa1($data['address']['line1'])
                ->setMistoRuianKod($data['address']['ruainCode'])
            ;
            if (empty($data['address']['line3'])) {
                // two line address
                $izo->setMistoAdresa3($data['address']['line2']);
            } else {
                $izo
                    ->setMistoAdresa2($data['address']['line2'])
                    ->setMistoAdresa3($data['address']['line3'])
                ;
            }
    }

}