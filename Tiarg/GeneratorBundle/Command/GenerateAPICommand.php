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

class GenerateAPICommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity'      , '', InputOption::VALUE_REQUIRED, 'La clase de la entidad a la cual le vamos a generar un controller'),
                new InputOption('destino'      , '', InputOption::VALUE_REQUIRED, 'El bundle donde pensamos generar nuestro controller'),
                new InputOption('con-update'  , '', InputOption::VALUE_NONE, 'Si debemos generar o no la funcion de update'),
                new InputOption('con-rol'  , '', InputOption::VALUE_NONE, 'Si debemos definir un rol con acceso a la api'),
                new InputOption('rol'  , '', InputOption::VALUE_NONE, 'Rol con acceso a la api')
            ))
            ->setHelp(<<<EOT
Este comando <info>api:generate</info> command genera una ABM basado en una entidad de Doctrine.

Este comando por default solo genera las rutas para listar todos e individualmente las entidades.

Usando la opcion --con-update permite generar las operaciones de update/remove.

<info>php app/console api:generate --entity=AcmeBlogBundle:Post --con-update</info>

Cada uno de los archivos generados se genera desde un template, mira el codigo si deseas extender esta funcionalidad.
EOT
            )         
            ->setName('api:generate')
            ->setDescription('Generar routing y controllers para una interfaz REST');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Bienvenido al generador de controller Tiarg');

        // namespace
        $output->writeln(array(
            '',
            'Este comando te ayuda a generar una api para tus entidades.',
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

        $question = new Question($questionHelper->getQuestion('En que bundle queres generar esta API?', $input->getOption('destino')), 
                                 $input->getOption('destino'));

        $bundleValidator = function ($bundleName)
                                    {
                                        return Validators::validateBundleNamespace($bundleName, false);
                                    };

        $question->setValidator($bundleValidator);

        $question->setAutocompleterValues($this->getContainer()->getParameter('kernel.bundles'));
        $entity = $questionHelper->ask($input, $output, $question);

        $input->setOption('destino', $entity);

        // write?
        $withWrite = $input->getOption('con-update') ? true : false;
        $output->writeln(array(
            '',
            'Por default, el generador crea dos entidades listar y mostrar uno.',
            'Tambien podes pedirle que genere funciones de update.',
            '',
        ));
        $question = new ConfirmationQuestion($questionHelper->getQuestion('Queres generar la accion de update y delete?', $withWrite ? 'yes' : 'no'), $withWrite ? true : false);

        $withWrite = $questionHelper->ask($input, $output, $question);
        $input->setOption('con-update', $withWrite);

        $question = new ConfirmationQuestion($questionHelper->getQuestion('Queres especificar un Rol para esta API?',  'yes'), true);
        
        $withRol = $questionHelper->ask($input, $output, $question);
        $input->setOption('con-rol', $withRol);

        if ($withRol)
        {
            $question = new Question($questionHelper->getQuestion('El nombre del Rol para esta API', 
                                                                   $input->getOption('rol')), 
                                     $input->getOption('rol'));
            $rol = $questionHelper->ask($input, $output, $question);
            $input->setOption('rol', $rol);
        }


        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Resumen antes de la generacion', 'bg=blue;fg=white', true),
            '',
            sprintf("Vas a usar el generador de controllers TIARG para generar tu REST \"<info>%s:%s</info>\"", $bundle, $entity),
            '',
        ));
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
        list($BundleName, $EntityName) = $this->parseShortcutNotation($entity);
        
        $output->writeln('Generando el Controller: <info>OK</info>');

        $withWrite = $input->getOption('con-update');
        $withRol   = $input->getOption('con-rol');
        $rol       = $input->getOption('rol');
        $destino   = $input->getOption('destino');
                
        $questionHelper->writeSection($output, 'Generacion API');
        $EntityMetadata = $this->getEntityMetadata($BundleName . ':' . $EntityName)[0]; 

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        $BundlePath     = $this->getContainer()->get('doctrine')->getAliasNamespace($BundleName);
        $BundleBasePath = implode('/',  array_slice(explode('\\', $BundlePath),0,count(explode('\\', $BundlePath)) - 1));
        $ControllerPath = $this->container->get('kernel')->locateResource('@' . $destino);

        $Namespace = str_replace('/', '\\', $BundleBasePath);
        $Bundle['Name']     =  $BundleName;
        $Entity['Con-Rol']  =  $withRol;
        $Entity['Rol']      =  $rol;
        $Entity['Name']     =  $EntityName;
        $Entity['Fields']   =  $EntityMetadata->getFieldNames();
        $Entity['Metadata'] =  $EntityMetadata;
        $Entity['Actions']  =  $withWrite ? array('cget', 'get', 'save', 'remove', 'update') : array('cget', 'get');

        $this->renderFile('controller.php.twig', 
                           $ControllerPath . '/Controller/' . $EntityName . 'Controller.php',
                           array('Namespace' => $Namespace,
                                 'Bundle'    => $Bundle, 
                                 'Entity'    => $Entity)
                         );
        
        $output->writeln('Generando el Controller en: ' . $ControllerPath);

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function render($template, $parameters)
    {
        $twig = $this->getTwigEnvironment();

        return $twig->render($template, $parameters);
    }

    protected function createGenerator()
    {
        return null;
    }

    /**
     * Get the twig environment that will render skeletons.
     *
     * @return \Twig_Environment
     */
    protected function getTwigEnvironment()
    {
        return new \Twig_Environment(new \Twig_Loader_Filesystem($this->container->get('kernel')->locateResource('@TiargGeneratorBundle/Resources/') . 'templates'), array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));
    }

    protected function renderFile($template, $target, $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        return file_put_contents($target, $this->render($template, $parameters));
    }
}
