<?php
/**
 * Packages a plugin.
 *
 * @package     smClayToDoctrineSchemaPlugin
 * @subpackage  task
 * @author      Craig Mason <mason@stasismedia.com>
 * @version     0.0.3
 */
class smClayGenerateDoctrineSchemaTask extends sfBaseTask
{

  protected function configure()
  {
    $this->namespace = 'clay';
    $this->name = 'generate-doctrine-schema';

    $this->briefDescription = 'Transforms clay model to schema';

    $this->addArgument(
      'output',
      sfCommandArgument::OPTIONAL,
      'The doctrine schema file',
      sfConfig::get('sf_config_dir') . DIRECTORY_SEPARATOR
        . 'doctrine' . DIRECTORY_SEPARATOR . 'schema.yml'
    );

    $this->addOption(
    	'model',
        null,
        sfCommandOption::PARAMETER_OPTIONAL,
        'The model filename',
        'lib.model.clay'
    );

    $this->detailedDescription = <<<EOF
The [clay:generate-doctrine-schema|INFO] generates a doctrine schema from a clay model.
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    // -- tranformation exists ?
    $transformation_filename = dirname(__FILE__).'/../../data/transform/clay2doctrine.xsl';
    if ( !file_exists($transformation_filename) ) {
        throw new Exception( "The transformation doesn't exist." );
    }


    // -- model exists ?
    $model_filename = sprintf( '%s'.DIRECTORY_SEPARATOR.'model'.DIRECTORY_SEPARATOR. $options['model'], sfConfig::get('sf_data_dir') );
    if ( !file_exists($model_filename) ) {
        throw new Exception( "Missing .clay model file. Please ensure it exists in data/model/" );
    }


    // -- schema exists ?
    $schema_filename = sprintf( '%s'.DIRECTORY_SEPARATOR.'doctrine'.DIRECTORY_SEPARATOR.'schema.xml', sfConfig::get('sf_config_dir') );
    if ( file_exists($schema_filename) ) {
      // Backup schema
      copy( $schema_filename, $schema_filename . '.previous' );
    }

    // Load in the CLAY model file
    $xml = new DOMDocument();
    $xml->load($model_filename);
    $this->logSection('xml', 'Loaded Clay file');

    // Load in the XSL transform sheet
    $xsl = new DOMDocument();
    $xsl->load($transformation_filename);
    $this->logSection('xsl', 'Loaded transformation file');

    // Create a new XSTL Processor and transform to XML
    $proc = new XSLTProcessor();
    $proc->importStyleSheet($xsl);
    $this->logSection('xsl', 'Processing XSL template');

    $xmlSchema = $proc->transformToXml($xml);
    $this->logSection('xml', 'Transforming to XML');

    // Clear up variables
    unset($xml);
    unset($xsl);
    unset($proc);

    $xml = new DOMDocument();
    $xml->loadXML($xmlSchema);

    $xpath = new DOMXPath($xml);

    $doctrineSchema = array();

    $model = $xpath->query("//database");
    $beginScript = $model->item(0)->getAttribute('beginScript');


    $tables = $xpath->query("//table");

    // Loop through all tables
    for($i = 0; $i < $tables->length; $i++)
    {
      $table = $tables->item($i);

      $tableName = $table->getAttribute('name');
      $tableAlias = $table->getAttribute('alias');
      $class = sfInflector::camelize($tableName);

      $doctrineSchema[$class]['tableName'] = $tableName;


      $tableChildNodes = $table->childNodes;
      for($j = 0; $j < $tableChildNodes->length; $j++)
      {
        $columnScale = null;

        $tableChild = $tableChildNodes->item($j);
        $nodeName = $tableChild->nodeName;

        // COLUMN
        if($nodeName == "column")
        {
          $columnName = $tableChild->getAttribute('name');

          // If we have a created_at or updated_at column, add a behaviour
          if($columnName == 'created_at' || $columnName == 'updated_at')
          {
            $doctrineSchema[$class]['actAs']['Timestampable'] = '';
            // Leave this iteration
            continue;
          }


          switch($tableChild->getAttribute('type'))
          {
            case 'INTEGER':
              $columnType = 'integer';
              $columnSize = $tableChild->getAttribute('size') ? $tableChild->getAttribute('size') : '4';
              break;

            case 'STRING':
            case 'CHAR':
            case 'VARCHAR':
              $columnType = 'string';
              $columnSize = $tableChild->getAttribute('size');
              break;

            case 'TEXT':
              $columnType = 'string';
              $columnSize = 4000;
              break;

            case 'DECIMAL' :
              $columnType  = 'decimal';
              $columnSize  = (float) $tableChild->getAttribute('size');
              $columnScale = (float) $tableChild->getAttribute('decimalDigits');
              break;

            case 'TIMESTAMP':
              $columnType = 'timestamp';
              break;

            case 'DATE':
              $columnType = 'date';
              break;

            case 'TIME':
              $columnType = 'time';
              break;

            case 'DATETIME':
              $columnType = 'timestamp';
              break;

            case 'FLOAT':
              $columnType = 'float';
              break;

            case 'BOOLEAN':
              $columnType = 'boolean';
              break;

            default:
              $columnType = '';
              $columnSize = '';
              break;
          }

          $doctrineSchema[$class]['columns'][$columnName]['type'] = $columnType;

          if(isset($columnSize))
          {
            $doctrineSchema[$class]['columns'][$columnName]['length'] = $columnSize;
          }
          if(isset($columnScale))
          {
            $doctrineSchema[$class]['columns'][$columnName]['scale'] = $columnScale;
          }

          if($tableChild->getAttribute('required') == "true" && $tableChild->getAttribute('primaryKey') != "true")
          {
            $doctrineSchema[$class]['columns'][$columnName]['notblank'] = "true";
          }
          if($tableChild->hasAttribute('default'))
          {
            switch($columnType)
            {
              case "float":
              case "decimal":
                $default = (float) $tableChild->getAttribute('default');
                break;
              default:
                $default = $tableChild->getAttribute('default');
            }
            $doctrineSchema[$class]['columns'][$columnName]['default'] = $default;
            // Remove the requirement for 'not blank' as this will cause issues in doctrine
            $doctrineSchema[$class]['columns'][$columnName]['notblank'] = "false";
          }


          if($tableChild->getAttribute('primaryKey') == "true")
          {
            $doctrineSchema[$class]['columns'][$columnName]['primary'] = "true";
          }

          if($tableChild->getAttribute('autoIncrement') == "true")
          {
            $doctrineSchema[$class]['columns'][$columnName]['autoincrement'] = "true";
          }
        }

        // FOREIGN KEY
        elseif($nodeName == "foreign-key")
        {
          $foreignTable = $tableChild->getAttribute('foreignTable');
          $foreignClass = sfInflector::camelize($foreignTable);

          $foreignKeyChilds = $tableChild->childNodes;
          $referenceNode = null;

          for($k = 0; $k < $foreignKeyChilds->length; $k++)
          {
            if($foreignKeyChilds->item($k)->nodeType == XML_ELEMENT_NODE && $foreignKeyChilds->item($k)->nodeName == "reference")
            {
              $referenceNode = $foreignKeyChilds->item($k);
              break;
            }
          }

          if(!$referenceNode)
          {
            continue;
          }

          $onDelete = ($tableChild->getAttribute('onDelete') == 'setnull') ? 'null' : $tableChild->getAttribute('onDelete');

          //$alias = sfInflector::camelize(substr($referenceNode->getAttribute('local'), 0, -3));

          $doctrineSchema[$class]['relations'][$foreignClass] = array(
            'class' => $foreignClass,
            'foreign' => $referenceNode->getAttribute('foreign'),
            'local' => $referenceNode->getAttribute('local'),
            'onDelete' => $onDelete
          );

          // Check if we have set a relationship alias for foreignAlias
          $alias = $tableChild->getAttribute('alias');
          if($alias != "")
          {
            $doctrineSchema[$class]['relations'][$foreignClass]['foreignAlias'] = $alias;
          }

          // Check if this is a 1:1 relationship
          $sourceMultiplicity = $tableChild->getAttribute('sourceMultiplicity');
          if($sourceMultiplicity == 1)
          {
            $doctrineSchema[$class]['relations'][$foreignClass]['foreignType'] = 'one';
            //$doctrineSchema[$foreignClass]['relations'][$class]['onDelete'] = $onDelete;
          }


        }

        // UNIQUE COLUMN
        elseif($nodeName == "unique")
        {
          $indexName = $tableChild->getAttribute('name');

          $foreignKeyChilds = $tableChild->childNodes;
          $fields = array();

          for($k = 0; $k < $foreignKeyChilds->length; $k++)
          {
            if($foreignKeyChilds->item($k)->nodeType == XML_ELEMENT_NODE && $foreignKeyChilds->item($k)->nodeName == "unique-column")
            {
              $fields[] = $foreignKeyChilds->item($k)->getAttribute('name');
            }
          }

          $doctrineSchema[$class]['indexes'][$indexName] = array(
            'fields' => $fields,
            'type' => 'unique'
          );
        }

        unset($columnSize);
      }
    }

    //var_dump($doctrineSchema);

    // Write schema
    if(file_exists($arguments['output']))
    {
      $this->getFilesystem()->remove($arguments['output']);
    }

    $output = $beginScript . "\n\n" . sfYaml::dump($doctrineSchema, 5);
    file_put_contents($arguments['output'], $output);
    $this->logSection('file+', $arguments['output']);


  }
}
