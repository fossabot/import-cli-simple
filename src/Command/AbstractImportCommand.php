<?php

/**
 * TechDivision\Import\Cli\Command\ImportCommandTrait
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-cli-simple
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Cli\Command;

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use TechDivision\Import\Utils\LoggerKeys;
use TechDivision\Import\Cli\Simple;
use TechDivision\Import\Cli\ConfigurationFactory;
use TechDivision\Import\Cli\Configuration;
use TechDivision\Import\Cli\Configuration\Database;
use TechDivision\Import\Cli\Configuration\LoggerFactory;
use TechDivision\Import\Cli\Utils\SynteticServiceKeys;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;

/**
 * The abstract import command implementation.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-cli-simple
 * @link      http://www.techdivision.com
 */
abstract class AbstractImportCommand extends Command
{

    /**
     * The default libraries.
     *
     * @var array
     */
    protected $defaultLibraries = array(
        'ce' => array(
            'techdivision/import',
            'techdivision/import-category',
            'techdivision/import-product',
            'techdivision/import-product-bundle',
            'techdivision/import-product-link',
            'techdivision/import-product-media',
            'techdivision/import-product-variant'
         ),
        'ee' => array(
            'techdivision/import',
            'techdivision/import-ee',
            'techdivision/import-category',
            'techdivision/import-category-ee',
            'techdivision/import-product',
            'techdivision/import-product-ee',
            'techdivision/import-product-bundle',
            'techdivision/import-product-bundle-ee',
            'techdivision/import-product-link',
            'techdivision/import-product-link-ee',
            'techdivision/import-product-media',
            'techdivision/import-product-media-ee',
            'techdivision/import-product-variant',
            'techdivision/import-product-variant-ee'
        )
    );

