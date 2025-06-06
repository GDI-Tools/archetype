<?php

namespace Archetype\Vendor\Doctrine\DBAL\Schema\Visitor;

use Archetype\Vendor\Doctrine\DBAL\Schema\Column;
use Archetype\Vendor\Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Archetype\Vendor\Doctrine\DBAL\Schema\Index;
use Archetype\Vendor\Doctrine\DBAL\Schema\Schema;
use Archetype\Vendor\Doctrine\DBAL\Schema\Sequence;
use Archetype\Vendor\Doctrine\DBAL\Schema\Table;
/**
 * Abstract Visitor with empty methods for easy extension.
 *
 * @deprecated
 */
class AbstractVisitor implements Visitor, NamespaceVisitor
{
    public function acceptSchema(Schema $schema)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function acceptNamespace($namespaceName)
    {
    }
    public function acceptTable(Table $table)
    {
    }
    public function acceptColumn(Table $table, Column $column)
    {
    }
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
    }
    public function acceptIndex(Table $table, Index $index)
    {
    }
    public function acceptSequence(Sequence $sequence)
    {
    }
}
