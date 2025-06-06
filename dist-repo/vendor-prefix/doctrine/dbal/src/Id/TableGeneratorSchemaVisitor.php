<?php

namespace Archetype\Vendor\Doctrine\DBAL\Id;

use Archetype\Vendor\Doctrine\DBAL\Schema\Column;
use Archetype\Vendor\Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Archetype\Vendor\Doctrine\DBAL\Schema\Index;
use Archetype\Vendor\Doctrine\DBAL\Schema\Schema;
use Archetype\Vendor\Doctrine\DBAL\Schema\Sequence;
use Archetype\Vendor\Doctrine\DBAL\Schema\Table;
use Archetype\Vendor\Doctrine\DBAL\Schema\Visitor\Visitor;
use Archetype\Vendor\Doctrine\Deprecations\Deprecation;
/** @deprecated */
class TableGeneratorSchemaVisitor implements Visitor
{
    /** @var string */
    private $generatorTableName;
    /** @param string $generatorTableName */
    public function __construct($generatorTableName = 'sequences')
    {
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/4681', 'The TableGeneratorSchemaVisitor class is is deprecated.');
        $this->generatorTableName = $generatorTableName;
    }
    /**
     * {@inheritDoc}
     */
    public function acceptSchema(Schema $schema)
    {
        $table = $schema->createTable($this->generatorTableName);
        $table->addColumn('sequence_name', 'string');
        $table->addColumn('sequence_value', 'integer', ['default' => 1]);
        $table->addColumn('sequence_increment_by', 'integer', ['default' => 1]);
    }
    /**
     * {@inheritDoc}
     */
    public function acceptTable(Table $table)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function acceptColumn(Table $table, Column $column)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function acceptIndex(Table $table, Index $index)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function acceptSequence(Sequence $sequence)
    {
    }
}