    /**
     * Configures the current command.
     *
     * @return void
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {

        // initialize the command with the required/optional options
        $this->addArgument(
            InputArgumentKeys::OPERATION_NAME,
            InputArgument::OPTIONAL,
            'The operation that has to be used for the import, one of "add-update", "replace" or "delete"'
        )
        ->addOption(
            InputOptionKeys::CONFIGURATION,
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the pathname to the configuration file to use',
            sprintf('%s/techdivision-import.json', getcwd())
        )
        ->addOption(
            InputOptionKeys::INSTALLATION_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento installation directory to which the files has to be imported'
        )
        ->addOption(
            InputOptionKeys::SOURCE_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The directory that has to be watched for new files'
        )
        ->addOption(
            InputOptionKeys::TARGET_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The target directory with the files that has been imported'
        )
        ->addOption(
            InputOptionKeys::UTILITY_CLASS_NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The utility class name with the SQL statements'
        )
        ->addOption(
            InputOptionKeys::PREFIX,
            null,
            InputOption::VALUE_REQUIRED,
            'The prefix of the CSV source file(s) that has/have to be imported'
        )
        ->addOption(
            InputOptionKeys::MAGENTO_EDITION,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento edition to be used, either one of CE or EE'
        )
        ->addOption(
            InputOptionKeys::MAGENTO_VERSION,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento version to be used, e. g. 2.1.2'
        )
        ->addOption(
            InputOptionKeys::SOURCE_DATE_FORMAT,
            null,
            InputOption::VALUE_REQUIRED,
            'The date format used in the CSV file(s)'
        )
        ->addOption(
            InputOptionKeys::USE_DB_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'The explicit database ID used for the actual import process'
        )
        ->addOption(
            InputOptionKeys::DB_PDO_DSN,
            null,
            InputOption::VALUE_REQUIRED,
            'The DSN used to connect to the Magento database where the data has to be imported, e. g. mysql:host=127.0.0.1;dbname=magento'
        )
        ->addOption(
            InputOptionKeys::DB_USERNAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The username used to connect to the Magento database'
        )
        ->addOption(
            InputOptionKeys::DB_PASSWORD,
            null,
            InputOption::VALUE_REQUIRED,
            'The password used to connect to the Magento database'
        )
        ->addOption(
            InputOptionKeys::LOG_LEVEL,
            null,
            InputOption::VALUE_REQUIRED,
            'The log level to use'
        )
        ->addOption(
            InputOptionKeys::DEBUG_MODE,
            null,
            InputOption::VALUE_REQUIRED,
            'Whether use the debug mode or not'
        )
        ->addOption(
            InputOptionKeys::PID_FILENAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The explicit PID filename to use',
            sprintf('%s/%s', sys_get_temp_dir(), Configuration::PID_FILENAME)
        );
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws \LogicException When this abstract method is not implemented
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // initialize the flag, whether the JMS annotations has been loaded or not
        $found = false;

        // the possible paths to the vendor directory
        $possibleVendorDirectories = array(
            dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR . 'vendor',
            dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'vendor'
        );

        // the path of the JMS serializer directory, relative to the vendor directory
        $jmsDirectory = DIRECTORY_SEPARATOR . 'jms' . DIRECTORY_SEPARATOR . 'serializer' . DIRECTORY_SEPARATOR . 'src';

        // try to find the path to the JMS Serializer annotations
        foreach ($possibleVendorDirectories as $possibleVendorDirectory) {
            if (file_exists($annotationDirectory = $possibleVendorDirectory . $jmsDirectory)) {
                $found = true;
                break;
            }
        }

        // stop processing, if the JMS annotations can't be found
        if (!$found) {
            throw new \Exception(
                sprintf(
                    'The JMS annotations can not be found in one of %s',
                    implode(', ', $possibleVendorDirectories)
                )
            );
        }

        // register the autoloader for the JMS serializer annotations
        \Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation',
            $annotationDirectory
        );

        // load the importer configuration
        $configuration = ConfigurationFactory::factory($input);

        // initialize the DI container
        $container = new ContainerBuilder();

        // load the DI configuration for the default libraries
        foreach ($this->getDefaultLibraries($configuration->getMagentoEdition()) as $defaultLibrary) {
            $defaultLoader = new XmlFileLoader($container, new FileLocator($possibleVendorDirectory));
            $defaultLoader->load(sprintf('%s/etc/services.xml', $defaultLibrary));
        }

        // register autoloaders for additional vendor directories
        $customLoader = new XmlFileLoader($container, new FileLocator());
        foreach ($configuration->getVendorDirs() as $vendorDir) {
            // load the vendor directory's auto loader
            if (file_exists($autoLoader = $vendorDir->getVendorDir() . '/autoload.php')) {
                require $autoLoader;
            }

            // load the DI configuration for the extension libraries
            foreach ($vendorDir->getLibraries() as $library) {
                $customLoader->load(realpath(sprintf('%s/%s/etc/services.xml', $vendorDir->getVendorDir(), $library)));
            }
        }

        // add the configuration as well as input/outut instances to the DI container
        $container->set(SynteticServiceKeys::INPUT, $input);
        $container->set(SynteticServiceKeys::OUTPUT, $output);
        $container->set(SynteticServiceKeys::CONFIGURATION, $configuration);
        $container->set(SynteticServiceKeys::APPLICATION, $this->getApplication());

        // initialize the PDO connection
        $dsn = $configuration->getDatabase()->getDsn();
        $username = $configuration->getDatabase()->getUsername();
        $password = $configuration->getDatabase()->getPassword();
        $connection = new \PDO($dsn, $username, $password);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // add the PDO connection to the DI container
        $container->set(SynteticServiceKeys::CONNECTION, $connection);

        // initialize the system logger
        $loggers = array();

        // initialize the default system logger
        $systemLogger = new Logger('techdivision/import');
        $systemLogger->pushHandler(
            new ErrorLogHandler(
                ErrorLogHandler::OPERATING_SYSTEM,
                $configuration->getLogLevel()
            )
        );

        // add it to the array
        $loggers[LoggerKeys::SYSTEM] = $systemLogger;

        // append the configured loggers or override the default one
        foreach ($configuration->getLoggers() as $loggerConfiguration) {
            // load the factory class that creates the logger instance
            $loggerFactory = $loggerConfiguration->getFactory();
            // create the logger instance and add it to the available loggers
            $loggers[$loggerConfiguration->getName()] = $loggerFactory::factory($loggerConfiguration);
        }

        // add the system loggers to the DI container
        $container->set(SynteticServiceKeys::LOGGERS, $loggers);

        // start the import process
        $container->get(SynteticServiceKeys::SIMPLE)->process();
    }

    protected function getDefaultLibraries($magentoEdition)
    {
        return array_merge(
            array(dirname(dirname(__DIR__))),
            $this->defaultLibraries[strtolower($magentoEdition)]
        );
    }
}
