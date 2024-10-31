<?php

namespace App\Command;

use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:generate-entities',
    description: 'Add a short description for your command',
)]
class GenerateEntitiesCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('prefix', InputArgument::OPTIONAL, 'Argument description', '')
            ->addOption('ignore-tables', 't', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of tables to ignore', [])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prefix = $input->getArgument('prefix');
        $ignoreTables = $input->getOption('ignore-tables');

        $platform      = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->createSchemaManager();
        $tables        = $schemaManager->listTableNames();

        foreach ($tables as $table){
            if(in_array($table, $ignoreTables, true)){
                $io->writeln("Skipping entity generation for table: $table");
                continue;
            }

            $columns = $schemaManager->listTableColumns($table);
    
            $io->writeln("Generating entity for table: $table");
            $this->generateEntity($platform, $table, $columns, $prefix);
        }

        $io->success('Entities generated successfully!');

        return Command::SUCCESS;
    }

    private function generateEntity(AbstractPlatform $platform, string $tableName, $columns ,string $prefix): void 
    {
        $className = $prefix . ucfirst($tableName);

        $entityContent  = "<?php\n\nnamespace App\Entity;\n\n";
        $entityContent .= "use App\Repository\ProductRepository;\n";
        $entityContent .= "use Doctrine\ORM\Mapping as ORM;\n\n";
        $entityContent .= "class $className\n{\n";

        $fields = [];
        foreach ($columns as $column){
            $fieldName          = $column->getName();
            $fieldType          = $this->mapColumnType($column, $platform);
            $fieldAnnotation    = $this->getFieldAnnotation($column, $fieldType);
            $fieldAutoIncrement = $column->getAutoincrement();

            array_push($fields, [
                'fieldName' => $fieldName, 
                'fieldType' => $fieldType, 
                'fieldAnnotation' => $fieldAnnotation,
                'fieldAutoIncrement' => $fieldAutoIncrement
            ]);
        }

        $this->mapAttributes($fields, $entityContent);
        $this->mapMethods($fields, $entityContent);

        $entityContent .= "}\n";

        file_put_contents("src/Entity/$className.php", $entityContent);
    }

    private function mapAttributes(array $fields, string &$entityContent): void
    {
        foreach ($fields as $field){
            $fieldName       = $field['fieldName'];
            $fieldType       = $field['fieldType'];
            $fieldAnnotation = $field['fieldAnnotation'];

            if($field['fieldAutoIncrement'] && strtolower($fieldName) === 'id'){
                $entityContent .= "\t#[ORM\Id]\n";
                $entityContent .= "\t#[ORM\GeneratedValue(strategy:\"AUTO\")]\n";
            }

            $entityContent .= "\t#$fieldAnnotation\n";
        
            if($fieldType === 'mixed'){
                $entityContent .= "\tprivate $fieldType \$$fieldName = null;\n\n";
            }
            else{
                $entityContent .= "\tprivate ?$fieldType \$$fieldName = null;\n\n";
            }
        }
    }

    private function mapMethods(array $fields, string &$entityContent): void
    {
        foreach ($fields as $field){
            $fieldName       = $field['fieldName'];
            $fieldType       = $field['fieldType'];

            $entityContent .= "\tpublic function get" . ucfirst($fieldName) . "(): $fieldType\n\t{\n";
            $entityContent .= "\t\treturn \$this->$fieldName;\n\t}\n\n";
            $entityContent .= "\tpublic function set" . ucfirst($fieldName) . "($fieldType \$$fieldName): void\n\t{\n";
            $entityContent .= "\t\t\$this->$fieldName = \$$fieldName;\n\t}\n\n";
        }
    }

    private function mapColumnType($column, AbstractPlatform $platform): string
    {
        $options = ['name' => $column->getName(), 'type' => $column->getType(), 'length' => $column->getLength()];

        if($column->getType() === Types::STRING && !$column->getLength()){
            $options['length'] = 255;
        }

        $columnType = $column->getType()->getSQLDeclaration($options, $platform);

        $platformName = (new \ReflectionClass($platform))->getShortName();

        switch ($platformName) {
            case 'MySQLPlatform':
            case 'PostgreSQLPlatform':
                return $this->mapMySqlAndPostgreSql($columnType);
            case 'SQLServerPlatform': 
                return $this->mapSqlServer($columnType);
            default:
                return 'mixed';
        }
    }

    private function mapMySqlAndPostgreSql(string $columnType): string
    {
        $baseType = preg_replace('/\(\d+\)/', '', $columnType);

        switch (strtolower(trim($baseType))) {
            case 'tinyint':
                return 'int'; 
            case 'smallint':
                return 'int';
            case 'mediumint':
                return 'int';
            case 'int':
            case 'integer':
                return 'int';
            case 'bigint':
                return 'int';

            case 'float':
                return 'float';
            case 'double':
                return 'float';
            case 'decimal':
            case 'numeric':
                return 'float';

            case 'date':
                return '\DateTimeInterface';
            case 'time':
                return '\DateTimeInterface';
            case 'datetime':
            case 'timestamp':
                return '\DateTimeInterface';
            case 'year':
                return 'int'; 

            case 'char':
                return 'string';
            case 'varchar':
                return 'string';
            case 'text':
                return 'string';
            case 'tinytext':
                return 'string';
            case 'mediumtext':
                return 'string';
            case 'longtext':
                return 'string';

            case 'binary':
                return 'string';
            case 'varbinary':
                return 'string';
            case 'blob':
                return 'string'; 
            case 'tinyblob':
                return 'string';
            case 'mediumblob':
                return 'string';
            case 'longblob':
                return 'string';

            case 'geometry':
            case 'point':
            case 'linestring':
            case 'polygon':
                return 'mixed'; 

            case 'json':
                return 'string';
            default:
                return 'mixed'; 
        }
    }


    private function mapSqlServer(string $columnType): string
    {
        $baseType = preg_replace('/\(\d+\)/', '', $columnType);

        switch (strtolower(trim($baseType))) {
            case 'varchar':
            case 'nvarchar':
            case 'text':
                return 'string';
            case 'int':
            case 'integer':
                return 'int';
            case 'bigint':
                return 'int';
            case 'smallint':
                return 'int';
            case 'tinyint':
                return 'bool';
            case 'bit':
                return 'bool';
            case 'datetime':
            case 'datetime2':
            case 'date':
                return '\DateTimeInterface';
            case 'float':
            case 'decimal':
                return 'float';
            case 'uniqueidentifier':
                return 'string';
            default:
                return 'mixed'; 
        }
    }


    private function getFieldAnnotation($column, $type): string
    {
        $fieldAnnotation = sprintf(
            "[ORM\Column(name: \"%s\", type: \"%s\", nullable: %s)]", 
            $column->getName(), 
            $type, 
            $column->getNotnull() ? 'false' : 'true'
        );

        if($type === 'mixed'){
            $fieldAnnotation = sprintf(
                "[ORM\Column(name: \"%s\", type: \"%s\")]", 
                $column->getName(), 
                $type
            );
        }

        if($type === 'string' && ($column->getLength() > 0)){
            $fieldAnnotation = sprintf(
                "[ORM\Column(name: \"%s\", type: \"%s\", length: %d)]", 
                $column->getName(), 
                $type,
                $column->getLength()
            );
        }

        return $fieldAnnotation;
    }
}
