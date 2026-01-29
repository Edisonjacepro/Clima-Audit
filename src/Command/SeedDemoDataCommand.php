<?php

namespace App\Command;

use App\Entity\Action;
use App\Entity\AdminConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:seed:demo',
    description: 'Seed default admin config and demo actions.'
)]
class SeedDemoDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->seedAdminConfig($output);
        $this->seedActions($output);

        return Command::SUCCESS;
    }

    private function seedAdminConfig(OutputInterface $output): void
    {
        $repo = $this->entityManager->getRepository(AdminConfig::class);
        $config = $repo->findOneBy([]);
        if ($config === null) {
            $config = new AdminConfig();
            $config->setHazardWeightsJson($this->params->get('app.risk.hazard_weights'));
            $config->setThresholdsJson($this->params->get('app.risk.thresholds'));
            $this->entityManager->persist($config);
            $this->entityManager->flush();
            $output->writeln('AdminConfig created.');
            return;
        }

        $output->writeln('AdminConfig already exists.');
    }

    private function seedActions(OutputInterface $output): void
    {
        $repo = $this->entityManager->getRepository(Action::class);
        $existingCount = (int) $repo->count([]);
        if ($existingCount >= 40) {
            $output->writeln('Actions already seeded.');
            return;
        }

        $actions = $this->getActionSeedData();
        foreach ($actions as $row) {
            $action = new Action();
            $action->setTitle($row['title']);
            $action->setDescription($row['description']);
            $action->setHazardTags($row['hazard_tags']);
            $action->setSectorTags($row['sector_tags']);
            $action->setEffort($row['effort']);
            $action->setCost($row['cost']);
            $action->setImpact($row['impact']);
            $action->setHorizon($row['horizon']);
            $action->setPrerequisites($row['prerequisites']);
            $action->setActive(true);
            $this->entityManager->persist($action);
        }

        $this->entityManager->flush();
        $output->writeln('Actions seeded.');
    }

    private function getActionSeedData(): array
    {
        return [
            [
                'title' => 'Plan canicule et communication interne',
                'description' => 'Mettre en place un protocole chaleur et informer les equipes.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Verifications HVAC et filtration',
                'description' => 'Verifier l\'entretien des systemes de ventilation et la qualite de l\'air.',
                'hazard_tags' => ['heat'],
                'sector_tags' => ['tertiaire', 'industrie', 'collectivite'],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Protections solaires sur vitrages',
                'description' => 'Installer stores, films ou brise-soleil pour limiter les surchauffes.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Optimisation des horaires d\'exploitation',
                'description' => 'Decaler certaines activites aux heures les plus fraiches.',
                'hazard_tags' => ['heat'],
                'sector_tags' => ['industrie', 'agri'],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Vegetalisation des abords',
                'description' => 'Planter des vegetaux pour creer des zones d\'ombre et rafraichir le site.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'high',
                'cost' => 'â‚¬â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Audit des charges thermiques',
                'description' => 'Identifier les zones les plus exposees a la chaleur.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Plan d\'hydratation du personnel',
                'description' => 'Mettre a disposition eau et pauses renforcees en periode chaude.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Isolation des toitures',
                'description' => 'Renforcer l\'isolation pour limiter les gains thermiques.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'high',
                'cost' => 'â‚¬â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Suivi des temperatures interieures',
                'description' => 'Installer des capteurs simples pour mesurer la surchauffe.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'low',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Optimiser l\'inertie des locaux',
                'description' => 'Adapter les usages pour profiter de l\'inertie thermique.',
                'hazard_tags' => ['heat'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Plan de prevention inondation',
                'description' => 'Definir procedures d\'alerte et de mise en securite.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Rehausse des stocks sensibles',
                'description' => 'Mettre les stocks critiques en hauteur ou sur palettes.',
                'hazard_tags' => ['flood'],
                'sector_tags' => ['industrie', 'tertiaire'],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Pose de batardeaux',
                'description' => 'Installer des dispositifs amovibles pour proteger les acces.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Verification des pompes de relevage',
                'description' => 'Tester et maintenir les pompes et systemes d\'evacuation.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => 'Presence d\'un sous-sol equipe',
            ],
            [
                'title' => 'Etancheite des passages de gaines',
                'description' => 'Calfeutrer les points d\'entree d\'eau potentiels.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Stock de materiel d\'urgence',
                'description' => 'Preparer sacs de sable, pompes mobiles, protections.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Etude hydraulique simplifiee',
                'description' => 'Verifier les points bas et les ecoulements proximaux.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Assurance et continuites d\'activite',
                'description' => 'Mettre a jour les garanties et plans de reprise.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Clapets anti-retour',
                'description' => 'Installer des clapets sur les evacuations sensibles.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Surveillance pluviometrique',
                'description' => 'Mettre en place un suivi des alertes meteo locales.',
                'hazard_tags' => ['flood'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'low',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Plan secheresse et suivi structurel',
                'description' => 'Definir un suivi des fissures et mouvements du batiment.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Gestion des eaux pluviales',
                'description' => 'Canaliser l\'eau pour eviter l\'assÃ¨chement des sols.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Arrosage regulier des fondations',
                'description' => 'Maintenir une humidite stable autour du batiment.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => ['collectivite', 'tertiaire'],
                'effort' => 'med',
                'cost' => 'â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Inspection des reseaux enterres',
                'description' => 'Verifier l\'etat des canalisations et regards.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'low',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Diagnostic structurel preventif',
                'description' => 'Faire un diagnostic pour identifier les zones sensibles.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'high',
                'cost' => 'â‚¬â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Gestion des arbres proches',
                'description' => 'Evaluer l\'impact des racines et adapter les plantations.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Formation des equipes maintenance',
                'description' => 'Sensibiliser aux signes de retrait-gonflement.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => 'â‚¬',
                'impact' => 'low',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Suivi hygrometrique des sols',
                'description' => 'Mesurer l\'humidite des sols pour ajuster les actions.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'low',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Renforcement des joints et fissures',
                'description' => 'Reparer les fissures pour limiter les degradations.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => 'â‚¬â‚¬',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Audit geotechnique detaille',
                'description' => 'Analyse approfondie pour sites sensibles.',
                'hazard_tags' => ['drought_clay'],
                'sector_tags' => [],
                'effort' => 'high',
                'cost' => 'â‚¬â‚¬â‚¬',
                'impact' => 'high',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Recensement des cavites connues',
                'description' => 'Collecter les informations historiques et cartographiques locales.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => '€',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Inspection visuelle des sols et fissures',
                'description' => 'Reperer rapidement les signes d\'affaissement.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => '€',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Diagnostic geotechnique preliminaire',
                'description' => 'Evaluer la presence de cavites ou zones instables.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => '€€',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Surveillance des affaissements',
                'description' => 'Mettre en place un suivi regulier des mouvements du sol.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => '€',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Gestion des eaux d\'infiltration',
                'description' => 'Limiter les infiltrations qui fragilisent les vides souterrains.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => '€€',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Limiter les charges lourdes en zone sensible',
                'description' => 'Adapter les circulations et stockages pour reduire les contraintes.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => '€',
                'impact' => 'med',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
            [
                'title' => 'Plan de securisation perimetrique',
                'description' => 'Definir zones de securite et procedures d\'evacuation.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => '€€',
                'impact' => 'low',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Etude structurelle et renforcement',
                'description' => 'Dimensionner des renforcements si instabilite confirmee.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'high',
                'cost' => '€€€',
                'impact' => 'med',
                'horizon' => '12m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Mise en place d\'un suivi topographique',
                'description' => 'Installer des reperes de mesure pour suivre les mouvements.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'med',
                'cost' => '€€',
                'impact' => 'med',
                'horizon' => '3m',
                'prerequisites' => null,
            ],
            [
                'title' => 'Coordination avec les services locaux',
                'description' => 'Echanger avec la commune et les services risques.',
                'hazard_tags' => ['cavites'],
                'sector_tags' => [],
                'effort' => 'low',
                'cost' => '€',
                'impact' => 'low',
                'horizon' => 'now',
                'prerequisites' => null,
            ],
        ];
    }
}



