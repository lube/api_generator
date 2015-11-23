<?php

// src/AppBundle/Command/GenerateRestCommand.php
namespace Tiarg\GeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sensio\Bundle\GeneratorBundle\Command\AutoComplete\EntitiesAutoCompleter;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;

class GenerateABMCommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity'      , '', InputOption::VALUE_REQUIRED, 'La clase de la entidad a la cual le vamos a generar un controller'),
                new InputOption('con-update'  , '', InputOption::VALUE_NONE, 'Si debemos generar o no la funcion de update'),
                new InputOption('prefijo-ruta', '', InputOption::VALUE_REQUIRED, 'El prefijo de las rutas a generar')
            ))
            ->setHelp(<<<EOT
Este comando <info>tiarg:controller:generate</info> command genera una ABM basado en una entidad de Doctrine.

Este comando por default solo genera las rutas para listar todos e individualmente las entidades.

<info>php app/console tiarg:controller:generate --entity=AcmeBlogBundle:Post --prefjo-ruta=post</info>

Usando la opcion --con-update permite generar la action y la ruta de update.

<info>php app/console tiarg:controller:generate --entity=AcmeBlogBundle:Post --prefjo-ruta=post --con-update</info>

Cada uno de los archivos generados se genera desde un template, mira el codigo si deseas extender esta funcionalidad.
EOT
            )         
            ->setName('tiarg:controller:generate')
            ->setDescription('Generar routing y controllers para una interfaz REST');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Bienvenido al generador de controller Tiarg');

        // namespace
        $output->writeln(array(
            '',
            'Este comando te ayuda a generar un ABM para tus entidades.',
            '',
            'Primero necesito que me digas la entidad a la cual queres generarle el ABM.',
            'Podes darme una entidad que todavia no existe, y te voy a ayudar a generarla',
            '',
            'Tenes que usar la notacion de Symfony de la siguiente manera <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        if ($input->hasArgument('entity') && $input->getArgument('entity') != '') 
        {
            $input->setOption('entity', $input->getArgument('entity'));
        }

        $question = new Question($questionHelper->getQuestion('El nombre del atajo a la Entidad', $input->getOption('entity')), $input->getOption('entity'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));

        $autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
        $autocompleteEntities = $autocompleter->getSuggestions();
        $question->setAutocompleterValues($autocompleteEntities);
        $entity = $questionHelper->ask($input, $output, $question);

        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        // write?
        $withWrite = $input->getOption('con-update') ?: false;
        $output->writeln(array(
            '',
            'Por default, el generador crea dos entidades listar y mostrar uno.',
            'Tambien podes pedirle que genere una funcion de update.',
            '',
        ));
        $question = new ConfirmationQuestion($questionHelper->getQuestion('Queres generar la action de update', $withWrite ? 'yes' : 'no', '?', $withWrite), $withWrite);

        $withWrite = $questionHelper->ask($input, $output, $question);
        $input->setOption('con-update', $withWrite);

        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determina el prefijo con el que se generan las nuevas rutas (todas las rutas generadas van a estar montadas en este prefijo',
            'prefijo: /prefijos/20/a/40, /prefijo/2, ...).',
            '',
        ));
        $prefix = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Prefijo de ruta', '/'.$prefix), '/'.$prefix));
        $input->setOption('prefijo-ruta', $prefix);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Resumen antes de la generacion', 'bg=blue;fg=white', true),
            '',
            sprintf("Vas a usar el generador de controllers TIARG para generar tu REST \"<info>%s:%s</info>\"", $bundle, $entity),
            '',
        ));
    }

    protected function updateRouting(QuestionHelper $questionHelper, InputInterface $input, OutputInterface $output, BundleInterface $bundle, $format, $entity, $prefix)
    {
        $auto = true;
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Confirma la generacion automatica de rutas', 'yes', '?'), true);
            $auto = $questionHelper->ask($input, $output, $question);
        }

        $output->write('Importando las rutas del ABM: ');
        $this->getContainer()->get('filesystem')->mkdir($bundle->getPath().'/Resources/config/routing/');
 
        $routing = new RoutingManipulator($bundle->getPath().'/Resources/config/routing.yml');
        try {
            $ret = $auto ? $routing->addResource($bundle->getName(), $format, '/'.$prefix, 'routing/'.strtolower(str_replace('\\', '_', $entity))) : false;
        } catch (\RuntimeException $exc) {
            $ret = false;
        }


        if (!$ret) {
            $help = sprintf("        <comment>recurso: \"@%s/Resources/config/routing/%s.%s\"</comment>\n", $bundle->getName(), strtolower(str_replace('\\', '_', $entity)), $format);
            $help .= sprintf("        <comment>prefijo:   /%s</comment>\n", $prefix);

            return array(
                '- No hace falta importar, routing previamente cargado',
                sprintf('  (%s).', $bundle->getPath().'/Resources/config/routing.yml'),
                '',
                sprintf('    <comment>%s:</comment>', $bundle->getName().('' !== $prefix ? '_'.str_replace('/', '_', $prefix) : '')),
                $help,
                '',
            );
        }
    }

    protected function getRoutePrefix(InputInterface $input, $entity)
    {
        $prefix = $input->getOption('prefijo-ruta') ?: strtolower(str_replace(array('\\', '/'), '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container = $this->getApplication()->getKernel()->getContainer();
        $em = $this->container->get('doctrine')->getManager();

        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) 
        {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Confirmas la generacion', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) 
            {
                $output->writeln('<error>Comando abortado</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('con-update');
                
        $questionHelper->writeSection($output, 'Generacion ABM');

        $BundlePath       = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle);
        $EntityPathBarra  = $BundlePath.'\\'.$entity;

        $EntityName     = $entity;
        $EntityMetadata = $this->getEntityMetadata($bundle . ':' . $entity); 
        $EntityPath     = $bundle . ':' . $entity ;

        $output->writeln('Generando el Controller: <info>OK</info>');


        $bundle    = $this->getContainer()->get('kernel')->getBundle($bundle);
        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $entity, $EntityMetadata[0], 'yml', $prefix, $withWrite, true);

        $EntityMetadata = $EntityMetadata[0];
        $EntityFields   = $EntityMetadata->getFieldNames();

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        $BundleBasePath = implode('/',  array_slice(explode('\\', $BundlePath),0,count(explode('\\', $BundlePath)) - 1));

        $RenderedController = $this->container->get('templating')->render('@TiargGeneratorBundle/Resources/templates/controller.php.twig', 
                                                                            array('BundleBasePath' => $BundleBasePath,
                                                                                  'EntityPathPuntos' => $EntityPath, 
                                                                                  'EntityPathBarra' => $EntityPathBarra, 
                                                                                  'EntityName' => $EntityName, 
                                                                                  'EntityFields' => $EntityFields, 
                                                                                  'EntityMetadata' => $EntityMetadata,
                                                                                  'RenderUpdate'   => $withWrite)
                                                                        );
        
        // -------------------------------------- Acme\BlogBundle\Entity -> Acme/Blog/Bundle/Controller
        $ControllerPath = 'src/' . $BundleBasePath  . '/Controller/';
        
        $output->writeln('Generando el Controller en: ' . $ControllerPath);

        file_put_contents($ControllerPath . $EntityName . 'Controller.php', $RenderedController);

        $this->routePrefix = $prefix;
        $this->routeNamePrefix = str_replace('/', '_', $prefix);
        $this->actions = $withWrite ? array('get', 'all', 'update') : array('get', 'all');

        $this->generateConfiguration($BundleBasePath, $EntityName);

        $runner($this->updateRouting($questionHelper, $input, $output, $bundle, 'yml', $entity, $prefix));

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function generateConfiguration($BundleBasePath, $entity)
    {

        $target = sprintf(
            'src/' . $BundleBasePath . '/Resources/config/routing/%s.yml',
            strtolower(str_replace('\\', '_', $entity))
        );

        $RenderedRouting = $this->container->get('templating')->render('@TiargGeneratorBundle/Resources/templates/routing.yml.twig', array(
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'bundle'            => $BundleBasePath,
            'entity'            => $entity,
        ));

        file_put_contents($target, $RenderedRouting);
    }

    protected function createGenerator($bundle = null)
    {
        return new DoctrineCrudGenerator($this->getContainer()->get('filesystem'));
    }


}
