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

class GenerateJsonSchemaCommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'La clase de la entidad a la cual le vamos a generar un json schema.')
            ))
            ->setHelp(<<<EOT
Este comando <info>api:generate:json</info> command genera un json schema para validar las request a una api de esta entidad.
EOT
            )         
            ->setName('api:generate:json')
            ->setDescription('Generar json schema para una interfaz REST de nuestra API');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container = $this->getApplication()->getKernel()->getContainer();

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($BundleName, $EntityName) = $this->parseShortcutNotation($entity);

        $BundlePath = $this->getContainer()->get('doctrine')->getAliasNamespace($BundleName);
        $schema = $this->container->get('json_schema.registry')->register($EntityName, $BundlePath . '\\'. $EntityName);
        $schema = $this->container->get('json_schema.generator')->generate($EntityName);

        $BundlePath = implode('/',  array_slice(explode('\\', $BundlePath),0,count(explode('\\', $BundlePath)) - 1));

        if (!$this->container->get('filesystem')->exists($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema'))
        {
            $this->container->get('filesystem')->mkdir($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema');
        }

        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Save/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );
        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Filter/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );
        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Update/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );

    }

    protected function createGenerator()
    {
        return null;
    }

}
