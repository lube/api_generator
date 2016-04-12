<?php

// src/AppBundle/Command/GenerateRestCommand.php
namespace Tiarg\GeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sensio\Bundle\GeneratorBundle\Command\AutoComplete\EntitiesAutoCompleter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sensio\Bundle\GeneratorBundle\Command\Validators;

class GenerateAPICommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity'     , '', InputOption::VALUE_REQUIRED, 'La clase de la entidad a la cual le vamos a generar un controller'),
                new InputOption('destino'    , '', InputOption::VALUE_REQUIRED, 'El bundle donde pensamos generar nuestro controller'),
                new InputOption('con-update' , '', InputOption::VALUE_NONE, 'Si debemos generar o no la funcion de update'),
                new InputOption('rol'        , '', InputOption::VALUE_NONE, 'Rol con acceso a la api')
            ))
            ->setHelp(<<<EOT
Este comando <info>api:generate</info> command genera una ABM basado en una entidad de Doctrine.

Este comando por default solo genera las rutas para listar todos e individualmente las entidades.

Usando la opcion --con-update permite generar las operaciones de update/remove.

<info>php app/console api:generate --entity=AcmeBlogBundle:Post --destino=AcmeBlogBundle --con-update --rol="ROLE_ADMIN"</info>

Cada uno de los archivos generados se genera desde un template, mira el codigo si deseas extender esta funcionalidad.
EOT
            )         
            ->setName('api:generate')
            ->setDescription('Generar routing y controllers para una interfaz REST');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generador de API Tiarg');

        $helper = $this->getHelper('question');

        // Descripcion
        $io->section('Especificacion');
        $io->text(sprintf("Este comando te ayuda a generar una api para tus entidades.\n
            Primero necesito que me digas la entidad a la cual queres generarle el ABM.\n
            Podes darme una entidad que todavia no existe, y te voy a ayudar a generarla.\n
            Tenes que usar la notacion de Symfony de la siguiente manera <comment>AcmeBlogBundle:Post</comment>."
        ));

        //Entidad
        if ($input->hasArgument('entity') && $input->getArgument('entity') != '') 
        {
            $input->setOption('entity', $input->getArgument('entity'));
        }
        else
        {
            $question = new Question('El nombre del atajo a la Entidad: <info>[AcmeBlogBundle]</info> ', 'AcmeBlogBundle:Blog');
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));

            $autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
            $question->setAutocompleterValues($autocompleter->getSuggestions());

            $input->setOption('entity',  $helper->ask($input, $output, $question));
        }

        //Destino
        if ($input->hasArgument('destino') && $input->getArgument('destino') != '') 
        {
            $input->setOption('destino', $input->getArgument('destino'));
        }
        else
        {
            $question = new Question('El nombre de bundle donde vamos a generar el controller para esta API:  <info>[AcmeBlogBundle]</info> ', 'AcmeBlogBundle');
            $bundleValidator = function ($bundleName)
                                        {
                                            return Validators::validateBundleNamespace($bundleName, false);
                                        };

            $question->setValidator($bundleValidator);
            $question->setAutocompleterValues($this->getContainer()->getParameter('kernel.bundles'));

            $input->setOption('destino', $helper->ask($input, $output, $question));
        }

        $summary = sprintf("Vas a usar el generador de controllers TIARG para generar tu REST \"<info>%s:%s</info>\"", 
                    $input->getOption('destino'), 
                    $input->getOption('entity'));

        $io->text(sprintf(
            "\n<info>Por default, el generador crea solo dos acciones, GET /blog y GET /blog/{id} para listar entidades.\n
            Tambien podes pedirle que genere funciones de update</info>.\n"
        ));

        //Actions
        if ($input->hasArgument('con-update') && $input->getArgument('con-update') != '') 
        {
            $input->setOption('con-update', $input->getArgument('con-update'));
        }
        else
        {
            $question = new ConfirmationQuestion('Queres generar las acciones de save, update y remove? <info>[yes]</info> ', true);

            $input->setOption('con-update', $helper->ask($input, $output, $question));
        }
        $summary .= sprintf("\n\nAcciones: %s", ($input->getOption('con-update') ? 'cGet, Get, Save, Remove, Update' : 'cGet, Get'));

        //Rol
        if ($input->hasArgument('rol') && $input->getArgument('rol') != '') 
        {
            $input->setOption('rol', $input->getArgument('rol'));
        }
        else
        {
            $question = new ConfirmationQuestion('Queres especificar un Rol para esta API? <info>[yes]</info> ', true);
            
            if ($helper->ask($input, $output, $question))
            {
                $question = new Question('El nombre del Rol para esta API', '<info>ROLE_ADMIN</info>');
                $input->setOption('rol', $helper->ask($input, $output, $question));
            }
        }
        $summary .= sprintf ("\n\nROL: \"<info>%s</info>\"", $input->getOption('rol'));

        //Resumen
        $io->section(
            $this->getHelper('formatter')->formatBlock('Resumen antes de la generacion', 'bg=blue;fg=white', true)
        );
        $io->text(
            $summary
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container = $this->getApplication()->getKernel()->getContainer();
        $helper = $this->getHelper('question');

        if ($input->isInteractive()) 
        {
            $question = new ConfirmationQuestion('Confirmas la generacion?', true);
            if (!$helper->ask($input, $output, $question)) 
            {
                $io->text('<error>Comando abortado</error>');

                return 1;
            }
        }

        list($BundleName, $EntityName) = $this->parseShortcutNotation($input->getOption('entity'));
        
        $io->section('Generando el Controller: <info>OK</info>');

   #     $withWrite = $input->getOption('con-update');
   #     $withRol   = $input->getOption('con-rol');
  #      $rol       = $input->getOption('rol');
  #      $destino   = $input->getOption('destino');
                
        $EntityMetadata = $this->getEntityMetadata($BundleName . ':' . $EntityName)[0]; 

        $errors = array();
        $runner = $helper->getRunner($output, $errors);

        $Namespace = $this->getContainer()->get('doctrine')->getAliasNamespace($BundleName);

        /*$BundleBasePath = implode('/',  
                                array_slice(
                                        explode('\\', $BundlePath)
                                        ,0
                                        ,-1
                                );*/

        $BundlePath = $this->container->get('kernel')->locateResource('@' . $input->getOption('destino'));

        $Namespace          =  str_replace('/', '\\', $Namespace);
        $Bundle['Name']     =  $BundleName;
        $Bundle['Path']     =  $BundlePath;
        $Entity['Con-Rol']  =  $input->getOption('rol') ? true : false;
        $Entity['Rol']      =  $input->getOption('rol');
        $Entity['Name']     =  $EntityName;
        $Entity['Fields']   =  $EntityMetadata->getFieldNames();
        $Entity['Metadata'] =  $EntityMetadata;
        $Entity['Actions']  =  $input->getOption('con-update') ? array('cget', 'get', 'save', 'remove', 'update') : array('cget', 'get');

        $this->renderFile('controller.php.twig', 
                           $BundlePath . '/Controller/' . $EntityName . 'Controller.php',
                           array('Namespace' => $Namespace,
                                 'Bundle'    => $Bundle, 
                                 'Entity'    => $Entity)
                         );
        
        $io->success('Generando el Controller en: ' . $BundlePath);

        $helper->writeGeneratorSummary($output, $errors);
    }

    protected function render($template, $parameters)
    {
        $twig = $this->getTwigEnvironment();

        return $twig->render($template, $parameters);
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

    private function parseShortcutNotation($shortcut)
    {
        $entity = str_replace('/', '\\', $shortcut);
        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The controller name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Post)', $entity));
        }
        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }
}
