<?php

// src/AppBundle/Command/GenerateRestCommand.php
namespace Lube\GeneratorBundle\Command;

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

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

class GenerateAPICommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity'     , '', InputOption::VALUE_REQUIRED, 'Target class for API Generation'),
                new InputOption('bundle'    , '', InputOption::VALUE_REQUIRED, 'Target bundle For API Controller'),
                new InputOption('with-update' , '', InputOption::VALUE_NONE, 'Should we add POST/PUT Operations'),
                new InputOption('role'        , '', InputOption::VALUE_NONE, 'API ROLE ACCESS')
            ))
            ->setHelp(<<<EOT
This command <info>api:generate</info> creates basic CRUD operations and RESTfull endpoints for your model.

Using --with-update allows the creation for update/remove operations.

<info>php app/console api:generate --entity=AcmeBlogBundle:Post --bundle=AcmeBlogBundle --with-update --role="ROLE_ADMIN"</info>
EOT
            )         
            ->setName('api:generate')
            ->setDescription('Generation of RESTfull endpoint and basic CRUD API');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('API Generator');

        $helper = $this->getHelper('question');

        // Descripcion
        $io->section('Specification');
        $io->text(array(
                        'This command creates basic CRUD operations and RESTfull endpoints for your model.',
                        '',
                        'First we are going to need the target entity.',
                        '',
                        'If you specify an inexistent API I will help you create it.',
                        '',
                        'For example <comment>AcmeBlogBundle:Post</comment>.',
                        '',
                ));

        //Entidad
        if ($input->hasArgument('entity') && $input->getArgument('entity') != '') 
        {
            $input->setOption('entity', $input->getArgument('entity'));
        }
        else
        {
            $question = new Question('The shortcut name for the Entity: <info>[AcmeBlogBundle:Blog]</info> ', 'AcmeBlogBundle:Blog');
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));

            $autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
            $question->setAutocompleterValues($autocompleter->getSuggestions());

            $input->setOption('entity',  $helper->ask($input, $output, $question));
        }

        //Target Bundle
        if ($input->hasArgument('bundle') && $input->getArgument('bundle') != '') 
        {
            $input->setOption('bundle', $input->getArgument('bundle'));
        }
        else
        {
            $question = new Question('Target bundle For API Controller:  <info>[AcmeBlogBundle]</info> ', 'AcmeBlogBundle');
            
            $bundles = $this->getContainer()->getParameter('kernel.bundles');
            
            $bundleValidator = function ($bundleName) use ($bundles)
                                        {
                                            Validators::validateBundleNamespace($bundleName, false);
                                            
                                            if (!array_key_exists($bundleName, $this->getContainer()->getParameter('kernel.bundles')))
                                            {
                                                throw new \RuntimeException('Cannot find Bundle '. $bundleName );
                                            }
                                            return $bundleName;
                                        };        

            $question->setValidator($bundleValidator);
            $question->setAutocompleterValues($bundles);

            $input->setOption('bundle', $helper->ask($input, $output, $question));
        }

        $summary[] = sprintf("You are going to use the generator to create an API endpoint at \"<info>%s:%s</info>\"", 
                    $input->getOption('bundle'), 
                    $input->getOption('entity'));
        $summary[] = '';

        $io->text(array(
                '',
                '<info>By default the generator only creates two endpoints, GET /blog y GET /blog/{id}.</info>',
                '',
                '<info>You can also specify the creation of PUT/POST operations</info>.',
                ''
            ));

        //Actions
        if ($input->hasArgument('with-update') && $input->getArgument('with-update') != '') 
        {
            $input->setOption('with-update', $input->getArgument('with-update'));
        }
        else
        {
            $question = new ConfirmationQuestion('Do you wish to generate POST/PUT/DELETE operations? <info>[yes]</info> ', true);

            $input->setOption('with-update', $helper->ask($input, $output, $question));
        }

        $summary[] = sprintf("Actions: %s", ($input->getOption('with-update') ? 'cGet, Get, Save, Remove, Update' : 'cGet, Get'));
        $summary[] = '';

        //Rol
        if ($input->hasArgument('role') && $input->getArgument('role') != '') 
        {
            $input->setOption('role', $input->getArgument('role'));
            $summary[] = sprintf ("ROL: \"<info>%s</info>\"", $input->getOption('role'));
            $summary[] = '';
        }
        else
        {
            $question = new ConfirmationQuestion('Do you want to specify a ROLE that can access this API? <info>[yes]</info> ', true);
            
            if ($helper->ask($input, $output, $question))
            {
                $question = new Question('ROLE\'s name <info>ROLE_ADMIN</info> ', 'ROLE_ADMIN');
                $input->setOption('role', $helper->ask($input, $output, $question));

                $summary[] = sprintf ("ROL: \"<info>%s</info>\"", $input->getOption('role'));
                $summary[] = '';
            }
        }

        //Resumen
        $io->section(
            "Summary"
        );
        $io->text(
            $summary
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->container = $this->getApplication()->getKernel()->getContainer();
        $helper = $this->getHelper('question');

        if ($input->isInteractive()) 
        {
            $question = new ConfirmationQuestion('Confirm API Generation? <info>[yes]</info> ', true);
            if (!$helper->ask($input, $output, $question)) 
            {
                return 1;
            }
        }

        list($BundleName, $EntityName) = $this->parseShortcutNotation($input->getOption('entity'));
        
        $io->section('Generating the controller: <info>OK</info>');

        $EntityMetadata = $this->getEntityMetadata($BundleName . ':' . $EntityName)[0]; 

        $errors = array();
        $runner = $this->getRunner($output, $errors);

        $Namespace = $this->container->get('doctrine')->getAliasNamespace($BundleName);

        $Namespace = implode('/',  
                                array_slice(
                                        explode('\\', $Namespace)
                                        ,0
                                        ,-1
                                )
                            );

        $BundlePath = $this->container->get('kernel')->locateResource('@' . $input->getOption('bundle'));

        $Namespace          =  str_replace('/', '\\', $Namespace);
        $Bundle['Name']     =  $BundleName;
        $Bundle['Path']     =  $BundlePath;
        $Entity['Rol']      =  $input->getOption('role');
        $Entity['Name']     =  $EntityName;
        $Entity['Metadata'] =  $EntityMetadata;
        $Entity['Actions']  =  $input->getOption('with-update') ? array('cget', 'get', 'save', 'remove', 'update') : array('cget', 'get');

        $this->renderFile('controller.php.twig', 
                           $BundlePath . '/Controller/' . $EntityName . 'Controller.php',
                           array('Namespace' => $input->getOption('bundle'),
                                 'Bundle'    => $Bundle, 
                                 'Entity'    => $Entity)
                         );
        
        $io->success('Generando el Controller en: ' . $BundlePath);

        $this->writeGeneratorSummary($output, $errors);

        return 0;
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
        return new \Twig_Environment(new \Twig_Loader_Filesystem($this->container->get('kernel')->locateResource('@LubeGeneratorBundle/Resources/') . 'templates'), array(
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
    
    protected function getEntityMetadata($entity)
    {
        $factory = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
        return $factory->getClassMetadata($entity)->getMetadata();
    }

    public function writeGeneratorSummary(OutputInterface $output, $errors)
    {
        if (!$errors) {
            $this->writeSection($output, 'Everything is OK! Now get to work :).');
        } else {
            $this->writeSection($output, array(
                'The command was not able to configure everything automatically.',
                'You\'ll need to make the following changes manually.',
            ), 'error');
            $output->writeln($errors);
        }
    }
    public function getRunner(OutputInterface $output, &$errors)
    {
        $runner = function ($err) use ($output, &$errors) {
            if ($err) {
                $output->writeln('<fg=red>FAILED</>');
                $errors = array_merge($errors, $err);
            } else {
                $output->writeln('<info>OK</info>');
            }
        };
        return $runner;
    }

    public function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }
}
