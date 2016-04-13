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
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Knp\JsonSchemaBundle\Schema\SchemaGenerator;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

class GenerateJsonSchemaCommand extends ContainerAwareCommand
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
        $io = new SymfonyStyle($input, $output);
        $io->title('Generador de JSON Schemas Tiarg');

        $questionHelper = $this->getHelper('question');

        if ($input->hasArgument('entity') && $input->getArgument('entity') != '') 
        {
            $input->setOption('entity', $input->getArgument('entity'));
        }
        else
        {
            $question = new Question('El nombre del atajo a la Entidad <info>[AppBundle:Blog]</info> ', 'AppBundle:Blog');
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));

            $autocompleter = new EntitiesAutoCompleter($this->getContainer()->get('doctrine')->getManager());
            $autocompleteEntities = $autocompleter->getSuggestions();
            $question->setAutocompleterValues($autocompleteEntities);
            $entity = $questionHelper->ask($input, $output, $question);
        }

        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->container = $this->getApplication()->getKernel()->getContainer();

        list($BundleName, $EntityName) = $this->parseShortcutNotation($input->getOption('entity'));

        foreach ($this->container->get('doctrine')->getManager()->getMetadataFactory()->getAllMetadata() as $m) 
        {
            $schema = $this->container->get('json_schema.registry')->register($m->getName(), $m->getName());
        }

        $BundlePath = $this->container->get('doctrine')->getAliasNamespace($BundleName);
        $schema = $this->container->get('json_schema.generator')->generate($BundlePath . '\\' . $EntityName, SchemaGenerator::LOOSE);

        $BundlePath = implode('/',  array_slice(explode('\\', $BundlePath),0,count(explode('\\', $BundlePath)) - 1));

        if (!$this->container->get('filesystem')->exists($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema'))
        {
            $this->container->get('filesystem')->mkdir($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema');
            $this->container->get('filesystem')->mkdir($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Save');
            $this->container->get('filesystem')->mkdir($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Filter');
            $this->container->get('filesystem')->mkdir($this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Update');
        }

        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Save/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );
        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Filter/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );
        file_put_contents ( $this->container->get('kernel')->getRootDir() . '/../src/' . $BundlePath . '/Schema/Update/' . $EntityName . 'Schema.json',
                            json_encode($schema->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) );

        $io->success("Json Schema correctamente generado!");
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
